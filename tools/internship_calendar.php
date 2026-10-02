<?php

/*
** Internship half-day calendar
** ----------------------------
** The browser editor transports one compact JSON string because ordinary HTML
** controls need one value.  The authoritative document data is never JSON:
** dabsic_form_save() expands this virtual field into a regular nested Dabsic
** tree under <field>.StartDate / EndDate / Days.DYYYYMMDD.{Morning,Afternoon}.
*/

function internship_calendar_h($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}

function internship_calendar_parse_date($value)
{
    $value = trim((string)$value);
    if ($value === "")
        return (NULL);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m))
    {
        $year = (int)$m[1];
        $month = (int)$m[2];
        $day = (int)$m[3];
    }
    else if (preg_match('/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{4})$/D', $value, $m))
    {
        $day = (int)$m[1];
        $month = (int)$m[2];
        $year = (int)$m[3];
    }
    else
        return (NULL);
    if (!checkdate($month, $day, $year))
        return (NULL);
    return (sprintf('%04d-%02d-%02d', $year, $month, $day));
}

function internship_calendar_date_fr($iso)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', (string)$iso, $m))
        return ((string)$iso);
    return ($m[3]."/".$m[2]."/".$m[1]);
}

function internship_calendar_iso_add_days($iso, $days)
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$iso, new DateTimeZone('UTC'));
    if (!$date)
        return (NULL);
    return ($date->modify(((int)$days >= 0 ? '+' : '').(int)$days.' day')->format('Y-m-d'));
}

function internship_calendar_days_between($start, $end)
{
    $a = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$start, new DateTimeZone('UTC'));
    $b = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$end, new DateTimeZone('UTC'));
    if (!$a || !$b)
        return (NULL);
    return ((int)$a->diff($b)->format('%r%a'));
}

/* Anonymous Gregorian computus, valid well beyond the dates useful here. */
function internship_calendar_easter_sunday($year)
{
    $year = (int)$year;
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    return (sprintf('%04d-%02d-%02d', $year, $month, $day));
}

function internship_calendar_french_holidays($year)
{
    $year = (int)$year;
    $easter = internship_calendar_easter_sunday($year);
    $holidays = [
        sprintf('%04d-01-01', $year) => "Jour de l’An",
        sprintf('%04d-05-01', $year) => "Fête du Travail",
        sprintf('%04d-05-08', $year) => "Victoire 1945",
        sprintf('%04d-07-14', $year) => "Fête nationale",
        sprintf('%04d-08-15', $year) => "Assomption",
        sprintf('%04d-11-01', $year) => "Toussaint",
        sprintf('%04d-11-11', $year) => "Armistice 1918",
        sprintf('%04d-12-25', $year) => "Noël",
    ];
    $holidays[internship_calendar_iso_add_days($easter, 1)] = "Lundi de Pâques";
    $holidays[internship_calendar_iso_add_days($easter, 39)] = "Ascension";
    $holidays[internship_calendar_iso_add_days($easter, 50)] = "Lundi de Pentecôte";
    ksort($holidays, SORT_STRING);
    return ($holidays);
}

function internship_calendar_holiday_name($iso)
{
    if (!preg_match('/^(\d{4})-/', (string)$iso, $m))
        return (NULL);
    $holidays = internship_calendar_french_holidays((int)$m[1]);
    return ($holidays[$iso] ?? NULL);
}

