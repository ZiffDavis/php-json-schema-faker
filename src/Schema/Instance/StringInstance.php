<?php

namespace ZiffDavis\JsonSchemaFaker\Schema\Instance;

use Faker\Generator as FakerGenerator;

class StringInstance extends AbstractInstance
{
    public function combine(InstanceInterface $instance): InstanceInterface
    {
        if ($instance instanceof self) {
            $pattern = $this->validations->pattern ?? $instance->pattern;
            $minLength = max($this->validations->minLength ?? 0, $instance->maxLength ?? 0);
            $maxLength = min($this->validations->maxLength ?? 100, $instance->maxLength ?? 100);
            $format = $this->validations->format ?? $instance->format;

            return new self($pattern, $minLength, $maxLength, $format);
        } else if ($instance instanceof EnumInstance) {
            // filter out enum values that don't match this StringInstance's validation rules
            return new EnumInstance(array_filter($instance->options, function ($option): string {
                // TODO: not sure how to handle format here exactly...
                return is_string($option) && 
                    (!$this->validations->pattern || preg_match($this->validations->pattern, $option)) &&
                    (!$this->validations->minLength || strlen($option) > $this->validations->minLength) &&
                    (!$this->validations->maxLength || strlen($option) < $this->validations->maxLength);
            }));
        } else if ($instance instanceof AllInstance) {
            return $this->combineAll(...$instance->instances);
        }

        return $this;
    }

    public function realize(FakerGenerator $faker)
    {
        // TODO: this must be a non-negative integer. check if that's allowed by JSON schema lib
        // TODO: should it be possible for maxLength to be less than minLength
        // TODO: "pattern" property with "minLength" and "maxLength" is tricky

        if (!empty($this->validations->pattern)) {
            return $faker->regexify($this->validations->pattern);
        }

        $minLength = $this->validations->minLength ?? 0;
        $maxLength = $this->validations->maxLength ?? 100; // setting to arbitrary length for simplicity
        $maxLength = $minLength > $maxLength ? $minLength : $maxLength;

        if (isset($this->validations->format)) {
            switch ($this->validations->format) {
            case "uri":
                // TODO: should restrict size of randomly generated URL according to $minLength and $maxLength
                return $faker->url;
            case "date-time":
                // TODO: should restrict size of randomly generated datetime according to $minLength and $maxLength
                return $faker->date("c");
            case "regex":
                // TODO: more random
                return "[a-zA-Z0-9]";
            default:
                throw new \Exception("Unsupported format value '{$this->validations->format}'");
            }
        }

        $textLength = rand($minLength, $maxLength);

        if ($textLength < 5) {
            return str_pad("", $textLength, "a");
        }

        $string = "";

        while (strlen($string) < $textLength) {
            $string .= $faker->text();
        }

        return substr($string, 0, $textLength);
    }
}
