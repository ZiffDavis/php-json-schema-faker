<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

use Faker\Generator as FakerGenerator;

class BooleanInstance extends AbstractInstance
{
    public function combine(InstanceInterface $instance): InstanceInterface
    {
        return $this;
    }

    public function realize(FakerGenerator $faker)
    {
        return (bool) rand(0, 1);
    }
}