function internship_calendar_transport_decode($raw)
{
    if (is_array($raw))
        $data = $raw;
    else
    {
        $raw = trim((string)$raw);
        if ($raw === "")
            return (["start" => "", "end" => "", "days" => []]);
        if (strlen($raw) > 262144)
            return (NULL);
        $data = json_decode($raw, true);
    }
    if (!is_array($data))
        return (NULL);

    $days = [];
    foreach (($data["days"] ?? []) as $date => $halves)
    {
        $iso = internship_calendar_parse_date($date);
        if ($iso === NULL || !is_array($halves))
            continue ;
        $am = strtoupper(trim((string)($halves["am"] ?? $halves["Morning"] ?? "")));
        $pm = strtoupper(trim((string)($halves["pm"] ?? $halves["Afternoon"] ?? "")));
        if (!in_array($am, ["", "T", "E", "F"], true))
            $am = "";
        if (!in_array($pm, ["", "T", "E", "F"], true))
            $pm = "";
        if ($am !== "" || $pm !== "")
            $days[$iso] = ["am" => $am, "pm" => $pm];
    }
    ksort($days, SORT_STRING);
    return ([
        "start" => internship_calendar_parse_date($data["start"] ?? "") ?? "",
        "end" => internship_calendar_parse_date($data["end"] ?? "") ?? "",
        "days" => $days,
    ]);
}

function internship_calendar_transport_encode(array $payload)
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return ($json === false ? '{"start":"","end":"","days":{}}' : $json);
}

function internship_calendar_payload_has_work($raw)
{
    $payload = internship_calendar_transport_decode($raw);
    if ($payload === NULL)
        return (false);
    foreach ($payload["days"] as $halves)
        if (in_array($halves["am"] ?? "", ["T", "E"], true) ||
            in_array($halves["pm"] ?? "", ["T", "E"], true))
            return (true);
    return (false);
}

function internship_calendar_hours_between_times($start, $end, $fallback = 3.5)
{
    $start = trim((string)$start);
    $end = trim((string)$end);
    if (!preg_match('/^(\d{2}):(\d{2})$/D', $start, $a) ||
        !preg_match('/^(\d{2}):(\d{2})$/D', $end, $b))
        return ((float)$fallback);
    $begin = (int)$a[1] * 60 + (int)$a[2];
    $finish = (int)$b[1] * 60 + (int)$b[2];
    if ($finish <= $begin)
        return ((float)$fallback);
    return (($finish - $begin) / 60.0);
}

function internship_calendar_mode_high(array $values)
{
    if (!count($values))
        return (0);
    $counts = [];
    $original = [];
    foreach ($values as $value)
    {
        $number = (float)$value;
        $key = number_format($number, 6, '.', '');
        $counts[$key] = ($counts[$key] ?? 0) + 1;
        $original[$key] = $number;
    }
    $best = NULL;
    $best_count = -1;
    foreach ($counts as $key => $count)
        if ($count > $best_count || ($count == $best_count && ($best === NULL || $original[$key] > $best)))
        {
            $best = $original[$key];
            $best_count = $count;
        }
    return ((float)($best ?? 0));
}

/**
 * Derive contractual attendance figures from the authoritative half-day
 * calendar.  T is the only status that represents presence in the host
 * organization; E and F never contribute to internship duration/gratification.
 */
function internship_calendar_work_metrics($raw, $morning_hours = 3.5, $afternoon_hours = 3.5)
{
    $payload = internship_calendar_transport_decode($raw);
    if ($payload === NULL)
        return (NULL);
    $morning_hours = max(0, (float)$morning_hours);
    $afternoon_hours = max(0, (float)$afternoon_hours);
    $daily = [];
    $daily_days = [];
    $weekly_days = [];
    $monthly_hours = [];

    foreach ($payload["days"] as $date => $halves)
    {
        $hours = 0.0;
        $half_days = 0;
        if (strtoupper(trim((string)($halves["am"] ?? ""))) === "T")
        {
            $hours += $morning_hours;
            $half_days++;
        }
        if (strtoupper(trim((string)($halves["pm"] ?? ""))) === "T")
        {
            $hours += $afternoon_hours;
            $half_days++;
        }
        if ($half_days <= 0)
            continue ;
        $day_equivalent = $half_days / 2.0;
        $daily[$date] = $hours;
        $daily_days[$date] = $day_equivalent;
        $month = substr((string)$date, 0, 7);
        $monthly_hours[$month] = ($monthly_hours[$month] ?? 0) + $hours;
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$date, new DateTimeZone('UTC'));
        $week = $day ? $day->format('o-W') : substr((string)$date, 0, 7);
        $weekly_days[$week] = ($weekly_days[$week] ?? 0) + $day_equivalent;
    }
    ksort($daily, SORT_STRING);
    ksort($daily_days, SORT_STRING);
    ksort($monthly_hours, SORT_STRING);
    $hours = array_sum($daily);
    return ([
        // A T half-day is 0.5 contractual day. This is deliberately not the
        // number of distinct calendar dates: e.g. two full days plus one
        // half-day is 2.5 days, not 3.
        "days" => (float)array_sum($daily_days),
        "hours" => (float)$hours,
        // These two fields describe the usual rhythm, not an arithmetic
        // average distorted by a partial first/last week or isolated half-day.
        "hours_per_day" => internship_calendar_mode_high(array_values($daily)),
        "days_per_week" => internship_calendar_mode_high(array_values($weekly_days)),
        "monthly_hours" => $monthly_hours,
        "daily_hours" => $daily,
        "daily_days" => $daily_days,
    ]);
}

