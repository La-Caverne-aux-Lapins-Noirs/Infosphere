<?php


function cycle_template_import_validation_integer($value, &$parsed = NULL)
{
    if (is_int($value))
    {
        $parsed = $value;
        return (true);
    }
    if (is_string($value) && preg_match('/^-?[0-9]+$/', trim($value)))
    {
        $parsed = (int)trim($value);
        return (true);
    }
    return (false);
}

function cycle_template_import_validation_bool($value, &$parsed = NULL)
{
    if (is_bool($value))
    {
        $parsed = $value ? 1 : 0;
        return (true);
    }
    if (is_int($value) && ($value === 0 || $value === 1))
    {
        $parsed = $value;
        return (true);
    }
    if (is_string($value))
    {
        $key = strtolower(trim($value));
        if (in_array($key, ["0", "false", "no", "off", "non"], true))
        {
            $parsed = 0;
            return (true);
        }
        if (in_array($key, ["1", "true", "yes", "on", "oui"], true))
        {
            $parsed = 1;
            return (true);
        }
    }
    return (false);
}

function cycle_template_import_validation_subscription($value, &$parsed = NULL)
{
    if (cycle_template_import_validation_integer($value, $parsed))
        return ($parsed >= 0 && $parsed <= 2);
    $key = strtolower(trim((string)$value));
    $values = [
        "manual" => 0, "manuel" => 0,
        "mandatory" => 1, "obligatory" => 1, "obligatoire" => 1,
        "automatic" => 2, "automatique" => 2,
    ];
    if (!array_key_exists($key, $values))
        return (false);
    $parsed = $values[$key];
    return (true);
}

function cycle_template_import_validation_declaration_type($value, &$parsed = NULL)
{
    if (cycle_template_import_validation_integer($value, $parsed))
        return ($parsed >= 0 && $parsed <= 2);
    $key = strtolower(trim((string)$value));
    $values = [
        "none" => 0, "disabled" => 0, "aucune" => 0,
        "local" => 1, "locale" => 1,
        "global" => 2, "remote" => 2, "anywhere" => 2,
    ];
    if (!array_key_exists($key, $values))
        return (false);
    $parsed = $values[$key];
    return (true);
}

function cycle_template_import_validate_localized_fields(array $raw, $path, array &$errors, array $fields)
{
    foreach (["fr", "en"] as $lang)
    {
        if (array_key_exists($lang, $raw))
        {
            if (!is_array($raw[$lang]))
                cycle_template_import_error($errors, $path.".".$lang, "doit être un bloc");
            else
            {
                cycle_template_import_unknown_keys($raw[$lang], $fields, $path.".".$lang, $errors);
                foreach ($fields as $field)
                    if (array_key_exists($field, $raw[$lang]) && is_array($raw[$lang][$field]))
                        cycle_template_import_error($errors, $path.".".$lang.".".$field, "doit être une valeur scalaire");
            }
        }
        foreach ($fields as $field)
        {
            $key = $lang."_".$field;
            if (array_key_exists($key, $raw) && is_array($raw[$key]))
                cycle_template_import_error($errors, $path.".".$key, "doit être une valeur scalaire");
        }
    }
}

