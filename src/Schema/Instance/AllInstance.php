<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

use Faker\Generator as FakerGenerator;

class AllInstance extends AbstractAggregateInstance
{
    public function combine(InstanceInterface $instance): InstanceInterface
    {
        return new self($this->instances + [$instance]);
    }

    public function realize(FakerGenerator $faker)
    {
        return $this->combineAll($this->instances)->realize($faker);
    }
}
