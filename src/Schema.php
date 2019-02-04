<?php

namespace ZiffDavis\JsonSchemaFaker;

use ZiffDavis\JsonSchemaFaker\Schema\Instance;

class Schema
{
    private const TYPES = [
        "array",
        "boolean",
        "integer",
        "null",
        "number",
        "object",
        "string"
    ];
    private $spec;
    private $parentSchema;

    public static function make(\stdClass $spec, ?self $parentSchema = null): self
    {
        return new self(clone_value($spec), $parentSchema);
    }

    public function toInstance()
    {
        $instances = array_map(function ($schema) {
            return self::make($schema)->toInstance();
        }, $this->spec->allOf ?? []);
        $conditionInstance = new Instance\EmptyInstance();

        if (isset($this->spec->if) && (isset($this->spec->then) || isset($this->spec->else))) { 
            $conditionInstance = self::make($this->spec->if)->toInstance();
            $thenInstance = new Instance\EmptyInstance();
            $elseInstance = new Instance\EmptyInstance();

            if (isset($this->spec->then)) {
                $thenInstance = new Instance\AllInstance(
                    self::make($this->spec->then)->toInstance(),
                    $conditionInstance
                );
            }

            if (isset($this->spec->else)) {
                $elseInstance = new Instance\AllInstance(
                    self::make($this->spec->else)->toInstance(),
                    new Instance\NotInstance($conditionInstance)
                );
            }

            $conditionInstance = new Instance\SumInstance($thenInstance, $elseInstance);
        }

        $typeInstance = new Instance\EmptyInstance();

        if (isset($this->spec->enum)) {
            $typeInstance = new Instance\EnumInstance($this->spec->enum);
        } else if (is_array($this->spec->type)) {
            $typeInstance = new Instance\SumInstance(array_map(function (string $type): Instance\InstanceInterface {
                return $this->toInstanceByType($type);
            }, $this->spec->type));
        } else if (isset($this->spec->type)) {
            $typeInstance = $this->toInstanceByType($this->spec->type);
        }

        return new Instance\AllInstance($conditionInstance, $typeInstance, ...$instances);
    }

    private function toInstanceByType(string $type): Instance\InstanceInterface
    {
        switch($type) {
        case "string":
            return new Instance\StringInstance(
                $this->spec->pattern ?? null,
                $this->spec->minLength ?? null,
                $this->spec->maxLength ?? null,
                $this->spec->format ?? null
            );
        case "null":
            return new Instance\NullInstance();
        case "object":
            $properties = [];

            foreach (get_object_vars($this->spec->properties) as $property => $value) {
                $properties[$property] = self::make($value)->toInstance();
            }

            return new Instance\ObjectInstance($properties);
        case "boolean":
            return new Instance\BooleanInstance();
        }

        throw new \Exception("Cannot make schema instance for unknown type '$type'");
    }

    private function oldestAncestor(): self
    {
        $ancestor = $this;

        while (isset($ancestor->parentSchema)) {
            $ancestor = $ancestor->parentSchema;
        }

        return $ancestor;
    } 

    private function __construct(\stdClass $spec, ?self $parentSchema)
    {
        $this->spec = $spec;
        $this->parentSchema = $parentSchema;
    }

    // TODO: merge into type instances
    private static function _generateInstance($schema, $originalSchema)
    {
        if (is_bool($schema)) {
            return $schema;
        }

        $typeGenerators = [];
        $typeGenerators["array"] = function ($schema, $faker) use ($originalSchema) {
            // TODO: "Omitting this keyword has the same behavior as an empty schema" what does this mean? 
            // TODO: Need to handle "contains" validation keyword
            $minItems = $schema->minItems ?? 0;
            $maxItems = $schema->maxItems ?? 10;
            $maxItems = $minItems > $maxItems ? $minItems : $maxItems;
            $numItems = rand($minItems, $maxItems);

            // TODO: "items" defaults to "empty schema" so I'm using a string default here
            $items = $schema->items ?? (object) ["type" => "string"]; 
            $subSchemas = is_array($items) ? $items : array_pad([], $numItems, $items);
            $array = [];

            if (count($subSchemas) < $numItems && isset($schema->additionalItems)) {
                $subSchemas = array_pad($subSchemas, $numItems, $schema->additionalItems);
            }

            if ($schema->uniqueItems ?? false) {
                // TODO: this uniqueness constraint will recurse to all nested schemas (not sure if desired)
                $faker = $faker->unique();
            }

            for ($i = 0; $i < $numItems; $i++) {
                $subSchema = $subSchemas[$i] ?? $subSchemas[0];
                $array[] = self::_generateInstance($subSchema, $originalSchema);
            }

            return $array;
        };
        $typeGenerators["integer"] = function ($schema, $faker) {
            $multipleOf = $schema->multipleOf ?? null;
            $minimum = max(
                isset($schema->minimum) ? $schema->minimum : -INF,
                isset($schema->exclusiveMinimum) ? $schema->exclusiveMinimum + 1 : -INF
            );
            $maximum = min(
                isset($schema->maximum) ? $schema->maximum : INF, // arbitrary maximum
                isset($schema->exclusiveMaximum) ? $schema->exclusiveMaximum - 1 : INF, // arbitrary maximum
                $minimum === -INF ? 1000 : $minimum + 1000
            );
            $number = $faker->numberBetween($minimum === -INF ? $maximum - 1000 : $minimum, $maximum);

            if ($multipleOf) {
                $number *= $multipleOf;
            }

            return $number;
        };

        $typeGenerators["number"] = function ($schema, $faker) {
            $multipleOf = $schema->multipleOf ?? null;
            $minimum = max(
                isset($schema->minimum) ? $schema->minimum : -INF,
                isset($schema->exclusiveMinimum) ? $schema->exclusiveMinimum : -INF
            );
            $maximum = min(
                isset($schema->maximum) ? $schema->maximum : INF,
                isset($schema->exclusiveMaximum) ? $schema->exclusiveMaximum : INF,
                $minimum === -INF ? 1000 : $minimum + 1000
            );

            do {
                $number = $faker->randomFloat(999999999, $minimum === -INF ? $maximum - 1000 : $minimum, $maximum);
            } while(
                (isset($schema->exclusiveMaximum) && $schema->exclusiveMaximum === $number) || 
                (isset($schema->exclusiveMinimum) && $schema->exclusiveMinimum === $number)
            );

            if ($multipleOf) {
                $number *= $multipleOf;
            }

            return $number;
        };

        if (isset($schema->{'$ref'})) {
            // TODO: need to handle $id in $ref
            $referencedSchema = $originalSchema;
            $properties = array_slice(explode("/", $schema->{'$ref'}), 1);

            foreach ($properties as $property) {
                $referencedSchema = $referencedSchema[$property];
            }

            $schema = $referencedSchema;
        }

        if (empty($schema->type)) {
            $schema->type = array_keys($typeGenerators)[array_rand(array_keys($typeGenerators))];
        } else {
            $schema->type = is_array($schema->type) ? $schema->type[array_rand($schema->type)] : $schema->type; // TODO: type may not exist
        }

        return $typeGenerators[$schema->type]($schema, FakerFactory::create());
    }
}