function cycle_template_import_validate_activity_fields(array $raw, $path, array &$errors)
{
    $bools = [
        "hidden", "mandatory", "allow_unregistration", "validated",
        "template_link", "medal_template", "support_template",
        "progressive_slot_opening", "team_based_slot_opening",
    ];
    foreach ($bools as $field)
    {
        if (!array_key_exists($field, $raw) || $raw[$field] === "")
            continue ;
        $tmp = NULL;
        if (!cycle_template_import_validation_bool($raw[$field], $tmp))
            cycle_template_import_error($errors, $path.".".$field, "booléen attendu (0/1, true/false, yes/no)");
    }

    $integers = [
        "min_team_size" => -1,
        "max_team_size" => -1,
        "maximum_subscription" => -1,
        "money" => 0,
        "estimated_work_duration" => 0,
        "automatic_correction_frequency" => 0,
        "slot_duration" => -1,
        "credit_a" => 0,
        "credit_b" => 0,
        "credit_c" => 0,
        "credit_d" => 0,
    ];
    foreach ($integers as $field => $minimum)
    {
        if (!array_key_exists($field, $raw) || $raw[$field] === "" || $raw[$field] === NULL)
            continue ;
        $parsed = NULL;
        if (!cycle_template_import_validation_integer($raw[$field], $parsed) || $parsed < $minimum)
            cycle_template_import_error($errors, $path.".".$field, "entier >= ".$minimum." attendu");
    }

    foreach (["grade_a", "grade_b", "grade_c", "grade_d", "grade_bonus"] as $field)
    {
        if (!array_key_exists($field, $raw) || $raw[$field] === "" || $raw[$field] === NULL)
            continue ;
        $parsed = NULL;
        if (!cycle_template_import_validation_integer($raw[$field], $parsed) || $parsed < 0 || $parsed > 100)
            cycle_template_import_error($errors, $path.".".$field, "pourcentage entier compris entre 0 et 100 attendu");
    }

    if (array_key_exists("validation", $raw) && $raw["validation"] !== "" && $raw["validation"] !== NULL)
    {
        $parsed = NULL;
        if (!cycle_template_import_validation_integer($raw["validation"], $parsed) || $parsed < 0 || $parsed > 4)
            cycle_template_import_error($errors, $path.".validation", "mode de validation attendu entre 0 et 4");
    }
    if (array_key_exists("subscription", $raw) && $raw["subscription"] !== "" && $raw["subscription"] !== NULL)
    {
        $parsed = NULL;
        if (!cycle_template_import_validation_subscription($raw["subscription"], $parsed))
            cycle_template_import_error($errors, $path.".subscription", "abonnement attendu: Manual, Mandatory, Automatic ou 0..2");
    }
    if (array_key_exists("declaration_type", $raw) && $raw["declaration_type"] !== "" && $raw["declaration_type"] !== NULL)
    {
        $parsed = NULL;
        if (!cycle_template_import_validation_declaration_type($raw["declaration_type"], $parsed))
            cycle_template_import_error($errors, $path.".declaration_type", "type de déclaration attendu: None, Local, Global ou 0..2");
    }

    if (array_key_exists("repository_name", $raw) && $raw["repository_name"] !== "" && $raw["repository_name"] !== NULL)
    {
        if (is_array($raw["repository_name"]))
            cycle_template_import_error($errors, $path.".repository_name", "doit être une valeur scalaire");
        else
        {
            $repository = (string)$raw["repository_name"];
            if (strlen($repository) > 255)
                cycle_template_import_error($errors, $path.".repository_name", "255 caractères maximum");
            if (preg_match('/\\s/u', $repository))
                cycle_template_import_error($errors, $path.".repository_name", "ne doit contenir aucun espace");
        }
    }

    if (array_key_exists("reference_activity", $raw) && $raw["reference_activity"] !== "" && $raw["reference_activity"] !== NULL)
    {
        $reference = $raw["reference_activity"];
        if (!is_string($reference) || !cycle_template_import_is_symbol(trim($reference)))
            cycle_template_import_error($errors, $path.".reference_activity", "codename d'activité invalide");
        else if (strlen(trim($reference)) > 255)
            cycle_template_import_error($errors, $path.".reference_activity", "codename trop long (255 caractères maximum)");
    }

    if (array_key_exists("todolist", $raw) && is_array($raw["todolist"]))
        cycle_template_import_error($errors, $path.".todolist", "doit être une valeur scalaire");

    $min = $max = NULL;
    $min_ok = array_key_exists("min_team_size", $raw)
        && cycle_template_import_validation_integer($raw["min_team_size"], $min);
    $max_ok = array_key_exists("max_team_size", $raw)
        && cycle_template_import_validation_integer($raw["max_team_size"], $max);
    if ($min_ok && $max_ok && $min >= 0 && $max >= 0 && $min > $max)
        cycle_template_import_error($errors, $path, "MinTeamSize ne peut pas être supérieur à MaxTeamSize");

    $grades = [];
    foreach (["grade_a", "grade_b", "grade_c", "grade_d"] as $field)
    {
        $parsed = NULL;
        if (array_key_exists($field, $raw) && cycle_template_import_validation_integer($raw[$field], $parsed))
            $grades[$field] = $parsed;
    }
    foreach ([["grade_a", "grade_b"], ["grade_b", "grade_c"], ["grade_c", "grade_d"]] as [$high, $low])
        if (isset($grades[$high], $grades[$low]) && $grades[$high] < $grades[$low])
            cycle_template_import_error($errors, $path, strtoupper($high)." doit être >= à ".strtoupper($low));

    cycle_template_import_validate_localized_fields(
        $raw, $path, $errors, ["name", "description", "objective", "method", "reference"]
    );
}

