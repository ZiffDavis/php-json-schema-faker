<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

// TODO: maybe rename to AbstractTypeInstance
abstract class AbstractInstance implements InstanceInterface
{
    use CombinableTrait;

    public $validations;

    public function __construct(Validations $validations)
    {
        $this->validations = $validations;
    }
}
