<?php

/*
 * Internship -> session desired-state planner (stage 4A).
 *
 * This file deliberately performs no INSERT/UPDATE/DELETE. It only transforms
 * the authoritative Dabsic tree Internship.ScheduleCalendar into the exact
 * standalone sessions that stage 4B will later materialize and synchronize.
 */

function internship_session_plan_property($name, $fallback)
{
    global $Configuration;

    if (isset($Configuration) && isset($Configuration->Properties) &&
        array_key_exists($name, $Configuration->Properties) &&
        trim((string)$Configuration->Properties[$name]) !== "")
        return (trim((string)$Configuration->Properties[$name]));
    return ((string)$fallback);
}

function internship_session_plan_time($value, $fallback)
{
    $value = trim((string)$value);
    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value))
        $value = $fallback;
    return ($value);
}

function internship_session_plan_settings(array $options = [])
{
    $get = function ($option, $property, $fallback) use ($options) {
        if (isset($options[$option]) && trim((string)$options[$option]) !== "")
            return (trim((string)$options[$option]));
        return (internship_session_plan_property($property, $fallback));
    };

    $settings = [
        "name" => $get("name", "internship_session_name", "Stage en entreprise"),
        "morning_start" => internship_session_plan_time($get("morning_start", "internship_morning_start", "09:00"), "09:00"),
        "morning_end" => internship_session_plan_time($get("morning_end", "internship_morning_end", "12:30"), "12:30"),
        "afternoon_start" => internship_session_plan_time($get("afternoon_start", "internship_afternoon_start", "13:30"), "13:30"),
        "afternoon_end" => internship_session_plan_time($get("afternoon_end", "internship_afternoon_end", "17:00"), "17:00"),
    ];
    if ($settings["name"] === "")
        $settings["name"] = "Stage en entreprise";
    if (strcmp($settings["morning_start"], $settings["morning_end"]) >= 0 ||
        strcmp($settings["afternoon_start"], $settings["afternoon_end"]) >= 0)
        return (NULL);
    return ($settings);
}

function internship_session_plan_calendar(array $configuration)
{
    $internship = $configuration["Internship"] ?? NULL;
    if (!is_array($internship))
        return (NULL);
    $calendar = $internship["ScheduleCalendar"] ?? NULL;
    if (!is_array($calendar))
        return (NULL);
    return ($calendar);
}

function internship_session_plan_date_from_node($key)
{
    if (!preg_match('/^D(\d{4})(\d{2})(\d{2})$/D', (string)$key, $m))
        return (NULL);
    return (internship_calendar_parse_date($m[1]."-".$m[2]."-".$m[3]));
}

function internship_session_source_key($date, $half)
{
    $date = internship_calendar_parse_date($date);
    $half = strtolower(trim((string)$half));
    if ($date === NULL || !in_array($half, ["morning", "afternoon"], true))
        return (NULL);
    return ("internship:".$date.":".$half);
}