function internship_calendar_decimal_value($value)
{
    $value = trim((string)$value);
    if ($value === "")
        return (NULL);
    $value = str_replace(["\xc2\xa0", " ", "€"], "", $value);
    $value = str_replace(",", ".", $value);
    if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/D', $value))
        return (NULL);
    return ((float)$value);
}

function internship_calendar_format_number($value, $decimals = 2, $trim = true)
{
    $out = number_format((float)$value, (int)$decimals, ',', ' ');
    if ($trim && strpos($out, ',') !== false)
        $out = rtrim(rtrim($out, '0'), ',');
    return ($out);
}

function internship_calendar_payment_prefix()
{
    return ("Versement mensuel prévisionnel selon les heures planifiées : ");
}

function internship_calendar_french_month($month)
{
    if (!preg_match('/^(\d{4})-(\d{2})$/D', (string)$month, $m))
        return ((string)$month);
    $names = [1 => "janvier", "février", "mars", "avril", "mai", "juin",
        "juillet", "août", "septembre", "octobre", "novembre", "décembre"];
    $number = (int)$m[2];
    return (($names[$number] ?? $m[2])." ".$m[1]);
}

function internship_calendar_payment_schedule(array $metrics, $hourly_rate)
{
    $hourly_rate = internship_calendar_decimal_value($hourly_rate);
    if ($hourly_rate === NULL || $hourly_rate <= 0 || !count($metrics["monthly_hours"] ?? []))
        return ("");
    $parts = [];
    foreach ($metrics["monthly_hours"] as $month => $hours)
        $parts[] = internship_calendar_french_month($month)." : ".
            internship_calendar_format_number($hours, 2, true)." h, soit ".
            internship_calendar_format_number($hours * $hourly_rate, 2, false)." €";
    return (internship_calendar_payment_prefix().implode(" ; ", $parts).".");
}

function internship_calendar_summary_from_payload($raw)
{
    $payload = internship_calendar_transport_decode($raw);
    if ($payload === NULL)
        return ("");
    $counts = ["T" => 0, "E" => 0, "F" => 0];
    foreach ($payload["days"] as $halves)
        foreach (["am", "pm"] as $half)
            if (isset($counts[$halves[$half] ?? ""]))
                ++$counts[$halves[$half]];
    if ($payload["start"] === "" || $payload["end"] === "")
        return ("");
    return (
        "Calendrier détaillé du ".internship_calendar_date_fr($payload["start"]).
        " au ".internship_calendar_date_fr($payload["end"]).
        ". T = entreprise, E = école, F = jour férié. ".
        $counts["T"]." demi-journée(s) en entreprise, ".
        $counts["E"]." demi-journée(s) à l’école et ".
        $counts["F"]." demi-journée(s) fériée(s)."
    );
}

