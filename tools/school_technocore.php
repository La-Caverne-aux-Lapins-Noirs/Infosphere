<?php

/**
 * School-level TechnoCore conventions used by both DocBuilder and Evaluator.
 *
 * Scolaire may keep local profiles for authoring/tests, but production
 * configuration is stored directly on the school row as JSON so the same
 * reusable subject can be rendered and evaluated with the conventions of the
 * school that uses it.
 */

function school_technocore_column_available()
{
    static $available = NULL;

    if ($available !== NULL)
        return ($available);
    if (!function_exists("db_select_rows"))
        return (false);
    $available = in_array("technocore_configuration_json", db_select_rows("school"), true);
    return ($available);
}

function school_technocore_defaults()
{
    return ([
        "configured" => false,
        "configuration_error" => NULL,
        "function_prefix" => "",
        "function_suffix" => "",
        "macro_prefix" => "",
        "macro_suffix" => "",
        "putchar_name" => "",
        "default_evaluation_enabled" => 1,
        "default_evaluation_cleanliness" => 1,
        "default_evaluation_norm" => 0,
        "default_evaluation_make" => 1,
        "default_evaluation_check" => 1,
        "default_evaluation_install" => 0,
    ]);
}

function school_technocore_configuration($id_school)
{
    $configuration = school_technocore_defaults();
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return ($configuration);
    if (!school_technocore_column_available())
    {
        $configuration["configuration_error"] =
            "school.technocore_configuration_json column is missing";
        return ($configuration);
    }

    $row = db_select_one("technocore_configuration_json
        FROM school
        WHERE id = $id_school");
    if (!is_array($row))
        return ($configuration);

    $raw = trim((string)($row["technocore_configuration_json"] ?? ""));
    if ($raw == "" || $raw == "{}")
        return ($configuration);

    $decoded = json_decode($raw, true);
    if (!is_array($decoded))
    {
        $configuration["configuration_error"] =
            "Invalid JSON in school.technocore_configuration_json";
        return ($configuration);
    }

    foreach (["function_prefix", "function_suffix", "macro_prefix", "macro_suffix", "putchar_name"] as $field)
        if (array_key_exists($field, $decoded))
            $configuration[$field] = (string)$decoded[$field];

    $evaluation = isset($decoded["default_evaluation"]) && is_array($decoded["default_evaluation"])
        ? $decoded["default_evaluation"]
        : [];
    $evaluation_fields = [
        "enabled" => "default_evaluation_enabled",
        "cleanliness" => "default_evaluation_cleanliness",
        "norm" => "default_evaluation_norm",
        "make" => "default_evaluation_make",
        "check" => "default_evaluation_check",
        "install" => "default_evaluation_install",
    ];
    foreach ($evaluation_fields as $json_field => $field)
    {
        if (array_key_exists($json_field, $evaluation))
            $configuration[$field] = !empty($evaluation[$json_field]) ? 1 : 0;
        // Accept an older/hand-written flat representation as a harmless fallback.
        else if (array_key_exists($field, $decoded))
            $configuration[$field] = !empty($decoded[$field]) ? 1 : 0;
    }

    $configuration["configured"] = true;
    return ($configuration);
}

function school_technocore_bool($data, $field, $default = false)
{
    if (!array_key_exists($field, $data))
        return ($default ? 1 : 0);
    $value = $data[$field];
    if (is_bool($value))
        return ($value ? 1 : 0);
    if (is_numeric($value))
        return ((int)$value != 0 ? 1 : 0);
    return (in_array(strtolower(trim((string)$value)), ["1", "true", "yes", "on"], true) ? 1 : 0);
}

function school_technocore_identifier($value, $allow_empty = true)
{
    $value = trim((string)$value);
    if ($value == "")
        return ($allow_empty ? "" : false);
    return (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value) === 1 ? $value : false);
}

