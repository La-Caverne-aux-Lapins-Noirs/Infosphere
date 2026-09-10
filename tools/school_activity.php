<?php

/** NULL keeps pre-migration installations and historical rows readable. */
function school_activity_flags(array $school)
{
    $of = trim((string)($school["formation_activity_number"] ?? "")) !== "";
    $cfa = trim((string)($school["alternation_registration_number"] ?? "")) !== ""
        || trim((string)($school["cfa_name"] ?? "")) !== "";
    return [
        "is_school" => isset($school["is_school"]) ? (int)(bool)$school["is_school"]
            : (int)(trim((string)($school["school_registration_number"] ?? "")) !== "" || (!$of && !$cfa)),
        "is_of" => isset($school["is_of"]) ? (int)(bool)$school["is_of"] : (int)$of,
        "is_cfa" => isset($school["is_cfa"]) ? (int)(bool)$school["is_cfa"] : (int)$cfa,
    ];
}

function school_activity_labels(array $school)
{
    $labels = [];
    foreach (["is_school" => "École", "is_of" => "OF", "is_cfa" => "CFA"] as $key => $label)
        if (school_activity_flags($school)[$key])
            $labels[] = $label;
    return $labels;
}

function school_activity_updates(array $data)
{
    $out = [];
    foreach (["is_school", "is_of", "is_cfa"] as $key)
        if (array_key_exists($key, $data))
        {
            if (!is_scalar($data[$key]) || !in_array((string)$data[$key], ["0", "1"], true))
                return new ErrorResponse("CannotEdit", "Statut établissement invalide : ".$key);
            $out[$key] = (int)$data[$key];
        }
    return new ValueResponse($out);
}
