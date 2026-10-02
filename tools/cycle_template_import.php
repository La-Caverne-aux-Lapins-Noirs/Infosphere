<?php

require_once (__DIR__."/cycle_template_import_fields.php");

/**
 * Cycle-template Dabsic import, phase 1: syntax and semantic validation.
 *
 * The file deliberately separates the calendar from the curriculum:
 * - Calendar describes activities and their relative sessions.
 * - Matters describes matter templates.
 *
 * Activity-to-matter membership is intentionally not represented by an extra
 * assignment table: an activity codename must start with its matter codename
 * followed by "-" (for example ALG-01A-TPE-000 belongs to ALG-01A).
 *
 * No database write is performed by this file.  The result of
 * cycle_template_import_compile() is a deterministic import plan that can be
 * checked for DB collisions and then committed transactionally by the next
 * stage of the importer.
 */

function cycle_template_import_normalize_key($key)
{
    if (is_int($key) || is_numeric($key))
        return ($key);
    $key = str_replace(["-", " "], "_", trim((string)$key));
    // Named Dabsic scopes are also used as local identifiers.  Keep acronyms
    // such as TP01 readable instead of turning them into t_p01.
    if (preg_match('/^[A-Z0-9_.]+$/', $key))
        $key = strtolower($key);
    else
    {
        if (strtolower($key) !== $key)
            $key = preg_replace('/(?<!^)[A-Z]/', '_$0', $key);
        $key = strtolower($key);
    }
    $key = preg_replace('/__+/', '_', $key);
    return (trim($key, "_"));
}

function cycle_template_import_normalize($data)
{
    if (!is_array($data))
        return ($data);
    $out = [];
    foreach ($data as $key => $value)
        $out[cycle_template_import_normalize_key($key)] =
            cycle_template_import_normalize($value);
    return ($out);
}

function cycle_template_import_scalar($value, $default = "")
{
    if ($value === NULL || is_array($value) || is_object($value))
        return ($default);
    return ($value);
}

function cycle_template_import_value(array $data, $keys, $default = NULL)
{
    if (!is_array($keys))
        $keys = [$keys];
    foreach ($keys as $key)
    {
        $key = cycle_template_import_normalize_key($key);
        if (array_key_exists($key, $data))
            return ($data[$key]);
    }
    return ($default);
}

function cycle_template_import_is_symbol($value)
{
    return (is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $value) === 1);
}

function cycle_template_import_error(array &$errors, $path, $message)
{
    $errors[] = ($path == "" ? "" : $path.": ").$message;
}

function cycle_template_import_unknown_keys(array $data, array $allowed, $path, array &$errors)
{
    $allowed = array_flip(array_map('cycle_template_import_normalize_key', $allowed));
    foreach ($data as $key => $unused)
    {
        if (is_int($key))
            continue ;
        $key = cycle_template_import_normalize_key($key);
        if (!isset($allowed[$key]))
            cycle_template_import_error($errors, $path.".".$key, "champ inconnu");
    }
}

function cycle_template_import_list($value)
{
    if ($value === NULL || $value === "")
        return ([]);
    if (!is_array($value))
        $value = preg_split('/\s*[;,]\s*/u', trim((string)$value));
    $out = [];
    foreach ($value as $key => $entry)
    {
        if (is_array($entry))
        {
            if (isset($entry["activity"]))
                $entry = $entry["activity"];
            else if (isset($entry["codename"]))
                $entry = $entry["codename"];
            else if (!is_int($key))
                $entry = $key;
            else
                continue ;
        }
        else if (!is_int($key) && ($entry === "" || $entry === NULL))
            $entry = $key;
        $entry = trim((string)$entry);
        if ($entry !== "")
            $out[] = $entry;
    }
    return ($out);
}

function cycle_template_import_day($value)
{
    if (is_int($value) || (is_string($value) && preg_match('/^[0-6]$/', trim($value))))
        return ((int)$value);
    $value = strtolower(trim((string)$value));
    $days = [
        "monday" => 0, "mon" => 0, "lundi" => 0, "lun" => 0,
        "tuesday" => 1, "tue" => 1, "mardi" => 1, "mar" => 1,
        "wednesday" => 2, "wed" => 2, "mercredi" => 2, "mer" => 2,
        "thursday" => 3, "thu" => 3, "jeudi" => 3, "jeu" => 3,
        "friday" => 4, "fri" => 4, "vendredi" => 4, "ven" => 4,
        "saturday" => 5, "sat" => 5, "samedi" => 5, "sam" => 5,
        "sunday" => 6, "sun" => 6, "dimanche" => 6, "dim" => 6,
    ];
    return ($days[$value] ?? NULL);
}

