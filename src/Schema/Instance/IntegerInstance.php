<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

use Faker\Factory as FakerFactory;

class IntegerInstance extends AbstractInstance
{
    public function append(InstanceInterface $instance): InstanceInterface
    {
        return $this;
    }

    public function realize(FakerFactory $faker);
    {
        return null;
    }
}
