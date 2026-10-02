<?php

require_once (__DIR__."/school_activity.php");

function cycle_enrollment_mode_labels()
{
    return [
        "school" => "École",
        "of" => "OF",
        "ofa" => "OF ALT",
        "cfa" => "CFA",
    ];
}

function cycle_enrollment_mode_options($id_cycle)
{
    $id_cycle = (int)$id_cycle;
    $labels = cycle_enrollment_mode_labels();
    if ($id_cycle <= 0)
        return ($labels);

    $schools = db_select_all("
        school.*
        FROM school_cycle
        LEFT JOIN school ON school.id = school_cycle.id_school
        WHERE school_cycle.id_cycle = $id_cycle
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
    ");

    // Un cycle non encore rattaché à une école reste configurable. Dès qu'une
    // école est liée, seuls ses modes réellement actifs sont proposés.
    if (!count($schools))
        return ($labels);

    $allowed = [];
    foreach ($schools as $school)
    {
        $flags = school_activity_flags($school);
        if (!empty($flags["is_school"]))
            $allowed["school"] = $labels["school"];
        if (!empty($flags["is_of"]))
        {
            $allowed["of"] = $labels["of"];
            $allowed["ofa"] = $labels["ofa"];
        }
        if (!empty($flags["is_cfa"]))
            $allowed["cfa"] = $labels["cfa"];
    }
    return ($allowed);
}

function cycle_enrollment_mode_validate($id_cycle, $mode)
{
    $mode = trim((string)$mode);
    $labels = cycle_enrollment_mode_labels();
    if ($mode == "" || !isset($labels[$mode]))
        return (new ErrorResponse("CannotEdit", "Mode d'inscription invalide."));

    $allowed = cycle_enrollment_mode_options($id_cycle);
    if (!isset($allowed[$mode]))
        return (new ErrorResponse(
            "CannotEdit",
            "Le mode ".$labels[$mode]." n'est pas actif pour l'établissement de ce cycle."
        ));
    return (new ValueResponse($mode));
}

function cycle_user_link_extra_properties($id_cycle)
{
    return [
        [
            "name" => "Mode d'inscription",
            "codename" => "enrollment_mode",
            "type" => "select",
            "options" => cycle_enrollment_mode_options($id_cycle),
            "all_options" => cycle_enrollment_mode_labels(),
            "empty_label" => "Mode d'inscription…",
            "unavailable_suffix" => " (indisponible actuellement)",
            "required" => true,
            "on_create" => true,
            "admin_func" => "is_director_for_cycle",
        ],
        [
            "name" => "Nom français",
            "codename" => "fr_name",
            "admin_func" => "is_director_for_cycle",
        ],
        [
            "name" => "Nom anglais",
            "codename" => "en_name",
            "admin_func" => "is_director_for_cycle",
        ],
        [
            "name" => isset($GLOBALS["Dictionnary"]["Curriculum"])
                ? $GLOBALS["Dictionnary"]["Curriculum"] : "Cursus",
            "codename" => "cursus",
            "admin_func" => "is_director_for_cycle",
        ],
        [
            "name" => isset($GLOBALS["Dictionnary"]["GeneralComment"])
                ? $GLOBALS["Dictionnary"]["GeneralComment"] : "Commentaire général",
            "codename" => "commentaries",
            "admin_func" => "is_director_for_cycle",
        ],
    ];
}