function cycle_template_import_validate_cycle_link_fields(array $raw, $path, array &$errors)
{
    if (array_key_exists("week_shift", $raw) && $raw["week_shift"] !== "" && $raw["week_shift"] !== NULL)
    {
        $parsed = NULL;
        if (!cycle_template_import_validation_integer($raw["week_shift"], $parsed))
            cycle_template_import_error($errors, $path.".week_shift", "entier attendu");
    }
    if (array_key_exists("replacement_subscription", $raw)
        && $raw["replacement_subscription"] !== "" && $raw["replacement_subscription"] !== NULL)
    {
        $parsed = NULL;
        if (!cycle_template_import_validation_subscription($raw["replacement_subscription"], $parsed))
            cycle_template_import_error($errors, $path.".replacement_subscription", "abonnement attendu: Manual, Mandatory, Automatic ou 0..2");
    }
    if (array_key_exists("cursus", $raw) && is_array($raw["cursus"]))
        cycle_template_import_error($errors, $path.".cursus", "doit être une valeur scalaire");
}

function cycle_template_import_validate_cycle_fields(array $raw, $path, array &$errors)
{
    cycle_template_import_validate_localized_fields($raw, $path, $errors, ["name"]);
}

function cycle_template_import_validate_session_fields(array $raw, $path, array &$errors)
{
    if (array_key_exists("maximum_subscription", $raw)
        && $raw["maximum_subscription"] !== "" && $raw["maximum_subscription"] !== NULL)
    {
        $parsed = NULL;
        if (!cycle_template_import_validation_integer($raw["maximum_subscription"], $parsed) || $parsed < -1)
            cycle_template_import_error($errors, $path.".maximum_subscription", "entier >= -1 attendu");
    }
    if (array_key_exists("laboratory", $raw) && $raw["laboratory"] !== "" && $raw["laboratory"] !== NULL)
    {
        if (is_array($raw["laboratory"]) || trim((string)$raw["laboratory"]) === "")
            cycle_template_import_error($errors, $path.".laboratory", "codename de laboratoire attendu");
    }
    if (array_key_exists("rooms", $raw) && is_array($raw["rooms"]))
        foreach ($raw["rooms"] as $key => $room)
            if (is_array($room) || trim((string)$room) === "")
                cycle_template_import_error($errors, $path.".rooms.".$key, "codename de salle attendu");
}

function cycle_template_import_relative_date($value, $path, array &$errors)
{
    if ($value === NULL || $value === "")
        return (NULL);
    if (is_int($value) || (is_string($value) && preg_match('/^[0-9]+$/', trim($value))))
        return (max(0, (int)$value));
    if (!is_array($value))
    {
        cycle_template_import_error($errors, $path, "date relative invalide (secondes ou bloc Week/Day/Time attendu)");
        return (NULL);
    }

    $value = cycle_template_import_normalize($value);
    cycle_template_import_unknown_keys($value, ["week", "day", "time", "hour", "begin", "start"], $path, $errors);
    $week = cycle_template_import_value($value, "week", 0);
    if (!is_int($week) && !(is_string($week) && preg_match('/^[0-9]+$/', trim($week))))
    {
        cycle_template_import_error($errors, $path.".week", "doit être un entier >= 0");
        $week = 0;
    }
    $day = cycle_template_import_day(cycle_template_import_value($value, "day", 0));
    if ($day === NULL)
    {
        cycle_template_import_error($errors, $path.".day", "jour invalide");
        $day = 0;
    }
    $clock = cycle_template_import_clock(cycle_template_import_value($value, ["time", "hour", "begin", "start"], "00:00"));
    if ($clock === NULL)
    {
        cycle_template_import_error($errors, $path.".time", "heure invalide, format attendu HH:MM");
        $clock = 0;
    }
    return (((int)$week * 7 + $day) * 86400 + $clock);
}

