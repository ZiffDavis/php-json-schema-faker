<?php

namespace ZiffDavis\JsonSchemaFaker;

class Faker
{
    public static function fake($schema)
    {
        // TODO: json schema validator might do some of this for me
        if (filter_var($schema, FILTER_VALIDATE_URL) !== false) {
            try {
                $schema = file_get_contents($schema);
            } catch (\Exception $e) {
                throw new \Exception(
                    "Failed to download JSON schema from '$schema' with error: {$e->getMessage()}"
                );
            }
        }

        if (! ($schema instanceof \stdClass)) {
            $schema = json_decode($schema);

            if (! ($schema instanceof \stdClass)) {
                $message = "Unable to decode provided schema with error: ";

                if (($error = json_last_error_msg()) && $error !== "No error") {
                    $message .= $error;
                } else {
                    $message .= "Unknown error";
                }

                throw new \Exception($message);
            }
        }

        return Schema::make($schema)->generateInstance();
    }
}
