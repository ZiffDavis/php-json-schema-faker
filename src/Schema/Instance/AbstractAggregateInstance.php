<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

abstract class AbstractAggregateInstance implements InstanceInterface
{
    use CombinableTrait;

    public $instances = [];

    public function __construct(InstanceInterface ...$instances)
    {
        $this->instances = $instances;
    }
}
