<?php

namespace ZiffDavis\JsonSchemaFaker;

function extract_conditionals($schema) 
{
    $conditionalSchemas = [];
    $newSchema = merge_sub_schemas($schema);

    if (isset($newSchema->if) && (isset($newSchema->then) || isset($newSchema->else))) {
        $conditionalSchema = new \stdClass;
        $conditionalSchema->if = $newSchema->if;

        if (isset($newSchema->then)) {
            $conditionalSchema->then = $newSchema->then;
        }

        if (isset($newSchema->else)) {
            $conditionalSchema->else = $newSchema->else;
        }

        $conditionalSchemas[] = $conditionalSchema;

        unset($newSchema->if);
        unset($newSchema->then);
        unset($newSchema->else);
    }

    foreach ($newSchema->allOf ?? [] as $subSchema) {
        [$newSubSchema, $conditionalSchema] = extract_conditional($subSchema);
        $newSchema = merge_schemas($newSchema, $newSubSchema);
        $conditionalSchemas[] = $conditionalSchema;
    }

    $newSchema->allOf = [];

    return [$newSchema, $conditionalSchemas];
}

function extract_conditional($schema)
{
    $newSchema = clone_value($schema);

    $conditionalSchema = new \stdClass;
    if (isset($newSchema->if) && (isset($newSchema->then) || isset($newSchema->else))) {
        $conditionalSchema->if = $newSchema->if;

        if (isset($newSchema->then)) {
            $conditionalSchema->then = $newSchema->then;
        }

        if (isset($newSchema->else)) {
            $conditionalSchema->else = $newSchema->else;
        }
    }

    unset($newSchema->if);
    unset($newSchema->then);
    unset($newSchema->else);

    return [$newSchema, $conditionalSchema];
}

function extract_sub_schemas($schema)
{
    $newSchema = clone_value($schema);

    $anyOf = !empty($newSchema->anyOf) ? [$anyOf[array_rand($anyOf)]] : [];
    $oneOf = !empty($newSchema->oneOf) ? [$oneOf[array_rand($oneOf)]] : []; // TODO: should validate against exactly one of these
    $subSchemas = ($newSchema->allOf ?? []) + $anyOf + $oneOf;

    unset($newSchema->allOf);
    unset($newSchema->anyOf);
    unset($newSchema->oneOf);

    return [$newSchema, $subSchemas];
}

function merge_sub_schemas($schema)
{
    [$newSchema, $subSchemas] = extract_sub_schemas($schema);

    while (count($subSchemas) > 0) {
        $subSchema = array_shift($subSchemas);
        [$newSubSchema, $moreSubSchemas] = extract_sub_schemas($subSchema);
        $subSchemas = $subSchemas + $moreSubSchemas;
        $newSchema = merge_schemas($newSchema, $newSubSchema);
    }

    return $newSchema;
}

function merge_schemas($schema, $otherSchema)
{
    // TODO: lots of merge detail work to do here 
    // TODO: ideally would be immutable

    $newSchema = new \stdClass; 

    if (isset($schema->type) || isset($otherSchema->type)) {
        $newSchema->type = $schema->type ?? $subSchema->type;
    }

    if (isset($schema->pattern) || isset($otherSchema->pattern)) {
        // TODO: merge regex?
        $newSchema->pattern = $schema->pattern ?? $otherSchema->pattern;
    }

    if (isset($schema->minLength) || isset($otherSchema->minLength)) {
        $newSchema->minLength = max($schema->minLength ?? 0, $otherSchema->minLength ?? 0);
    }

    if (isset($schema->maxLength) || isset($otherSchema->maxLength)) {
        $newSchema->maxLength = min($schema->maxLength ?? 100, $otherSchema->maxLength ?? 100);
    }

    if (isset($schema->maximum) || isset($otherSchema->maximum)) {
        $newSchema->maximum = min($schema->maximum ?? INF, $otherSchema->maximum ?? INF);
    }

    if (isset($schema->exclusiveMaximum) || isset($otherSchema->exclusiveMaximum)) {
        $newSchema->exclusiveMaximum = min($schema->exclusiveMaximum ?? INF, $otherSchema->exclusiveMaximum ?? INF);
    }

    if (isset($schema->minimum) || isset($otherSchema->minimum)) {
        $newSchema->minimum = max($schema->minimum ?? -INF, $otherSchema->minimum ?? -INF);
    }

    if (isset($schema->exclusiveMinimum) || isset($otherSchema->exclusiveMinimum)) {
        $newSchema->exclusiveMinimum = max($schema->exclusiveMinimum ?? -INF, $otherSchema->exclusiveMinimum ?? -INF);
    }

    // extract conditionals and stuff them into their own schemas on the "allOf" property
    // TODO: would be nice to make it more clear that this is happening
    $conditionals = [];

    if (isset($schema->if) && (isset($schema->then) || isset($schema->else))) {
        $conditionalSchema = new \stdClass;
        $conditionalSchema->if = $schema->if;

        if (isset($schema->then)) {
            $conditionalSchema->then = $schema->then;
        }

        if (isset($schema->else)) {
            $conditionalSchema->else = $schema->else;
        }

        $conditionals[] = $conditionalSchema;
    }

    if (isset($otherSchema->if) && (isset($otherSchema->then) || isset($otherSchema->else))) {
        $conditionalSchema = new \stdClass;
        $conditionalSchema->if = $otherSchema->if;

        if (isset($otherSchema->then)) {
            $conditionalSchema->then = $otherSchema->then;
        }

        if (isset($otherSchema->else)) {
            $conditionalSchema->else = $otherSchema->else;
        }

        $conditionals[] = $conditionalSchema;
    }

    // only set "allOf" property when it's a non-empty array per the spec
    if (count($conditionals) > 0 || count($schema->allOf ?? []) > 0 || count($otherSchema->allOf ?? []) > 0) {
        $newSchema->allOf = $conditionals + ($schema->allOf ?? []) + ($otherSchema->allOf ?? []);
    }

    return $newSchema;
}

function clone_value($value)
{
    if (is_object($value) && get_class($value) === "stdClass") {
        return clone_std_class($value);
    } else if (is_array($value)) {
        return clone_array($value);
    } else if (is_scalar($value) || $value === null) {
        return $value;
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