function cycle_template_import_relation_entries($value)
{
    if ($value === NULL || $value === "")
        return ([]);
    if (!is_array($value))
        return ([$value]);
    return (array_values($value));
}

function cycle_template_import_compile_named_links($value, $path, array &$errors)
{
    $out = [];
    foreach (cycle_template_import_relation_entries($value) as $i => $entry)
    {
        if (is_array($entry))
        {
            $entry = cycle_template_import_normalize($entry);
            cycle_template_import_unknown_keys($entry, ["codename", "name"], $path.".".$i, $errors);
            $entry = cycle_template_import_value($entry, ["codename", "name"], "");
        }
        $entry = trim((string)$entry);
        if ($entry === "")
            cycle_template_import_error($errors, $path.".".$i, "codename obligatoire");
        else
            $out[] = $entry;
    }
    return (array_values(array_unique($out)));
}

function cycle_template_import_compile_teachers($value, $kind, $path, array &$errors, $allow_pay = true)
{
    $out = [];
    foreach (cycle_template_import_relation_entries($value) as $i => $entry)
    {
        $row = ["kind" => $kind, "codename" => "", "teacher_pay" => NULL, "assistant_pay" => NULL];
        if (is_array($entry))
        {
            $entry = cycle_template_import_normalize($entry);
            cycle_template_import_unknown_keys($entry, ["codename", "name", "teacher_pay", "assistant_pay"], $path.".".$i, $errors);
            $row["codename"] = trim((string)cycle_template_import_value($entry, ["codename", "name"], ""));
            if ($allow_pay)
            {
                foreach (["teacher_pay", "assistant_pay"] as $field)
                {
                    $v = cycle_template_import_value($entry, $field, NULL);
                    if ($v !== NULL && $v !== "")
                    {
                        $parsed = NULL;
                        if (!cycle_template_import_validation_integer($v, $parsed) || $parsed < 0)
                            cycle_template_import_error($errors, $path.".".$i.".".$field, "entier >= 0 attendu");
                        else
                            $row[$field] = $parsed;
                    }
                }
            }
            else if (cycle_template_import_value($entry, "teacher_pay", NULL) !== NULL
                || cycle_template_import_value($entry, "assistant_pay", NULL) !== NULL)
                cycle_template_import_error($errors, $path.".".$i, "les rémunérations ne s'appliquent pas aux responsables de cycle");
        }
        else
            $row["codename"] = trim((string)$entry);
        if ($row["codename"] === "")
            cycle_template_import_error($errors, $path.".".$i, "codename obligatoire");
        else
            $out[] = $row;
    }
    return ($out);
}


function cycle_template_import_boolish($value)
{
    if (is_bool($value))
        return ($value ? 1 : 0);
    if (is_numeric($value))
        return ((int)$value ? 1 : 0);
    $value = strtolower(trim((string)$value));
    if (in_array($value, ["true", "yes", "on", "oui"], true))
        return (1);
    if (in_array($value, ["false", "no", "off", "non", ""], true))
        return (0);
    return ((int)$value ? 1 : 0);
}