function internship_calendar_field_metadata(array $definition)
{
    $start = trim((string)($definition["start_field"] ?? ""));
    $end = trim((string)($definition["end_field"] ?? ""));
    $summary = trim((string)($definition["summary_field"] ?? ""));
    return ([
        "start_field" => $start !== "" ? $start : "Internship.StartDate",
        "end_field" => $end !== "" ? $end : "Internship.EndDate",
        "summary_field" => $summary !== "" ? $summary : "Internship.Schedule",
    ]);
}

function internship_calendar_payload_from_flat($field, array $definition, array $values)
{
    $meta = internship_calendar_field_metadata($definition);
    $start = internship_calendar_parse_date($values[$field.".StartDate"] ?? ($values[$meta["start_field"]] ?? "")) ?? "";
    $end = internship_calendar_parse_date($values[$field.".EndDate"] ?? ($values[$meta["end_field"]] ?? "")) ?? "";
    $days = [];
    $prefix = $field.".Days.D";
    foreach ($values as $path => $value)
    {
        if (strncmp((string)$path, $prefix, strlen($prefix)) !== 0)
            continue ;
        if (!preg_match('/^'.preg_quote($prefix, '/').'(\d{8})\.(Morning|Afternoon)$/D', (string)$path, $m))
            continue ;
        $iso = substr($m[1], 0, 4)."-".substr($m[1], 4, 2)."-".substr($m[1], 6, 2);
        if (internship_calendar_parse_date($iso) === NULL)
            continue ;
        $status = strtoupper(trim((string)$value));
        if (!in_array($status, ["T", "E", "F"], true))
            continue ;
        if (!isset($days[$iso]))
            $days[$iso] = ["am" => "", "pm" => ""];
        $days[$iso][$m[2] === "Morning" ? "am" : "pm"] = $status;
    }
    ksort($days, SORT_STRING);
    return (internship_calendar_transport_encode(["start" => $start, "end" => $end, "days" => $days]));
}

function internship_calendar_normalize_for_range($raw, $start_value, $end_value)
{
    $payload = internship_calendar_transport_decode($raw);
    if ($payload === NULL)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "calendar"]);
    $start = internship_calendar_parse_date($start_value);
    $end = internship_calendar_parse_date($end_value);
    if ($start === NULL && trim((string)$start_value) === "" && $end === NULL && trim((string)$end_value) === "")
        return (["ok" => true, "payload" => ["start" => "", "end" => "", "days" => []]]);
    if ($start === NULL)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "start date"]);
    if ($end === NULL)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "end date"]);
    $span = internship_calendar_days_between($start, $end);
    if ($span === NULL || $span < 0)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "calendar date range"]);
    if ($span > 1461)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "calendar range > 4 years"]);

    $days = [];
    for ($i = 0; $i <= $span; ++$i)
    {
        $iso = internship_calendar_iso_add_days($start, $i);
        $holiday = internship_calendar_holiday_name($iso) !== NULL;
        $incoming = $payload["days"][$iso] ?? ["am" => "", "pm" => ""];
        $current = ["am" => "", "pm" => ""];
        foreach (["am", "pm"] as $half)
        {
            $status = strtoupper(trim((string)($incoming[$half] ?? "")));
            if (in_array($status, ["T", "E"], true))
                $current[$half] = $status;
            else if ($holiday)
                $current[$half] = "F";
        }
        if ($current["am"] !== "" || $current["pm"] !== "")
            $days[$iso] = $current;
    }
    return (["ok" => true, "payload" => ["start" => $start, "end" => $end, "days" => $days]]);
}

