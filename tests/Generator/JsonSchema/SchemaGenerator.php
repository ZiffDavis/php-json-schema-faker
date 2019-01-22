<?php

namespace Tests\Generator\JsonSchema;

use Eris\Generator;
use Eris\Generator\GeneratedValueSingle;
use Eris\Random\RandomRange;

class SchemaGenerator implements Generator
{
    public static function __callStatic($name, $args)
    {
        return new self($name, $args);
    }

    public function __construct($method, $args)
    {
        $this->method = $method;
        $this->args = $args;
    }

    public function __invoke($size, RandomRange $rand)
    {
        return $this->{$this->method}($size, $rand);
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

    public function shrink(GeneratedValueSingle $element)
    {
        return $element;
    }

    private function validateSchema($schema)
    {
        Schema::import(json_decode(file_get_contents(__DIR__ . "/schemas/metaschema.json")))->in($schema);
    }
}