function cycle_template_import_compile_medals($value, $path, array &$errors)
{
    $out = [];
    foreach (cycle_template_import_relation_entries($value) as $i => $entry)
    {
        $row = ["codename" => "", "role" => 1, "money" => 0, "local" => 0];
        if (is_array($entry))
        {
            $entry = cycle_template_import_normalize($entry);
            cycle_template_import_unknown_keys($entry, ["codename", "name", "role", "money", "mark", "local"], $path.".".$i, $errors);
            $row["codename"] = trim((string)cycle_template_import_value($entry, ["codename", "name"], ""));
            $role = cycle_template_import_value($entry, "role", 1);
            $money = cycle_template_import_value($entry, ["money", "mark"], 0);
            $local = cycle_template_import_value($entry, "local", 0);
            $parsed = NULL;
            if (!cycle_template_import_validation_integer($role, $parsed) || $parsed < -1 || $parsed > 4)
                cycle_template_import_error($errors, $path.".".$i.".role", "rôle de médaille attendu entre -1 et 4");
            else
                $row["role"] = $parsed;
            if (!cycle_template_import_validation_integer($money, $parsed) || $parsed < 0)
                cycle_template_import_error($errors, $path.".".$i.".money", "entier >= 0 attendu");
            else
                $row["money"] = $parsed;
            if (!cycle_template_import_validation_bool($local, $parsed))
                cycle_template_import_error($errors, $path.".".$i.".local", "booléen attendu");
            else
                $row["local"] = $parsed;
        }
        else
            $row["codename"] = trim((string)$entry);
        if ($row["codename"] === "")
            cycle_template_import_error($errors, $path.".".$i, "codename de médaille obligatoire");
        else
            $out[] = $row;
    }
    return ($out);
}

function cycle_template_import_compile_supports($value, $path, array &$errors)
{
    $out = [];
    foreach (cycle_template_import_relation_entries($value) as $i => $entry)
    {
        $row = ["kind" => "support", "codename" => "", "chapter" => (int)$i];
        if (is_array($entry))
        {
            $entry = cycle_template_import_normalize($entry);
            cycle_template_import_unknown_keys($entry, ["type", "kind", "codename", "name", "chapter"], $path.".".$i, $errors);
            $kind = strtolower(trim((string)cycle_template_import_value($entry, ["type", "kind"], "support")));
            $aliases = [
                "support" => "support", "asset" => "support_asset", "support_asset" => "support_asset",
                "category" => "support_category", "support_category" => "support_category",
                "activity" => "activity", "subactivity" => "activity",
            ];
            if (!isset($aliases[$kind]))
                cycle_template_import_error($errors, $path.".".$i.".type", "type attendu: Support, Asset, Category ou Activity");
            else
                $row["kind"] = $aliases[$kind];
            $row["codename"] = trim((string)cycle_template_import_value($entry, ["codename", "name"], ""));
            $chapter = cycle_template_import_value($entry, "chapter", 0);
            $parsed = NULL;
            if (!cycle_template_import_validation_integer($chapter, $parsed) || $parsed < 0)
                cycle_template_import_error($errors, $path.".".$i.".chapter", "entier >= 0 attendu");
            else
                $row["chapter"] = $parsed;
        }
        else
            $row["codename"] = trim((string)$entry);
        if ($row["codename"] === "")
            cycle_template_import_error($errors, $path.".".$i, "codename de support obligatoire");
        else
            $out[] = $row;
    }
    return ($out);
}

function cycle_template_import_compile_scales($value, $type, $path, array &$errors)
{
    $out = [];
    foreach (cycle_template_import_relation_entries($value) as $i => $entry)
    {
        $row = ["codename" => "", "chapter" => (int)$i, "type" => (int)$type];
        if (is_array($entry))
        {
            $entry = cycle_template_import_normalize($entry);
            cycle_template_import_unknown_keys($entry, ["codename", "name", "chapter"], $path.".".$i, $errors);
            $row["codename"] = trim((string)cycle_template_import_value($entry, ["codename", "name"], ""));
            $chapter = cycle_template_import_value($entry, "chapter", 0);
            $parsed = NULL;
            if (!cycle_template_import_validation_integer($chapter, $parsed) || $parsed < 0)
                cycle_template_import_error($errors, $path.".".$i.".chapter", "entier >= 0 attendu");
            else
                $row["chapter"] = $parsed;
        }
        else
            $row["codename"] = trim((string)$entry);
        if ($row["codename"] === "")
            cycle_template_import_error($errors, $path.".".$i, "codename de barème obligatoire");
        else
            $out[] = $row;
    }
    return ($out);
}

