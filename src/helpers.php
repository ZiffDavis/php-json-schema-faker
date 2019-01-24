<?php

namespace ZiffDavis\JsonSchemaFaker;

function merge_schemas($schema, $otherSchema)
{
    // TODO: lots of merge detail work to do here 
    // TODO: ideally would be immutable

    /*
    $conditionalSchemas = [];

    if (isset($subSchema->if) && (isset($subSchema->then) || isset($subSchema->else))) {
        $conditionalSchema = new \stdClass;
        $conditionalSchema->if = $subSchema->if;

        if (isset($subSchema->then)) {
            $conditionalSchema->then = $subSchema->then;
        } else {
            $conditionalSchema->else = $subSchema->else;
        }

        $conditionalSchemas[] = $conditionalSchema;
    }

    $schema->allOf = $schema->allOf ?? [];
    $schema->allOf = $schema->allOf + [$subSchema] + $conditionalSchemas;
     */

    $newSchema = new \stdClass; 

    if (isset($schema->pattern) || isset($otherSchema->pattern)) {
        // TODO: merge regex?
        $newSchema->pattern = $schema->pattern ?? $otherSchema->pattern;
    }

    $newSchema->minLength = max($schema->minLength ?? 0, $otherSchema->minLength ?? 0);
    $newSchema->maxLength = min($schema->maxLength ?? 100, $otherSchema->maxLength ?? 100);

    return $newSchema;
}

function clone_value($value)
{
    if (is_object($value) && get_class($value) === "stdClass") {
        return clone_std_class($value);
    } else if (is_array($value)) {
        return clone_array($value);
    } else if (is_scalar($value)) {
        return identity($value);
    }

    throw new \Exception("Unable to clone type: " . gettype($value));
}

function clone_std_class($value)
{
    $clone = new \stdClass;

    foreach (get_object_vars($value) as $k => $v) {
        $clone->{$k} = clone_value($v);
    }

    return $clone;
}

function clone_array($value)
{
    $clone = [];

    foreach ($value as $k => $v) {
        $clone[$k] = clone_value($v);
    }

    return $clone;
}

function identity($value)
{
    return $value;
}
