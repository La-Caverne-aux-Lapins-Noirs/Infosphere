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

function school_activity_mode_allowed(array $school, $mode)
{
    $mode = strtolower(trim((string)$mode));
    if ($mode === "other")
        return (true);
    $map = [
        "school" => "is_school",
        "ecole" => "is_school",
        "of" => "is_of",
        "ofa" => "is_of",
        "cfa" => "is_cfa",
    ];
    if (!isset($map[$mode]))
        return (false);
    $flags = school_activity_flags($school);
    return (!empty($flags[$map[$mode]]));
}

/**
 * Values exposed to generated documents. The database keeps every historical
 * value, but an inactive activity must never leak one of its administrative
 * identifiers into a newly generated document.
 */
function school_activity_document_fields(array $school)
{
    $flags = school_activity_flags($school);
    $school_mode = !empty($flags["is_school"]);
    $formation_mode = !empty($flags["is_of"]) || !empty($flags["is_cfa"]);
    $cfa_mode = !empty($flags["is_cfa"]);
    $uai_mode = $school_mode || $cfa_mode;

    return ([
        "NDA" => $formation_mode ? (string)($school["formation_activity_number"] ?? "") : "",
        "UAI" => $uai_mode ? (string)($school["uai"] ?? "") : "",
        "cfa_name" => $cfa_mode ? (string)($school["cfa_name"] ?? "") : "",
        "executing_establishment_name" => $cfa_mode ? (string)($school["executing_establishment_name"] ?? "") : "",
        "school_registration_number" => $school_mode ? (string)($school["school_registration_number"] ?? "") : "",
        "school_registration_academy" => $school_mode ? (string)($school["school_registration_academy"] ?? "") : "",
        "formation_activity_number" => $formation_mode ? (string)($school["formation_activity_number"] ?? "") : "",
        "formation_activity_region" => $formation_mode ? (string)($school["formation_activity_region"] ?? "") : "",
        "alternation_registration_number" => $cfa_mode ? (string)($school["alternation_registration_number"] ?? "") : "",
        "alternation_registration_academy" => $cfa_mode ? (string)($school["alternation_registration_academy"] ?? "") : "",
    ]);
}

function school_activity_contract_kind_mode($kind)
{
    $kind = strtoupper(trim((string)$kind));
    $map = [
        "ECL" => "school",
        "OF" => "of",
        "OFA" => "ofa",
        "CFA" => "cfa",
    ];
    return ($map[$kind] ?? NULL);
}

function school_activity_contract_kind_allowed(array $school, $kind)
{
    $mode = school_activity_contract_kind_mode($kind);
    return ($mode === NULL ? true : school_activity_mode_allowed($school, $mode));
}

/** Resolve the activity required by a Dabsic contract model when identifiable. */
function school_activity_contract_file_mode($file)
{
    $file = (string)$file;
    $basename = strtolower(pathinfo($file, PATHINFO_FILENAME));
    $names = [
        "contrat_ecole" => "school",
        "contract_school" => "school",
        "ecl" => "school",
        "contrat_of_hors_alternance" => "of",
        "contrat_of" => "of",
        "of" => "of",
        "contrat_of_alternance" => "ofa",
        "ofa" => "ofa",
        "contrat_cfa" => "cfa",
        "cfa" => "cfa",
    ];
    if (isset($names[$basename]))
        return ($names[$basename]);
    if (!is_file($file) || !is_readable($file))
        return (NULL);
    $content = @file_get_contents($file);
    if ($content === false)
        return (NULL);
    if (preg_match('/^[ \\t]*CFA[ \\t]*=[ \\t]*1[ \\t]*$/mi', $content))
        return ("cfa");
    if (preg_match('/^[ \\t]*OF[ \\t]*=[ \\t]*1[ \\t]*$/mi', $content))
        return ("of");
    if (preg_match('/^[ \\t]*(?:Ecole|École)[ \\t]*=[ \\t]*1[ \\t]*$/miu', $content))
        return ("school");
    return (NULL);
}

function school_activity_contract_file_allowed(array $school, $file)
{
    $mode = school_activity_contract_file_mode($file);
    return ($mode === NULL ? true : school_activity_mode_allowed($school, $mode));
}
