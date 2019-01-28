<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Eris\Generator;
use Tests\Generator\JsonSchema\SchemaGenerator;
use Swaggest\JsonSchema\Schema;
use ZiffDavis\JsonSchemaFaker\Faker as JsonSchemaFaker;

class JsonSchemaFakerTest extends TestCase
{
    use \Eris\TestTrait;

    public function dataGenerateValidSchemaInstance()
    {
        return array_map(function ($generatorMethod) {
            return [$generatorMethod];
        }, [
            "validNumber",
            "validInteger",
            "validBoolean",
            "validNull",
            "validObject",
            "validSumType"
        ]);
    }

    /**
     * @dataProvider dataGenerateValidSchemaInstance
     */
    public function testGenerateValidSchemaInstance($generatorMethod)
    {
        $this->doSTuff(SchemaGenerator::$generatorMethod());
    }

    public function testGenerateValidArrayInstance()
    {
        $this->doSTuff(SchemaGenerator::validArray());
    }

    public function testGenerateValidStringInstance()
    {
        $this->doSTuff(SchemaGenerator::validString());
    }

    public function testInstance()
    {
        $json = <<<JSON
{
    "type": "string",
    "maxLength": 788,
    "if": {
        "pattern": "^[a-z]$"
    },
    "then": {
        "maxLength": 606
    },
    "else": {
        "minLength": 538
    },
    "allOf": [
        {}
    ]
}
JSON;

        $json = <<<JSON
{
            "type": [
                "string",
                "number"
            ],
            "if": {
                "pattern": "^[a-z]$"
            },
            "then": {
                "maxLength": 294
            },
            "else": {
                "minLength": 126
            },
            "allOf": [
                {}
            ],
            "minimum": 1.2000000000000002,
            "maxim
JSON;

        $json = <<<JSON
{
            "type": [
                "string",
                "number"
            ],
            "if": {
                "pattern": "^[a-z]$"
            },
            "then": {
                "maxLength": 294
            },
            "else": {
                "minLength": 126
            },
            "allOf": [
                {}
            ],
            "minimum": 1.2000000000000002,
            "maximum": 1.2000000000000002,
            "exclusiveMinimum": 1.1000000000000001,
            "exclusiveMaximum": 1.3
}
JSON;

        $schema = json_decode($json);
        $schemaInstance = JsonSchemaFaker::fake($schema);

        try {
            Schema::import($schema)->in($schemaInstance);
        } catch (\Exception $e) {
            $printableSchemaInstance = json_encode($schemaInstance, JSON_PRETTY_PRINT);
            $printableSchema = json_encode($schema, JSON_PRETTY_PRINT);
            $message = "The following schema instance:\n\n$printableSchemaInstance\n\nIs invalid according to the following schema:\n\n$printableSchema\n\nValidation error: {$e->getMessage()}";

            $this->fail($message);
        }

        $this->assertTrue(true);
    }

    private function doSTuff($generator)
    {
        $this->forAll($generator)->then(function ($schema) {
            $schemaInstance = JsonSchemaFaker::fake($schema);

            try {
                Schema::import($schema)->in($schemaInstance);
            } catch (\Exception $e) {
                $printableSchemaInstance = json_encode($schemaInstance, JSON_PRETTY_PRINT);
                $printableSchema = json_encode($schema, JSON_PRETTY_PRINT);
                $message = "The following schema instance:\n\n$printableSchemaInstance\n\nIs invalid according to the following schema:\n\n$printableSchema\n\nValidation error: {$e->getMessage()}";

                $this->fail($message);
            }

            $this->assertTrue(true);
        });
    }
}
