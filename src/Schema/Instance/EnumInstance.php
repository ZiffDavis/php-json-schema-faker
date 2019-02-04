<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

class EnumInstance extends AbstractInstance
{
    public $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function append(InstanceInterface $instance): InstanceInterface
    {
        if ($instance instanceof self) {
            return new self(array_intersect($this->options, $instance->options));
        }

        return $this;
    }

    public function realize()
    {
        return $this->options[array_rand($this->options)];
    }
}