function cycle_template_import_clock($value)
{
    if (!is_string($value) && !is_int($value))
        return (NULL);
    $value = trim((string)$value);
    if (!preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?$/', $value, $m))
        return (NULL);
    return (((int)$m[1] * 3600) + ((int)$m[2] * 60) + (isset($m[3]) ? (int)$m[3] : 0));
}

function cycle_template_import_duration($value)
{
    if (is_int($value) || (is_string($value) && preg_match('/^[0-9]+$/', trim($value))))
        return ((int)$value * 60); // numeric durations are minutes
    if (!is_string($value))
        return (NULL);
    $value = trim($value);
    if (!preg_match('/^([0-9]+):([0-5][0-9])(?::([0-5][0-9]))?$/', $value, $m))
        return (NULL);
    return (((int)$m[1] * 3600) + ((int)$m[2] * 60) + (isset($m[3]) ? (int)$m[3] : 0));
}

function cycle_template_import_session(array $raw, $path, array &$errors, array $defaults = [])
{
    $raw = cycle_template_import_normalize($raw);
    cycle_template_import_unknown_keys($raw, [
        "week", "day", "begin", "start", "end", "duration",
        "maximum_subscription", "laboratory", "rooms", "appointment_slots", "slots"
    ], $path, $errors);
    cycle_template_import_validate_session_fields($raw, $path, $errors);

    $week = cycle_template_import_value($raw, "week", $defaults["week"] ?? NULL);
    $day = cycle_template_import_value($raw, "day", $defaults["day"] ?? NULL);
    $begin = cycle_template_import_value($raw, ["begin", "start"], NULL);
    $end = cycle_template_import_value($raw, "end", NULL);
    $duration = cycle_template_import_value($raw, "duration", NULL);

    if (!is_int($week) && !(is_string($week) && preg_match('/^[0-9]+$/', trim($week))))
    {
        cycle_template_import_error($errors, $path.".week", "doit être un entier >= 0 (0 = première semaine)");
        $week = 0;
    }
    $week = max(0, (int)$week);
    $day_index = cycle_template_import_day($day);
    if ($day_index === NULL)
    {
        cycle_template_import_error($errors, $path.".day", "jour invalide (0..6 ou lundi..dimanche)");
        $day_index = 0;
    }
    $begin_clock = cycle_template_import_clock($begin);
    if ($begin_clock === NULL)
    {
        cycle_template_import_error($errors, $path.".begin", "heure invalide, format attendu HH:MM");
        $begin_clock = 0;
    }
    $begin_offset = ($week * 7 + $day_index) * 86400 + $begin_clock;

    if ($end !== NULL && $end !== "")
    {
        $end_clock = cycle_template_import_clock($end);
        if ($end_clock === NULL)
        {
            cycle_template_import_error($errors, $path.".end", "heure invalide, format attendu HH:MM");
            $end_offset = $begin_offset;
        }
        else
            $end_offset = ($week * 7 + $day_index) * 86400 + $end_clock;
    }
    else
    {
        $seconds = cycle_template_import_duration($duration);
        if ($seconds === NULL || $seconds <= 0)
        {
            cycle_template_import_error($errors, $path, "il faut End ou une Duration positive");
            $seconds = 0;
        }
        $end_offset = $begin_offset + $seconds;
    }
    if ($end_offset <= $begin_offset)
        cycle_template_import_error($errors, $path, "la fin de session doit être après le début");

    $out = [
        "date" => ["begin" => $begin_offset, "end" => $end_offset],
        "_calendar" => ["week" => $week, "day" => $day_index],
    ];
    foreach (["maximum_subscription", "laboratory"] as $key)
    {
        $value = cycle_template_import_value($raw, $key, NULL);
        if ($value !== NULL && $value !== "")
            $out[$key] = cycle_template_import_scalar($value);
    }
    $rooms = cycle_template_import_list(cycle_template_import_value($raw, "rooms", []));
    if ($rooms)
        $out["rooms"] = $rooms;

    $slots = cycle_template_import_value($raw, ["appointment_slots", "slots"], []);
    if (is_array($slots) && $slots)
    {
        $out["appointment_slots"] = [];
        foreach ($slots as $slot_key => $slot)
        {
            if (!is_array($slot))
            {
                cycle_template_import_error($errors, $path.".appointment_slots.".$slot_key, "créneau invalide");
                continue ;
            }
            $compiled = cycle_template_import_session(
                $slot,
                $path.".appointment_slots.".$slot_key,
                $errors,
                ["week" => $week, "day" => $day_index]
            );
            if ($compiled["date"]["begin"] < $begin_offset || $compiled["date"]["end"] > $end_offset)
                cycle_template_import_error(
                    $errors,
                    $path.".appointment_slots.".$slot_key,
                    "le créneau doit être inclus dans la session parente"
                );
            $out["appointment_slots"][] = ["date" => $compiled["date"]];
        }
    }
    return ($out);
}

function cycle_template_import_copy_activity_fields(array $raw)
{
    $out = [];
    foreach ([
        "type", "min_team_size", "max_team_size", "hidden", "mandatory",
        "maximum_subscription", "money", "subscription", "repository_name",
        "estimated_work_duration", "automatic_correction_frequency", "slot_duration",
        "validation", "credit_a", "credit_b", "credit_c", "credit_d",
        "allow_unregistration", "validated", "template_link", "medal_template",
        "support_template", "grade_a", "grade_b", "grade_c", "grade_d",
        "grade_bonus", "declaration_type", "reference_activity",
        "progressive_slot_opening", "team_based_slot_opening", "todolist"
    ] as $key)
    {
        $value = cycle_template_import_value($raw, $key, NULL);
        if ($value !== NULL && $value !== "")
            $out[$key] = cycle_template_import_scalar($value);
    }
    foreach (["fr", "en"] as $lang)
    {
        $scope = cycle_template_import_value($raw, $lang, []);
        if (is_array($scope))
        {
            $tmp = [];
            foreach (["name", "description", "objective", "method", "reference"] as $field)
            {
                $value = cycle_template_import_value($scope, $field, NULL);
                if ($value !== NULL && !is_array($value))
                    $tmp[$field] = $value;
            }
            if ($tmp)
                $out[$lang] = $tmp;
        }
        foreach (["name", "description", "objective", "method", "reference"] as $field)
        {
            $value = cycle_template_import_value($raw, $lang."_".$field, NULL);
            if ($value !== NULL && !is_array($value))
                $out[$lang][$field] = $value;
        }
    }
    return ($out);
}

function cycle_template_import_calendar(array $raw, array &$errors)
{
    $calendar = [];
    foreach ($raw as $scope_name => $entry)
    {
        $path = "calendar.".$scope_name;
        if (!is_array($entry))
        {
            cycle_template_import_error($errors, $path, "une activité doit être un bloc");
            continue ;
        }
        $entry = cycle_template_import_normalize($entry);
        cycle_template_import_unknown_keys($entry, [
            "codename", "type", "min_team_size", "max_team_size", "hidden", "mandatory",
            "maximum_subscription", "money", "subscription", "repository_name",
            "estimated_work_duration", "automatic_correction_frequency", "slot_duration",
            "validation", "credit_a", "credit_b", "credit_c", "credit_d",
            "allow_unregistration", "validated", "template_link", "medal_template",
            "support_template", "grade_a", "grade_b", "grade_c", "grade_d",
            "grade_bonus", "declaration_type", "reference_activity",
            "progressive_slot_opening", "team_based_slot_opening", "todolist",
            "emergence_date", "registration_date", "close_date", "subject_appeir_date",
            "subject_disappeir_date", "pickup_date", "done_date",
            "teachers", "laboratories", "skills", "medals", "supports",
            "scales", "mcqs", "satisfaction", "software",
            "fr", "en", "fr_name", "fr_description", "fr_objective", "fr_method", "fr_reference",
            "en_name", "en_description", "en_objective", "en_method", "en_reference",
            "sessions"
        ], $path, $errors);
        cycle_template_import_validate_activity_fields($entry, $path, $errors);

        // Dabsic scope names are syntax labels only.  They must never carry
        // Infosphere identity: codenames may legally contain '-' while a
        // Dabsic scope name cannot.  References therefore always target this
        // explicit Codename field.
        $codename = trim((string)cycle_template_import_value($entry, "codename", ""));
        if ($codename === "")
            cycle_template_import_error($errors, $path.".codename", "codename obligatoire");
        else if (!cycle_template_import_is_symbol($codename))
            cycle_template_import_error($errors, $path.".codename", "codename invalide");
        else if (strlen($codename) > 255)
            cycle_template_import_error($errors, $path.".codename", "codename trop long (255 caractères maximum)");
        if ($codename !== "" && isset($calendar[$codename]))
            cycle_template_import_error($errors, $path.".codename", "codename de calendrier dupliqué: ".$codename);

        $type = trim((string)cycle_template_import_value($entry, "type", ""));
        if ($type === "")
            cycle_template_import_error($errors, $path.".type", "type d'activité obligatoire");
        else if (strcasecmp($type, "Module") === 0)
            cycle_template_import_error($errors, $path.".type", "Module est réservé aux matières");

        $compiled = cycle_template_import_copy_activity_fields($entry);
        $compiled = array_merge(
            $compiled,
            cycle_template_import_compile_activity_extensions($entry, $path, $errors)
        );
        $compiled["kind"] = "activity";
        $compiled["codename"] = $codename;
        // Sessions belong to this exact activity occurrence. Repetition is
        // represented by several activity entries, each with its own session,
        // not by one synthetic activity carrying every repeated session.
        $sessions = cycle_template_import_value($entry, "sessions", []);
        $compiled["sessions"] = [];
        if ($sessions !== NULL && !is_array($sessions))
            cycle_template_import_error($errors, $path.".sessions", "doit être un ensemble de blocs");
        else if (is_array($sessions))
        {
            foreach ($sessions as $session_id => $session)
            {
                if (!is_array($session))
                {
                    cycle_template_import_error($errors, $path.".sessions.".$session_id, "session invalide");
                    continue ;
                }
                $compiled["sessions"][] = cycle_template_import_session(
                    $session, $path.".sessions.".$session_id, $errors
                );
            }
        }
        if ($codename !== "" && !isset($calendar[$codename]))
            $calendar[$codename] = $compiled;
    }
    return ($calendar);
}

function cycle_template_import_matters(array $raw, array $calendar, array &$errors)
{
    $matters = [];
    foreach ($raw as $scope_name => $entry)
    {
        $path = "matters.".$scope_name;
        if (!is_array($entry))
        {
            cycle_template_import_error($errors, $path, "une matière doit être un bloc");
            continue ;
        }
        $entry = cycle_template_import_normalize($entry);
        cycle_template_import_unknown_keys($entry, [
            "codename", "type", "min_team_size", "max_team_size", "hidden",
            "mandatory", "maximum_subscription", "money", "subscription", "repository_name",
            "estimated_work_duration", "automatic_correction_frequency", "slot_duration",
            "validation", "credit_a", "credit_b", "credit_c", "credit_d", "allow_unregistration",
            "validated", "template_link", "medal_template", "support_template",
            "grade_a", "grade_b", "grade_c", "grade_d", "grade_bonus",
            "declaration_type", "reference_activity", "progressive_slot_opening",
            "team_based_slot_opening", "todolist", "emergence_date", "registration_date",
            "close_date", "subject_appeir_date", "subject_disappeir_date", "pickup_date", "done_date",
            "teachers", "laboratories", "skills", "medals", "supports",
            "scales", "mcqs", "satisfaction", "software",
            "fr", "en", "fr_name", "fr_description", "fr_objective",
            "fr_method", "fr_reference", "en_name", "en_description", "en_objective", "en_method",
            "en_reference", "week_shift", "cursus", "replacement_subscription"
        ], $path, $errors);
        cycle_template_import_validate_activity_fields($entry, $path, $errors);
        cycle_template_import_validate_cycle_link_fields($entry, $path, $errors);

        $codename = trim((string)cycle_template_import_value($entry, "codename", ""));
        if ($codename === "")
            cycle_template_import_error($errors, $path.".codename", "codename obligatoire");
        else if (!cycle_template_import_is_symbol($codename))
            cycle_template_import_error($errors, $path.".codename", "codename invalide");
        else if (strlen($codename) > 255)
            cycle_template_import_error($errors, $path.".codename", "codename trop long (255 caractères maximum)");
        if ($codename !== "" && isset($matters[$codename]))
            cycle_template_import_error($errors, $path.".codename", "codename de matière dupliqué: ".$codename);

        $declared_type = trim((string)cycle_template_import_value($entry, "type", "Module"));
        if ($declared_type !== "" && strcasecmp($declared_type, "Module") !== 0)
            cycle_template_import_error($errors, $path.".type", "une matière doit être de type Module");

        $compiled = cycle_template_import_copy_activity_fields($entry);
        $compiled = array_merge(
            $compiled,
            cycle_template_import_compile_activity_extensions($entry, $path, $errors)
        );
        $compiled["kind"] = "matter";
        $compiled["codename"] = $codename;
        $compiled["type"] = "Module";
        $compiled["_cycle_link"] = [];
        foreach (["week_shift", "cursus", "replacement_subscription"] as $key)
        {
            $value = cycle_template_import_value($entry, $key, NULL);
            if ($value !== NULL && $value !== "")
                $compiled["_cycle_link"][$key] = cycle_template_import_scalar($value);
        }
        $compiled["activities"] = [];
        if ($codename !== "" && !isset($matters[$codename]))
            $matters[$codename] = $compiled;
    }

    // The matter is part of the activity identity itself.  Use the longest
    // matching matter codename so that a specific matter such as ALG-01A wins
    // over a broader prefix such as ALG when both exist.
    foreach ($calendar as $activity_codename => $calendar_activity)
    {
        $matches = [];
        foreach ($matters as $matter_codename => $matter)
            if (strncmp($activity_codename, $matter_codename."-", strlen($matter_codename) + 1) === 0)
                $matches[] = $matter_codename;
        if (!$matches)
        {
            cycle_template_import_error(
                $errors,
                "calendar.".$activity_codename.".codename",
                "aucune matière ne correspond au préfixe du codename ".$activity_codename
            );
            continue ;
        }
        usort($matches, function ($a, $b) {
            $length = strlen($b) <=> strlen($a);
            return ($length !== 0 ? $length : strcmp($a, $b));
        });
        $matter_codename = $matches[0];
        $activity = $calendar_activity;
        $activity["calendar_codename"] = $activity_codename;
        // Calendar codenames are already real Infosphere codenames.  Do not
        // generate an additional namespace or punctuation here.
        $activity["codename"] = $activity_codename;
        $matters[$matter_codename]["activities"][] = $activity;
    }

    // A matter is allowed to be empty in a cycle template.  The codename rule
    // is intentionally one-way: every activity must resolve to a matter, but a
    // declared matter does not have to own an activity yet.  This makes it
    // possible to prepare a complete curriculum before every activity has been
    // authored.
    return ($matters);
}

function cycle_template_import_cycle(array $raw, array &$errors)
{
    $raw = cycle_template_import_normalize($raw);
    cycle_template_import_unknown_keys($raw, [
        "codename", "cycle", "number", "objective", "fr", "en", "fr_name", "en_name",
        "teachers", "laboratories"
    ], "cycle", $errors);
    cycle_template_import_validate_cycle_fields($raw, "cycle", $errors);
    $codename = trim((string)cycle_template_import_value($raw, "codename", ""));
    if ($codename === "")
        cycle_template_import_error($errors, "cycle.codename", "codename obligatoire");
    else if (!cycle_template_import_is_symbol($codename))
        cycle_template_import_error($errors, "cycle.codename", "codename invalide");
    else if (strlen($codename) > 64)
        cycle_template_import_error($errors, "cycle.codename", "codename trop long (64 caractères maximum)");
    $number = cycle_template_import_value($raw, ["cycle", "number"], NULL);
    if (!is_int($number) && !(is_string($number) && preg_match('/^[0-9]+$/', trim($number))))
    {
        cycle_template_import_error($errors, "cycle.cycle", "numéro de cycle entier obligatoire");
        $number = 0;
    }
    $number = (int)$number;
    if ($number < 0 || $number > 20)
        cycle_template_import_error($errors, "cycle.cycle", "doit être compris entre 0 et 20");

    $out = ["codename" => $codename, "cycle" => $number, "is_template" => 1];
    $objective = cycle_template_import_value($raw, "objective", NULL);
    if ($objective !== NULL && $objective !== "")
    {
        if (!is_int($objective) && !(is_string($objective) && preg_match('/^[0-9]+$/', trim($objective))))
            cycle_template_import_error($errors, "cycle.objective", "doit être un entier >= 0");
        else
            $out["objective"] = (int)$objective;
    }
    foreach (["fr", "en"] as $lang)
    {
        $scope = cycle_template_import_value($raw, $lang, []);
        if (is_array($scope) && isset($scope["name"]))
            $out[$lang."_name"] = cycle_template_import_scalar($scope["name"]);
        $name = cycle_template_import_value($raw, $lang."_name", NULL);
        if ($name !== NULL && !is_array($name))
            $out[$lang."_name"] = $name;
    }
    $relations = cycle_template_import_compile_cycle_relations($raw, "cycle", $errors);
    if ($relations)
        $out["_relations"] = $relations;
    return ($out);
}

function cycle_template_import_sort_recursive($value)
{
    if (!is_array($value))
        return ($value);
    $is_list = array_keys($value) === range(0, count($value) - 1);
    foreach ($value as &$entry)
        $entry = cycle_template_import_sort_recursive($entry);
    unset($entry);
    if ($is_list)
    {
        usort($value, function ($a, $b) {
            return strcmp(
                json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        });
    }
    else
        ksort($value, SORT_STRING);
    return ($value);
}

function cycle_template_import_signature(array $plan)
{
    $copy = $plan;
    unset($copy["signature"]);
    $copy = cycle_template_import_sort_recursive($copy);
    return (hash('sha256', json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
}

function cycle_template_import_compile(array $raw)
{
    $raw = cycle_template_import_normalize($raw);
    $errors = [];
    cycle_template_import_unknown_keys($raw, [
        "format", "version", "cycle", "calendar", "matters", "subjects", "curriculum"
    ], "root", $errors);

    $format = strtolower(trim((string)cycle_template_import_value($raw, "format", "cycle_template")));
    if (!in_array($format, ["cycle_template", "cycle-template", "cycletemplate"], true))
        cycle_template_import_error($errors, "format", "format attendu: CycleTemplate");
    $version = cycle_template_import_value($raw, "version", 1);
    if ((int)$version !== 1)
        cycle_template_import_error($errors, "version", "version de format non supportée");

    $cycle_raw = cycle_template_import_value($raw, "cycle", []);
    $calendar_present = array_key_exists("calendar", $raw);
    $calendar_raw = cycle_template_import_value($raw, "calendar", []);
    $matters_raw = cycle_template_import_value($raw, ["matters", "subjects", "curriculum"], []);
    if (!is_array($cycle_raw))
    {
        cycle_template_import_error($errors, "cycle", "bloc Cycle obligatoire");
        $cycle_raw = [];
    }
    if (!$calendar_present || !is_array($calendar_raw))
    {
        cycle_template_import_error($errors, "calendar", "bloc Calendar obligatoire");
        $calendar_raw = [];
    }
    if (!is_array($matters_raw) || !$matters_raw)
    {
        cycle_template_import_error($errors, "matters", "bloc Matters non vide obligatoire");
        $matters_raw = [];
    }

    $cycle = cycle_template_import_cycle($cycle_raw, $errors);
    $calendar = cycle_template_import_calendar($calendar_raw, $errors);
    $matters = cycle_template_import_matters($matters_raw, $calendar, $errors);

    // Database codenames must also be unique inside the file itself.
    $db_codenames = [];
    foreach ($matters as $matter_id => $matter)
    {
        $all = [[$matter["codename"], "matters.".$matter_id]];
        foreach ($matter["activities"] as $activity)
            $all[] = [$activity["codename"], "calendar.".$activity["calendar_codename"]];
        foreach ($all as [$codename, $path])
        {
            if ($codename === "")
                continue ;
            if (isset($db_codenames[$codename]))
                cycle_template_import_error($errors, $path, "collision interne de codename avec ".$db_codenames[$codename]);
            else
                $db_codenames[$codename] = $path;
        }
    }

    $plan = [
        "format" => "cycle_template",
        "version" => 1,
        "cycle" => $cycle,
        "calendar" => $calendar,
        "matters" => $matters,
    ];
    $plan["signature"] = cycle_template_import_signature($plan);
    return (["ok" => count($errors) === 0, "errors" => $errors, "plan" => $plan]);
}
