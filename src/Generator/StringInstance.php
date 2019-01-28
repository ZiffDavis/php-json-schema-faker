<?php

namespace ZiffDavis\JsonSchemaFaker\Generator;

use Swaggest\JsonSchema\Schema;
use function ZiffDavis\JsonSchemaFaker\merge_schemas;
use function ZiffDavis\JsonSchemaFaker\merge_sub_schemas;
use function ZiffDavis\JsonSchemaFaker\extract_conditionals;
use function ZiffDavis\JsonSchemaFaker\clone_value;

class StringInstance
{
    public function __invoke($schema, $faker)
    {
        // TODO: this must be a non-negative integer. check if that's allowed by JSON schema lib
        // TODO: should it be possible for maxLength to be less than minLength
        // TODO: "pattern" property with "minLength" and "maxLength" is tricky

        $schema = merge_sub_schemas($schema);

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

        $string = substr($string, 0, $textLength);
        [$schema, $conditionalSchemas] = extract_conditionals($schema);

        foreach ($conditionalSchemas as $conditionalSchema) {
            if (isset($conditionalSchema->if) && (isset($conditionalSchema->then) || isset($conditionalSchema->else))) {
                try {
                    // TODO: how to handle "allOf" and other complex properties on "if" schema
                    Schema::import($conditionalSchema->if)->in($string);

                    if (isset($conditionalSchema->then)) {
                        // TODO: how to handle complex "then" schemas
                        $schema = merge_schemas($schema, $conditionalSchema->then);
                    }
                } catch (\Exception $e) {
                    if (isset($conditionalSchema->else)) {
                        // TODO: how to handle complex "else" schemas
                        $schema = merge_schemas($schema, $conditionalSchema->else);
                    }
                }

                $string = $this->__invoke($schema, $faker);
            }
        }

        return $string;
    }
}
