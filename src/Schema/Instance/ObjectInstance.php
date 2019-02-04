<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

use Faker\Factory as FakerFactory;

class ObjectInstance extends AbstractInstance
{
    public function combine(InstanceInterface $instance): InstanceInterface
    {
        if ($instance instanceof self) {
            $symmetricDifference = array_merge(
                array_diff_key($instance->properties, $this->validations->properties),
                array_diff_key($this->validations->properties, $instance->properties)
            );
            $intersectingKeys = array_keys(array_intersect_key($instance->properties, $this->validations->properties));
            $properties = $symmetricDifference;

            foreach ($intersectingKeys as $property) {
                $properties[$property] = $instance->properties[$property]->append($this->validations->properties[$property]);
            }

            return new self($properties);
        }

        return $this;
    }

    public function realize(FakerFactory $faker);
    {
        $obj = new \stdClass;

        foreach ($this->validations->properties as $property => $instance) {
            $obj->{$property} = $instance->realize();
        }

        return $obj;
    }
}