function internship_session_plan(array $configuration, $id_user, $source_id = "", array $options = [])
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "id_user"]);

    $calendar = internship_session_plan_calendar($configuration);
    if ($calendar === NULL)
        return (["ok" => false, "error" => "MissingField", "details" => "Internship.ScheduleCalendar"]);
    if (isset($calendar["Format"]) && trim((string)$calendar["Format"]) !== "" &&
        strcasecmp(trim((string)$calendar["Format"]), "HalfDayV1") !== 0)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "Internship.ScheduleCalendar.Format"]);

    // The contractual internship dates are authoritative.  Calendar.StartDate
    // and Calendar.EndDate are a denormalized rendering/editor convenience and
    // may temporarily be stale after an administrative correction.  Never let
    // such stale metadata keep sessions alive outside Internship.StartDate /
    // Internship.EndDate.
    $has_internship_start = array_key_exists("StartDate", $configuration["Internship"]);
    $has_internship_end = array_key_exists("EndDate", $configuration["Internship"]);
    $internship_start = internship_calendar_parse_date($configuration["Internship"]["StartDate"] ?? "");
    $internship_end = internship_calendar_parse_date($configuration["Internship"]["EndDate"] ?? "");
    $calendar_start = internship_calendar_parse_date($calendar["StartDate"] ?? "");
    $calendar_end = internship_calendar_parse_date($calendar["EndDate"] ?? "");
    $start = $has_internship_start ? $internship_start : $calendar_start;
    $end = $has_internship_end ? $internship_end : $calendar_end;
    if ($start === NULL || $end === NULL || strcmp($start, $end) > 0)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "Internship date range"]);

    $settings = internship_session_plan_settings($options);
    if ($settings === NULL)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "internship half-day hours"]);

    $source_id = trim((string)$source_id);
    if ($source_id !== "" && !preg_match('/^[A-Za-z0-9_.:-]{1,96}$/D', $source_id))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "source_id"]);

    $sessions = [];
    $days = $calendar["Days"] ?? [];
    if (!is_array($days))
        $days = [];
    foreach ($days as $day_key => $day)
    {
        if (!is_array($day) || ($date = internship_session_plan_date_from_node($day_key)) === NULL)
            continue ;
        if (strcmp($date, $start) < 0 || strcmp($date, $end) > 0)
            continue ;
        foreach ([
            "Morning" => ["morning", $settings["morning_start"], $settings["morning_end"]],
            "Afternoon" => ["afternoon", $settings["afternoon_start"], $settings["afternoon_end"]],
        ] as $field => $half)
        {
            if (strtoupper(trim((string)($day[$field] ?? ""))) !== "T")
                continue ;
            [$half_name, $begin, $finish] = $half;
            $key = internship_session_source_key($date, $half_name);
            $session = [
                "id_activity" => -1,
                "id_laboratory" => -1,
                "id_team" => -1,
                "id_user" => $id_user,
                "name" => $settings["name"],
                "begin_date" => $date." ".$begin.":00",
                "end_date" => $date." ".$finish.":00",
                "source_type" => $source_id === "" ? NULL : "document",
                "source_id" => $source_id === "" ? NULL : $source_id,
                "source_key" => $source_id === "" ? NULL : $key,
                "calendar_date" => $date,
                "half_day" => $half_name,
                "desired_key" => $key,
            ];
            $sessions[$key] = $session;
        }
    }
    ksort($sessions, SORT_STRING);
    return ([
        "ok" => true,
        "source_type" => $source_id === "" ? NULL : "document",
        "source_id" => $source_id === "" ? NULL : $source_id,
        "student_id" => $id_user,
        "start" => $start,
        "end" => $end,
        "settings" => $settings,
        "sessions" => $sessions,
    ]);
}

function internship_session_plan_from_file($file, $id_user, $source_id = "", array $options = [])
{
    if (!is_string($file) || !is_file($file) || !is_readable($file))
        return (["ok" => false, "error" => "MissingFile", "details" => (string)$file]);
    $loaded = load_configuration($file, [], false);
    if ($loaded->is_error() || !is_array($loaded->value))
        return (["ok" => false, "error" => "InvalidFile", "details" => (string)$file]);
    return (internship_session_plan($loaded->value, $id_user, $source_id, $options));
}


/*
 * Finalization-time structural validation.
 *
 * Synchronization is deliberately tolerant of stale denormalized calendar
 * metadata: Internship.StartDate/EndDate are authoritative and out-of-range
 * Days nodes are ignored.  Finalization is stricter so a signed convention
 * cannot freeze incoherent metadata or malformed half-day values.
 */
