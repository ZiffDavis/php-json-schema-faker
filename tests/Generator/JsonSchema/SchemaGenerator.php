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
    private $methods = ["validObject", "validString", "validNumber", "validInteger", "validBoolean", "validNull", "validArray", "validSumType"];

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

    private function validArray($size, RandomRange $rand)
    {
        $schema = new \stdClass;
        $schema->type = "array";
        $currentDepth = $this->nestedSchemaDepth;

        if ($rand->rand(0, 1)) {
            if ($this->nestedSchemaDepth >= self::MAX_DEPTH) {
                // array_values here is used to renumber the array
                $methods = array_values(array_filter($this->methods, function ($method) {
                    return $method !== "validObject" && $method !== "validArray";
                }));
            } else {
                $methods = $this->methods;
            }

            $validSchema = $this->{$methods[$rand->rand(0, count($methods) - 1)]}($size, $rand)->unbox();

            if (($validSchema->type === "object" || $validSchema->type === "array" || in_array($validSchema->type, ["object", "array"], true)) && $currentDepth === $this->nestedSchemaDepth) {
                $this->nestedSchemaDepth++;
            }

            $schema->items = $validSchema;
        }

        // TODO: more properties here

        return GeneratedValueSingle::fromJustValue($schema);
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
        $currentDepth = $this->nestedSchemaDepth;

        if ($this->nestedSchemaDepth >= self::MAX_DEPTH) {
            // array_values here is used to renumber the array
            $methods = array_values(array_filter($this->methods, function ($method) {
                return $method !== "validObject" && $method !== "validArray";
            }));
        } else {
            $methods = $this->methods;
        }

        for ($i = 0; $i < $numProperties; $i++) {
            $validSchema = $this->{$methods[$rand->rand(0, count($methods) - 1)]}($size, $rand)->unbox();

            if (($validSchema->type === "object" || $validSchema->type === "array" || in_array($validSchema->type, ["object", "array"], true)) && $currentDepth === $this->nestedSchemaDepth) {
                $this->nestedSchemaDepth++;
            }

            $properties->{substr(str_shuffle($pool), 0, $rand->rand(5,10))} = $validSchema;
        }

        $schema->properties = $properties;

        switch ($rand->rand(0, 2)) {
        case 0:
            $schema->required = array_keys(get_object_vars($schema->properties));
            break;
        case 1:
            $schema->required = [];
            break;
        default:
            // omit "required" from schema
        }

        $randAdditionalProps = $rand->rand(0, 2);

        switch ($randAdditionalProps) {
        case 0:
        case 1:
            $schema->additionalProperties = (bool) $randAdditionalProps;
            break;
        default:
            // omit "additionalProperties" from schema
        }

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

            // generate string schema with conditional
            if ($rand->rand(0, 1)) {
                $schema->if = new \stdClass;
                $schema->if->pattern = "^[a-z]$";

                if ($maxLength - $minLength >=  10) {
                    if ($rand->rand(0, 1)) {
                        $maxMinDiff = $maxLength - $minLength;
                        $schema->then = new \stdClass;
                        $schema->then->maxLength = $maxLength - floor($maxMinDiff * $rand->rand(0, 100) * 0.01);
                        $maxLength = $schema->then->maxLength;
                    }

                    if ($rand->rand(0, 1)) {
                        $maxMinDiff = $maxLength - $minLength;
                        $schema->else = new \stdClass;
                        $schema->else->minLength = $minLength + floor($maxMinDiff * $rand->rand(0, 100) * 0.01);
                    }
                }
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

    private function validSumType($size, RandomRange $rand)
    {
        $schema = new \stdClass;
        $schema->type = [];
        $currentDepth = $this->nestedSchemaDepth;

        if ($this->nestedSchemaDepth >= self::MAX_DEPTH) {
            // array_values here is used to renumber the array
            $methods = array_values(array_filter($this->methods, function ($method) {
                return $method !== "validObject" && $method !== "validArray" && $method !== "validSumType";
            }));
        } else {
            $methods = array_values(array_filter($this->methods, function ($method) {
                return $method !== "validSumType";
            }));
        }

        $removeMethod = $rand->rand(0, 1) ? "validNumber" : "validInteger";
        $methods = array_values(array_filter($methods, function ($method) use ($removeMethod) {
            return $method !== $removeMethod;
        }));

        $methods = array_slice($methods, 0, $rand->rand(2, 4));

        foreach ($methods as $method) {
            $variant = $this->{$method}($size, $rand)->unbox();

            foreach (get_object_vars($variant) as $property => $value) {
                if ($property === "type") {
                    $schema->type[] = $value;
                } else {
                    $schema->{$property} = $value;
                }
            }
        }

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
