<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

trait CombinableTrait
{
    public function combineAll(InstanceInterface ...$instances): InstanceInterface
    {
        return array_reduce($instances, function(InstanceInterface $instance, InstanceInterface $otherInstance) {
            return $instance->combine($otherInstance);
        }, $this);
    }
}
