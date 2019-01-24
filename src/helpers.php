<?php

namespace JsonSchemaFaker;

function merge_schemas($schema, $subSchema)
{
    // TODO: lots of merge detail work to do here 
    // TODO: ideally would be immutable

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

    return $schema;
}
