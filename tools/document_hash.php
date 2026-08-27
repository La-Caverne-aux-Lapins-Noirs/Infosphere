<?php

/**
 * Reserved variables made available to every DocBuilder model.
 *
 * DocBuilder owns the authoritative SHA-256 computation. Infosphere reserves
 * the field as empty during its preliminary mergeconf/form pass so it is never
 * exposed as something an operator can fill manually.
 */
function document_builder_dabsic_hash_fields($hash = "")
{
    return ([
        "DocBuilder.DabsicHash" => (string)$hash,
    ]);
}

function document_builder_append_dabsic_hash_parts(array $parts, $hash = "")
{
    foreach (document_builder_dabsic_hash_fields($hash) as $key => $value)
        $parts[] = ["type" => "field", "key" => $key, "value" => $value];
    return ($parts);
}

function document_builder_append_dabsic_hash_field_strings(array $fields, $hash = "")
{
    foreach (document_builder_dabsic_hash_fields($hash) as $key => $value)
        $fields[] = $key."=".$value;
    return ($fields);
}
