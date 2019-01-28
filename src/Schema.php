<?php

namespace ZiffDavis\JsonSchemaFaker;

use Faker\Factory as FakerFactory;
use function ZiffDavis\JsonSchemaFaker\clone_value;
use ZiffDavis\JsonSchemaFaker\Generator\StringInstance;

class Schema
{
    private $spec;
    private static $typeGenerators = [];

    public static function make(\stdClass $spec): self
    {
        return new self(clone_value($spec));
    }

    public function generateInstance()
    {
        return self::_generateInstance($this->spec, $this->spec);
    }

    private function __construct(\stdClass $spec)
    {
        $this->spec = $spec;
    }

    private static function _generateInstance($schema, $originalSchema)
    {
        if (is_bool($schema)) {
            return $schema;
        }

        $typeGenerators = [];
        $typeGenerators["object"] = function ($schema, $faker) use ($originalSchema) {
            // TODO: lots more properties to support here
            // TODO: Can "if" keywords exist directly on the object?
            $obj = new \stdClass;

            // TODO: should decide which properties to add based on "required"
            if (isset($schema->properties)) {
                foreach ($schema->properties as $property => $subSchema) {
                    $obj->{$property} = self::_generateInstance($subSchema, $originalSchema);
                }
            }

            $allOf = $schema->allOf ?? [];

            return $obj;
        };
        $typeGenerators["string"] = new StringInstance();
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
        $typeGenerators["null"] = function ($schema, $faker) {
            return null;
        };
        $typeGenerators["boolean"] = function ($schema, $faker) {
            return (bool) rand(0, 1);
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

        if (isset($schema->enum)) {
            return $schema->enum[array_rand($schema->enum)];
        }

        if (empty($schema->type)) {
            $schema->type = array_keys($typeGenerators)[array_rand(array_keys($typeGenerators))];
        } else {
            $schema->type = is_array($schema->type) ? $schema->type[array_rand($schema->type)] : $schema->type; // TODO: type may not exist
        }

        return $typeGenerators[$schema->type]($schema, FakerFactory::create());
    }
}
