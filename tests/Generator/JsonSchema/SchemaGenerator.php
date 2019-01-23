<?php

namespace Tests\Generator\JsonSchema;

use Eris\Generator;
use Eris\Generator\GeneratedValueSingle;
use Eris\Random\RandomRange;

// TODO: need to handle "default"
class SchemaGenerator implements Generator
{
    private const MAX_DEPTH = 5;
    private $method;
    private $args;
    private $nestedSchemaDepth;
    private $methods = ["validObject", "validString", "validNumber", "validInteger", "validBoolean", "validNull"];

    public static function __callStatic($name, $args)
    {
        return new self($name, $args);
    }

    public function __construct($method, $args)
    {
        $this->method = $method;
        $this->args = $args;
        $this->nestedSchemaDepth = 0;
    }

    public function __invoke($size, RandomRange $rand)
    {
        return $this->{$this->method}($size, $rand);
    }

    private function validObject($size, RandomRange $rand)
    {
        $schema = new \stdClass;
        $schema->type = "object";

        if ($rand->rand(0, 1)) {
            $schema->maxProperties = $rand->rand(5, 10);
        }

        if ($rand->rand(0, 1)) {
            $schema->minProperties = $rand->rand(0, 5);
        }

        $pool = implode(range("a", "z"));
        $numProperties = $rand->rand($schema->minProperties ?? 0, $schema->maxProperties ?? 10);
        $properties = new \stdClass;

        for ($i = 0; $i < $numProperties; $i++) {
            $validSchema = $this->validSchema($size, $rand)->unbox();

            if ($validSchema->type === "object") {
                $this->nestedSchemaDepth++;
            }

            if ($this->nestedSchemaDepth > self::MAX_DEPTH) {
                // array_values here is used to renumber the array
                $this->methods = array_values(array_filter($this->methods, function ($method) {
                    return $method !== "validObject";
                }));
            }

            $properties->{substr(str_shuffle($pool), 0, $rand->rand(5,10))} = $validSchema;
        }

        $schema->properties = $properties;

        return GeneratedValueSingle::fromJustValue($schema, self::class);

        // TODO: more properties
    }

    private function validString($size, RandomRange $rand)
    {
        $schema = new \stdClass;
        $schema->type = "string"; // TODO: what is the correct behavior if type is omitted?

        switch ($rand->rand(0, 3)) {
        case 0:
            // TODO: should randomize
            $schema->regex = "^[0-9A-Za-z]{23}$";
            break;
        case 1:
            $minLength = $rand->rand(0, 500);
            $maxLength = $rand->rand(501, 1000);

            if ($rand->rand(0, 1)) {
                $schema->minLength = $minLength;
            }

            if ($rand->rand(0, 1)) {
                $schema->maxLength = $maxLength;
            }
            break;
        case 2:
            $formats = ["regex", "uri", "date-time"];
            // TODO: support more formats and formats with "minLength", "regex", etc 
            $schema->format = $formats[$rand->rand(0, count($formats) - 1)];
            break;
        default:
            $schema->minLength = $schema->maxLength = $rand->rand(0, 1000);
        }

        return GeneratedValueSingle::fromJustValue($schema, self::class);
    }

    // TODO: need to test "multipleOf"
    private function validNumber($size, RandomRange $rand)
    {
        return $this->validInteger($size, $rand)->map(function ($schema) use ($rand) {
            $schema->type = "number"; // TODO: what is the correct behavior if type is omitted?
            $multiplier = $rand->rand(1, 100) / 100;

            foreach (["maximum", "minimum", "exclusiveMinimum", "exclusiveMaximum"] as $property) {
                if (isset($schema->{$property})) {
                    $schema->{$property} = $multiplier * $schema->{$property};
                }
            }

            return $schema;
        }, self::class);
    }

    // TODO: need to test "multipleOf"
    private function validInteger($size, RandomRange $rand)
    {
        $schema = new \stdClass;
        $schema->type = "integer";

        if ($rand->rand(0, 1)) {
            if ($rand->rand(0, 1)) {
                $schema->maximum = $schema->minimum = $rand->rand(0, 1000);
            }

            if ($rand->rand(0, 1)) {
                $minMax = $schema->maximum ?? $rand->rand(0, 1);
                $schema->exclusiveMinimum = $minMax - 1;
                $schema->exclusiveMaximum = $minMax + 1;
            }
        } else if ($rand->rand(0, 1)) {
            if ($rand->rand(0, 1)) {
                $schema->maximum = $rand->rand(500, 1000);
            }

            if ($rand->rand(0, 1)) {
                $schema->exclusiveMaximum = $rand->rand(501, 1000);
            }

            if ($rand->rand(0, 1)) {
                $schema->minimum = $rand->rand(0, 500);
            }

            if ($rand->rand(0, 1)) {
                $schema->exclusiveMinimum = $rand->rand(0, 499);
            }
        }

        return GeneratedValueSingle::fromJustValue($schema, self::class);
    }

    private function validBoolean($size, RandomRange $rand)
    {
        $schema = new \stdClass;
        $schema->type = "boolean";

        return GeneratedValueSingle::fromJustValue($schema, self::class);
    }

    private function validNull($size, RandomRange $rand)
    {
        $schema = new \stdClass;
        $schema->type = "null";

        return GeneratedValueSingle::fromJustValue($schema, self::class);
    }

    private function validSchema($size, RandomRange $rand)
    {
        return $this->{$this->methods[$rand->rand(0, count($this->methods) - 1)]}($size, $rand);
    }

    public function shrink(GeneratedValueSingle $element)
    {
        return $element;
    }

    private function validateSchema($schema)
    {
        Schema::import(json_decode(file_get_contents(__DIR__ . "/schemas/metaschema.json")))->in($schema);
    }
}
