<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

use Faker\Factory as FakerFactory;

class SumInstance extends AbstractAggregateInstance
{
    public function __construct(InstanceInterface ...$instances)
    {
        if (!$instances) {
            throw new \Exception(get_class($this) . " must have at least one sub instance");
        }

        return parent::__construct(...$instances);
    }

    public function combine(InstanceInterface $instance): InstanceInterface
    {
        $newInstances = array_map(function (InstanceInterface $otherInstance) use ($instance): InstanceInterface {
            return $instance->combine($otherInstance);
        }, $this->instances);

        return new self(...$newInstances);
    }

    public function realize(FakerFactory $faker)
    {
        return $this->instances[array_rand($this->instances)]->realize($faker);
    }
}
