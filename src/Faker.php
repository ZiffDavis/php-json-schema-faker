<?php

namespace ZiffDavis\JsonSchemaFaker;

use Faker\Factory as FakerFactory;
use Swaggest\JsonSchema\Schema;

class Faker
{
    private static $typeGenerators = [];

    public static function fake($schema)
    {
        // TODO: json schema validator might do some of this for me
        if (filter_var($schema, FILTER_VALIDATE_URL) !== false) {
            try {
                $schema = file_get_contents($schema);
            } catch (\Exception $e) {
                throw new \Exception(
                    "Failed to download JSON schema from '$schema' with error: {$e->getMessage()}"
                );
            }
        }

        if (! ($schema instanceof \stdClass)) {
            $schema = json_decode($schema);

            if (! ($schema instanceof \stdClass)) {
                $message = "Unable to decode provided schema with error: ";

                if (($error = json_last_error_msg()) && $error !== "No error") {
                    $message .= $error;
                } else {
                    $message .= "Unknown error";
                }

                throw new \Exception($message);
            }
        }

        // TODO: should ensure valid JSON Schema

        // prevents accidental mutation of provided schema
        $newSchema = self::cloneValue($schema);

        return self::generateInstance($newSchema, $newSchema);
    }

    private static function cloneValue($value)
    {
        if (is_object($value)) {
            return self::cloneObject($value);
        } else if (is_array($value)) {
            return self::cloneArray($value);
        } 

        return self::cloneScalar($value);
    }

    private static function cloneObject($value)
    {
        $clone = new \stdClass;

        foreach (get_object_vars($value) as $k => $v) {
            $clone->{$k} = self::cloneValue($v);
        }

        return $clone;
    }

    private static function cloneArray($value)
    {
        $clone = [];

        foreach ($value as $k => $v) {
            $clone[$k] = self::cloneValue($v);
        }

        return $clone;
    }

    private static function cloneScalar($value)
    {
        return $value;
    }

    private static function generateInstance($schema, $originalSchema)
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
                    $obj->{$property} = self::generateInstance($subSchema, $originalSchema);
                }
            }

            $allOf = $schema->allOf ?? [];

            return $obj;
        };
        $typeGenerators["string"] = function ($schema, $faker) {
            // TODO: this must be a non-negative integer. check if that's allowed by JSON schema lib
            // TODO: should it be possible for maxLength to be less than minLength
            // TODO: "pattern" property with "minLength" and "maxLength" is tricky

            if (!empty($schema->pattern)) {
                return $faker->regexify($schema->pattern);
            }

            $minLength = $schema->minLength ?? 0;
            $maxLength = $schema->maxLength ?? 100; // setting to arbitrary length for simplicity
            $maxLength = $minLength > $maxLength ? $minLength : $maxLength;

            if (isset($schema->format)) {
                switch ($schema->format) {
                case "uri":
                    // TODO: should restrict size of randomly generated URL according to $minLength and $maxLength
                    return $faker->url;
                case "date-time":
                    // TODO: should restrict size of randomly generated datetime according to $minLength and $maxLength
                    return $faker->date("c");
                case "regex":
                    // TODO: more random
                    return "[a-zA-Z0-9]";
                default:
                    throw new \Exception("Unsupported format value '{$schema->format}'");
                }
            }

            $textLength = rand($minLength, $maxLength);

            if ($textLength < 5) {
                return str_pad("", $textLength, "a");
            }

            $string = "";

            while (strlen($string) < $textLength) {
                $string .= $faker->text();
            }

            return substr($string, 0, $textLength);
        };
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
                $array[] = self::generateInstance($subSchema, $originalSchema);
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

        $schema = self::transformSchema($schema);

        if (empty($schema->type)) {
            $schema->type = array_keys($typeGenerators)[array_rand(array_keys($typeGenerators))];
        } else {
            $schema->type = is_array($schema->type) ? $schema->type[array_rand($schema->type)] : $schema->type; // TODO: type may not exist
        }

        $schemaInstance = $typeGenerators[$schema->type]($schema, FakerFactory::create());
        $conditionalSchemas = $schema->allOf ?? [];

        foreach ($conditionalSchemas as $conditionalSchema) {
            try {
                Schema::import($conditionalSchema->if)->in($schemaInstance);

                if (isset($conditionalSchema->then)) {
                    $schema = self::mergeSchemas($schema, $conditionalSchema->then);
                }
            } catch (\Exception $e) {
                if (isset($conditionalSchema->else)) {
                    $schema = self::mergeSchemas($schema, $conditionalSchema->else);
                }
            }
        }

        return $typeGenerators[$schema->type]($schema, FakerFactory::create());
    }

    /**
     * "Lifts" a schema's subschemas found in properties like "allOf", "anyOf", "oneOf", etc. into the schema to simplify value generation.
     * Will only partially merge subschemas with conditional keywords.
     */
    private static function transformSchema($schema)
    {
        $subSchemas = self::extractSubSchemas($schema);

        while (count($subSchemas) > 0) {
            $subSchema = array_shift($subSchemas);
            $subSchemas = $subSchemas + self::extractSubSchemas($subSchema);
            $schema = self::mergeSchemas($schema, $subSchema);
        }

        return $schema;
    }

    private static function extractSubSchemas($schema)
    {
        $anyOf = !empty($schema->anyOf) ? [$anyOf[array_rand($anyOf)]] : [];
        $oneOf = !empty($schema->oneOf) ? [$oneOf[array_rand($oneOf)]] : []; // TODO: should validate against exactly one of these

        return ($schema->allOf ?? []) + $anyOf + $oneOf;
    }

    private static function mergeSchemas($schema, $subSchema)
    {
        // TODO: lots of merge detail work to do here 
        // TODO: ideally would be immutable

        $conditionalSchemas = [];

        if (isset($subSchema->if) && (isset($subSchema->then) || isset($subSchema->else))) {
            $conditionalSchema = new \stdClass;
            $conditionalSchema->if = $subSchema->if;

            if (isset($subSchema->then)) {
                $conditionalSchema->then = $subSchema->then;
            } else {
                $conditionalSchema->else = $subSchema->else;
            }

            $conditionalSchemas[] = $conditionalSchema;
        }

        $schema->allOf = $schema->allOf ?? [];
        $schema->allOf = $schema->allOf + [$subSchema] + $conditionalSchemas;

        return $schema;
    }
}