function school_technocore_component($value)
{
    $value = trim((string)$value);
    if ($value == "")
        return ("");
    return (preg_match('/^[A-Za-z0-9_]+$/D', $value) === 1 ? $value : false);
}

function school_technocore_validate_affixes($prefix, $suffix, $sample)
{
    if ($prefix === false || $suffix === false)
        return (false);
    return (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $prefix.$sample.$suffix) === 1);
}

function school_technocore_save($id_school, array $data, $id_actor = 0)
{
    global $Database;

    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return (new ErrorResponse("InvalidParameter", "school"));
    if (!school_technocore_column_available())
        return (new ErrorResponse("CannotEdit", "school.technocore_configuration_json column is missing"));

    $function_prefix = school_technocore_component($data["function_prefix"] ?? "");
    $function_suffix = school_technocore_component($data["function_suffix"] ?? "");
    $macro_prefix = school_technocore_component($data["macro_prefix"] ?? "");
    $macro_suffix = school_technocore_component($data["macro_suffix"] ?? "");
    $putchar_name = school_technocore_identifier($data["putchar_name"] ?? "", true);

    if (!school_technocore_validate_affixes($function_prefix, $function_suffix, "function") ||
        !school_technocore_validate_affixes($macro_prefix, $macro_suffix, "MACRO") ||
        $putchar_name === false)
        return (new ErrorResponse("InvalidParameter", "TechnoCore identifier"));

    $payload = [
        "version" => 1,
        "function_prefix" => $function_prefix,
        "function_suffix" => $function_suffix,
        "macro_prefix" => $macro_prefix,
        "macro_suffix" => $macro_suffix,
        "putchar_name" => $putchar_name,
        "default_evaluation" => [
            "enabled" => school_technocore_bool($data, "default_evaluation_enabled", false) != 0,
            "cleanliness" => school_technocore_bool($data, "default_evaluation_cleanliness", false) != 0,
            "norm" => school_technocore_bool($data, "default_evaluation_norm", false) != 0,
            "make" => school_technocore_bool($data, "default_evaluation_make", false) != 0,
            "check" => school_technocore_bool($data, "default_evaluation_check", false) != 0,
            "install" => school_technocore_bool($data, "default_evaluation_install", false) != 0,
        ],
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false)
        return (new ErrorResponse("CannotEdit", "school TechnoCore configuration JSON"));

    $json = $Database->real_escape_string($json);
    if (!$Database->query("UPDATE school
        SET technocore_configuration_json = '$json'
        WHERE id = $id_school"))
        return (new ErrorResponse("CannotEdit", "school TechnoCore configuration"));

    // The regular school API log already records the acting user. Keep the
    // actor out of the configuration payload: it is configuration, not audit data.
    unset($id_actor);
    return (new Response);
}

function school_technocore_resolved_putchar(array $configuration)
{
    $explicit = trim((string)($configuration["putchar_name"] ?? ""));
    if ($explicit != "")
        return ($explicit);
    return ((string)($configuration["function_prefix"] ?? "")."putchar".
        (string)($configuration["function_suffix"] ?? ""));
}

function school_technocore_dabsic_quote($value)
{
    return (json_encode((string)$value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function school_technocore_profile_dabsic(array $school, &$error = NULL)
{
    $error = NULL;
    $id_school = (int)($school["id"] ?? 0);
    if ($id_school <= 0)
    {
        $error = "Invalid school";
        return (NULL);
    }
    if (!school_technocore_column_available())
    {
        $error = "school.technocore_configuration_json column is missing";
        return (NULL);
    }

    $configuration = school_technocore_configuration($id_school);
    if (!empty($configuration["configuration_error"]))
    {
        $error = $configuration["configuration_error"];
        return (NULL);
    }
    if (empty($configuration["configured"]))
    {
        $error = "TechnoCore configuration is not configured for school ".
            (string)($school["codename"] ?? $id_school);
        return (NULL);
    }

    $codename = (string)($school["codename"] ?? "");
    $school_name = trim((string)($school["fr_name"] ?? ($school["name"] ?? $codename)));
    $putchar = school_technocore_resolved_putchar($configuration);
    $bool = static function ($value) { return ((int)$value != 0 ? "true" : "false"); };

    return (
        "School = ".school_technocore_dabsic_quote($codename)."\n".
        "SchoolName = ".school_technocore_dabsic_quote($school_name)."\n".
        "FunctionPrefix = ".school_technocore_dabsic_quote($configuration["function_prefix"])."\n".
        "FunctionSuffix = ".school_technocore_dabsic_quote($configuration["function_suffix"])."\n".
        "MacroPrefix = ".school_technocore_dabsic_quote($configuration["macro_prefix"])."\n".
        "MacroSuffix = ".school_technocore_dabsic_quote($configuration["macro_suffix"])."\n".
        "PutChar = ".school_technocore_dabsic_quote($putchar)."\n\n".
        "[DefaultEvaluation\n".
        "  Enabled = ".$bool($configuration["default_evaluation_enabled"])."\n".
        "  Cleanliness = ".$bool($configuration["default_evaluation_cleanliness"])."\n".
        "  Norm = ".$bool($configuration["default_evaluation_norm"])."\n".
        "  Make = ".$bool($configuration["default_evaluation_make"])."\n".
        "  Check = ".$bool($configuration["default_evaluation_check"])."\n".
        "  Install = ".$bool($configuration["default_evaluation_install"])."\n".
        "]\n"
    );
}

function school_technocore_write_profile(array $school, $path, &$error = NULL)
{
    $content = school_technocore_profile_dabsic($school, $error);
    if ($content === NULL)
        return (false);
    if (file_put_contents($path, $content) === false)
    {
        $error = "Cannot write school TechnoCore profile: ".$path;
        return (false);
    }
    return (true);
}

function school_technocore_user_school_ids($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ([]);
    $out = [];
    foreach (db_select_all("DISTINCT id_school
        FROM user_school
        WHERE id_user = $id_user
        ORDER BY id_school") as $row)
        if ((int)($row["id_school"] ?? 0) > 0)
            $out[] = (int)$row["id_school"];
    return ($out);
}

function school_technocore_activity_cycle_school_ids($activity)
{
    $cycles = [];
    foreach ((array)($activity->cycle ?? []) as $cycle)
    {
        $id = (int)($cycle["id_cycle"] ?? ($cycle["id"] ?? 0));
        if ($id > 0)
            $cycles[] = $id;
    }
    $cycles = array_values(array_unique($cycles));
    if (!count($cycles))
        return ([]);

    $out = [];
    foreach (db_select_all("DISTINCT id_school
        FROM school_cycle
        WHERE id_cycle IN (".implode(",", $cycles).")
        ORDER BY id_school") as $row)
        if ((int)($row["id_school"] ?? 0) > 0)
            $out[] = (int)$row["id_school"];
    return ($out);
}

function school_technocore_pick_activity_school($activity, $id_user)
{
    $user_schools = school_technocore_user_school_ids($id_user);
    $activity_schools = school_technocore_activity_cycle_school_ids($activity);

    if (($activity->session_registered ?? NULL) != NULL && function_exists("session_school_ids"))
    {
        $session_schools = array_values(array_unique(array_map(
            "intval", (array)session_school_ids($activity->session_registered)
        )));
        $common = array_values(array_intersect($session_schools, $user_schools));
        if (count($common))
            return ((int)$common[0]);
        if (count($session_schools) == 1)
            return ((int)$session_schools[0]);
    }

    $common = array_values(array_intersect($activity_schools, $user_schools));
    if (count($common))
        return ((int)$common[0]);
    if (count($activity_schools) == 1)
        return ((int)$activity_schools[0]);
    if (count($user_schools) == 1)
        return ((int)$user_schools[0]);
    return (0);
}