function cycle_template_import_declaration_type_value($value)
{
    if ($value === NULL || $value === "")
        return (0);
    if (is_numeric($value))
        return ((int)$value);
    $value = strtolower(trim((string)$value));
    return ([
        "none" => 0, "disabled" => 0, "aucune" => 0,
        "local" => 1, "locale" => 1,
        "global" => 2, "remote" => 2, "anywhere" => 2,
    ][$value] ?? (int)$value);
}

function cycle_template_import_software_type($value, $path, array &$errors)
{
    if (is_int($value) || (is_string($value) && preg_match('/^[0-2]$/', trim($value))))
        return ((int)$value);
    $key = strtolower(trim((string)$value));
    $types = ["evaluator" => 0, "evaluation" => 0, "reference" => 1, "tools" => 2, "tool" => 2, "outils" => 2];
    if (!isset($types[$key]))
    {
        cycle_template_import_error($errors, $path, "type logiciel attendu: Evaluator, Reference ou Tools");
        return (0);
    }
    return ($types[$key]);
}

function cycle_template_import_compile_software($value, $path, array &$errors)
{
    $out = [];
    foreach (cycle_template_import_relation_entries($value) as $i => $entry)
    {
        $row = ["software" => "", "type" => 0];
        if (is_array($entry))
        {
            $entry = cycle_template_import_normalize($entry);
            cycle_template_import_unknown_keys($entry, ["software", "name", "type"], $path.".".$i, $errors);
            $row["software"] = trim((string)cycle_template_import_value($entry, ["software", "name"], ""));
            $row["type"] = cycle_template_import_software_type(cycle_template_import_value($entry, "type", 0), $path.".".$i.".type", $errors);
        }
        else
            $row["software"] = trim((string)$entry);
        if ($row["software"] === "")
            cycle_template_import_error($errors, $path.".".$i, "nom de logiciel/dépôt obligatoire");
        else
            $out[] = $row;
    }
    return ($out);
}

function cycle_template_import_compile_activity_extensions(array $raw, $path, array &$errors)
{
    $out = [];
    foreach ([
        "emergence_date", "registration_date", "close_date", "subject_appeir_date",
        "subject_disappeir_date", "pickup_date", "done_date"
    ] as $field)
    {
        if (array_key_exists($field, $raw))
            $out[$field] = cycle_template_import_relative_date($raw[$field], $path.".".$field, $errors);
    }

    $relations = [];
    if (array_key_exists("teachers", $raw))
        $relations["teachers"] = cycle_template_import_compile_teachers($raw["teachers"], "user", $path.".teachers", $errors);
    if (array_key_exists("laboratories", $raw))
        $relations["laboratories"] = cycle_template_import_compile_teachers($raw["laboratories"], "laboratory", $path.".laboratories", $errors);
    if (array_key_exists("skills", $raw))
        $relations["skills"] = cycle_template_import_compile_named_links($raw["skills"], $path.".skills", $errors);
    if (array_key_exists("medals", $raw))
        $relations["medals"] = cycle_template_import_compile_medals($raw["medals"], $path.".medals", $errors);
    if (array_key_exists("supports", $raw))
        $relations["supports"] = cycle_template_import_compile_supports($raw["supports"], $path.".supports", $errors);
    foreach (["scales" => 0, "mcqs" => 1, "satisfaction" => 2] as $field => $type)
        if (array_key_exists($field, $raw))
            $relations[$field] = cycle_template_import_compile_scales($raw[$field], $type, $path.".".$field, $errors);
    if (array_key_exists("software", $raw))
        $relations["software"] = cycle_template_import_compile_software($raw["software"], $path.".software", $errors);
    if ($relations)
        $out["_relations"] = $relations;
    return ($out);
}

function cycle_template_import_compile_cycle_relations(array $raw, $path, array &$errors)
{
    $relations = [];
    if (array_key_exists("teachers", $raw))
        $relations["teachers"] = cycle_template_import_compile_teachers($raw["teachers"], "user", $path.".teachers", $errors, false);
    if (array_key_exists("laboratories", $raw))
        $relations["laboratories"] = cycle_template_import_compile_teachers($raw["laboratories"], "laboratory", $path.".laboratories", $errors, false);
    return ($relations);
}
