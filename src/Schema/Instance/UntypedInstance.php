<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

use Faker\Generator as FakerGenerator;

class UntypedInstance implements InstanceInterface
{
    public $validationSets = [];

    public function __construct(ValidationKeywords ...$validations)
    {
        $this->validationSets = $validations;
    }

    public function combine(InstanceInterface $instance): InstanceInterface
    {
        if ($instance instanceof UntypedInstance) {
        }
    }

    public function realize(FakerGenerator $faker)
    {
        // TODO: something
    }
}
