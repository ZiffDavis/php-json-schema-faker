<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

use Faker\Generator as FakerGenerator;

interface InstanceInterface
{
    public function combine(self $instance): self;
    public function combineAll(self ...$instances): self;
    public function realize(FakerGenerator $faker);
}
