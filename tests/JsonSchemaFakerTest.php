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
            "validString",
            "validNumber",
            "validInteger",
            "validBoolean"
        ]);
    }

    /**
     * @dataProvider dataGenerateValidSchemaInstance
     */
    public function testGenerateValidSchemaInstance($generatorMethod)
    {
        $this->forAll(
            SchemaGenerator::$generatorMethod()
        )->then(function ($schema) {
            $schemaInstance = JsonSchemaFaker::fake($schema);

            try {
                Schema::import($schema)->in($schemaInstance);
            } catch (\Exception $e) {
                $printableSchemaInstance = json_encode($schemaInstance);
                $printableSchema = var_export($schema, true);
                $message = "The following schema instance:\n\n$printableSchemaInstance\n\nIs invalid according to the following schema:\n\n$printableSchema\n\nValidation error: {$e->getMessage()}";

                $this->fail($message);
            }

            $this->assertTrue(true);
		});
	}
}
