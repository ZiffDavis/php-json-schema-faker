<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

class NotInstance implements InstanceInterface
{
    public $instance;

    public function __construct(InstanceInterface $instance)
    {
        $this->instance = $instance;
    }
}
