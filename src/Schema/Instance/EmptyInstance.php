<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

use Faker\Generator as FakerGenerator;

class EmptyInstance implements InstanceInterface
{
    public function combine(InstanceInterface $instance): InstanceInterface
    {
        return $instance;
    }

    public function realize(FakerGenerator $faker)
    {
        // empty instances can be any valid JSON value
        // TODO: may want to update this to use other instances for more randomization
        $methods = ["randomFloat", "randomNumber", "randomElements", "text"];
        return $faker->{$methods[array_rand($methods)]};
    }
}