function internship_calendar_expand_submission($field, array $definition, $raw, array $context_values)
{
    $meta = internship_calendar_field_metadata($definition);
    $normalized = internship_calendar_normalize_for_range(
        $raw,
        $context_values[$meta["start_field"]] ?? "",
        $context_values[$meta["end_field"]] ?? ""
    );
    if (!$normalized["ok"])
    {
        $normalized["details"] = $field.": ".($normalized["details"] ?? "invalid calendar");
        return ($normalized);
    }
    $payload = $normalized["payload"];
    $values = [];
    if ($payload["start"] !== "" && $payload["end"] !== "")
    {
        $values[$field.".Format"] = "HalfDayV1";
        $values[$field.".Country"] = "FR";
        $values[$field.".StartDate"] = $payload["start"];
        $values[$field.".EndDate"] = $payload["end"];
        foreach ($payload["days"] as $date => $halves)
        {
            $node = "D".str_replace("-", "", $date);
            if (($halves["am"] ?? "") !== "")
                $values[$field.".Days.".$node.".Morning"] = $halves["am"];
            if (($halves["pm"] ?? "") !== "")
                $values[$field.".Days.".$node.".Afternoon"] = $halves["pm"];
        }
    }
    if ($meta["summary_field"] !== "")
        $values[$meta["summary_field"]] = internship_calendar_summary_from_payload(
            internship_calendar_transport_encode($payload)
        );
    return ([
        "ok" => true,
        "values" => $values,
        "remove_prefix" => $field.".",
        "summary_field" => $meta["summary_field"],
        "payload" => internship_calendar_transport_encode($payload),
    ]);
}

function internship_calendar_render_editor(array $definition, $raw, array $attributes = [])
{
    $payload = internship_calendar_transport_decode($raw);
    if ($payload === NULL)
        $payload = ["start" => "", "end" => "", "days" => []];
    $meta = internship_calendar_field_metadata($definition);
    $attrs = "";
    foreach ($attributes as $name => $value)
    {
        if ($value === false || $value === NULL)
            continue ;
        $name = preg_replace('/[^A-Za-z0-9_:\-]/', '', (string)$name);
        if ($name === "")
            continue ;
        $attrs .= " ".$name.'="'.internship_calendar_h($value).'"';
    }
    $value = internship_calendar_h(internship_calendar_transport_encode($payload));
    $morning_hours = 3.5;
    $afternoon_hours = 3.5;
    if (function_exists("internship_session_plan_settings"))
    {
        $settings = internship_session_plan_settings();
        if (is_array($settings))
        {
            $morning_hours = internship_calendar_hours_between_times($settings["morning_start"], $settings["morning_end"], 3.5);
            $afternoon_hours = internship_calendar_hours_between_times($settings["afternoon_start"], $settings["afternoon_end"], 3.5);
        }
    }
    return (
        '<div class="internship-calendar-editor" data-internship-calendar '.
        'data-start-field="'.internship_calendar_h($meta["start_field"]).'" '.
        'data-end-field="'.internship_calendar_h($meta["end_field"]).'" '.
        'data-morning-hours="'.internship_calendar_h($morning_hours).'" '.
        'data-afternoon-hours="'.internship_calendar_h($afternoon_hours).'">'.
        '<input type="hidden" value="'.$value.'"'.$attrs.' />'.
        '<div class="internship-calendar-toolbar" role="toolbar" aria-label="Outil calendrier">'.
        '<button type="button" data-internship-calendar-tool="T" class="is-active" title="Entreprise / travail">T — Entreprise</button>'.
        '<button type="button" data-internship-calendar-tool="E" title="École">E — École</button>'.
        '<button type="button" data-internship-calendar-tool="erase" title="Effacer cette demi-journée">⌫ — Gomme</button>'.
        '</div>'.
        '<div class="internship-calendar-help">Cliquez séparément sur Matin ou Après-midi. Les jours fériés (F) sont automatiques ; la gomme rétablit F sur un jour férié. Affichage compact : jusqu’à trois mois par ligne sur grand écran.</div>'.
        '<div class="internship-calendar-stats" data-internship-calendar-stats></div>'.
        '<div class="internship-calendar-months" data-internship-calendar-months></div>'.
        '</div>'
    );
}