function internship_session_validate_configuration(array $configuration, $id_user = 0, $source_id = "", array $options = [])
{
    $calendar = internship_session_plan_calendar($configuration);
    if ($calendar === NULL)
        return (["ok" => true, "applicable" => false, "errors" => [], "warnings" => [], "counts" => ["T" => 0, "E" => 0, "F" => 0]]);

    $errors = [];
    $warnings = [];
    $counts = ["T" => 0, "E" => 0, "F" => 0];
    $internship = $configuration["Internship"] ?? [];
    $start = internship_calendar_parse_date($internship["StartDate"] ?? "");
    $end = internship_calendar_parse_date($internship["EndDate"] ?? "");
    $calendar_start = internship_calendar_parse_date($calendar["StartDate"] ?? "");
    $calendar_end = internship_calendar_parse_date($calendar["EndDate"] ?? "");

    if ($start === NULL)
        $errors[] = "Internship.StartDate est absente ou invalide";
    if ($end === NULL)
        $errors[] = "Internship.EndDate est absente ou invalide";
    if ($start !== NULL && $end !== NULL && strcmp($start, $end) > 0)
        $errors[] = "la date de début du stage est postérieure à sa date de fin";
    if ($start !== NULL && $end !== NULL)
    {
        $span = internship_calendar_days_between($start, $end);
        if ($span === NULL || $span > 1461)
            $errors[] = "la période de stage dépasse quatre ans";
    }

    if (isset($calendar["Format"]) && trim((string)$calendar["Format"]) !== "" &&
        strcasecmp(trim((string)$calendar["Format"]), "HalfDayV1") !== 0)
        $errors[] = "Internship.ScheduleCalendar.Format doit être HalfDayV1";
    if ($calendar_start === NULL || $calendar_end === NULL)
        $errors[] = "les bornes du calendrier détaillé sont absentes ou invalides";
    if ($start !== NULL && $calendar_start !== NULL && $start !== $calendar_start)
        $errors[] = "la date de début du calendrier ne correspond pas à Internship.StartDate";
    if ($end !== NULL && $calendar_end !== NULL && $end !== $calendar_end)
        $errors[] = "la date de fin du calendrier ne correspond pas à Internship.EndDate";

    $days = $calendar["Days"] ?? [];
    if (!is_array($days))
    {
        $errors[] = "Internship.ScheduleCalendar.Days doit être un scope Dabsic";
        $days = [];
    }
    foreach ($days as $day_key => $day)
    {
        $date = internship_session_plan_date_from_node($day_key);
        if ($date === NULL || !is_array($day))
        {
            $errors[] = "entrée de calendrier invalide : ".(string)$day_key;
            continue ;
        }
        if ($start !== NULL && $end !== NULL &&
            (strcmp($date, $start) < 0 || strcmp($date, $end) > 0))
        {
            $warnings[] = "jour hors période ignoré : ".$date;
            continue ;
        }
        foreach (["Morning" => "matin", "Afternoon" => "après-midi"] as $field => $label)
        {
            $value = strtoupper(trim((string)($day[$field] ?? "")));
            if ($value === "")
                continue ;
            if (!in_array($value, ["T", "E", "F"], true))
            {
                $errors[] = $date." ".$label." contient une valeur invalide (".$value.")";
                continue ;
            }
            if ($value === "F" && internship_calendar_holiday_name($date) === NULL)
            {
                $errors[] = $date." ".$label." est marqué F alors que ce jour n'est pas férié";
                continue ;
            }
            ++$counts[$value];
        }
    }

    if ($counts["T"] <= 0)
        $errors[] = "le calendrier ne contient aucune demi-journée en entreprise (T)";

    // Exercise the exact planner as part of validation so invalid configured
    // half-day hours are caught before the document is frozen.
    if (!count($errors))
    {
        $plan = internship_session_plan($configuration, max(1, (int)$id_user), $source_id, $options);
        if (!$plan["ok"])
            $errors[] = (string)($plan["details"] ?? "calendrier de stage invalide");
    }

    return ([
        "ok" => !count($errors),
        "applicable" => true,
        "errors" => array_values(array_unique($errors)),
        "warnings" => array_values(array_unique($warnings)),
        "counts" => $counts,
        "start" => $start,
        "end" => $end,
    ]);
}
