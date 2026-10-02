<?php

/**
 * Stable storage identity for one temporal trimester on one learner profile.
 * FullProfile groups concurrent cycles by their first month/year, so the same
 * key is deliberately used here instead of an individual technical cycle id.
 */
function report_card_period_key($cycle, array $cycle_ids = [])
{
    if (is_object($cycle) && isset($cycle->first_day) && $cycle->first_day !== NULL)
    {
        $key = datex("Ym", $cycle->first_day);
        if (preg_match('/^[0-9]{6}$/D', (string)$key))
            return ((string)$key);
    }
    $cycle_number = is_object($cycle) && isset($cycle->cycle) ? (int)$cycle->cycle : 0;
    $ids = array_values(array_unique(array_filter(array_map("intval", $cycle_ids))));
    sort($ids, SORT_NUMERIC);
    return ("cycle".$cycle_number."_".substr(hash("sha256", implode("-", $ids)), 0, 12));
}

function report_card_storage_directory($student_codename)
{
    global $Configuration;

    return (rtrim($Configuration->UsersDir((string)$student_codename), "/")."/admin/bulletins/");
}

function report_card_safe_cycle_codename($cycle)
{
    $codename = is_object($cycle) && isset($cycle->codename) ? (string)$cycle->codename : "cycle";
    $safe = preg_replace('/[^a-zA-Z0-9_-]+/', "_", $codename);
    return ($safe !== "" ? $safe : "cycle");
}

function report_card_canonical_path($student_codename, $period_key)
{
    $period_key = preg_replace('/[^A-Za-z0-9_-]+/', "_", (string)$period_key);
    return (report_card_storage_directory($student_codename)."bulletin_".$period_key.".pdf");
}

/**
 * Include the old timestamped filename so the first regeneration after this
 * change can collapse historical duplicates into the new canonical file.
 */
function report_card_existing_paths($student_codename, $period_key, $cycle)
{
    $base = report_card_storage_directory($student_codename);
    $canonical = report_card_canonical_path($student_codename, $period_key);
    $paths = [];
    if (is_file($canonical))
        $paths[$canonical] = true;

    $legacy = glob($base."*_bulletin_".report_card_safe_cycle_codename($cycle).".pdf");
    if (is_array($legacy))
        foreach ($legacy as $path)
            if (is_file($path))
                $paths[$path] = true;
    return (array_keys($paths));
}

function report_card_source_key($student_id, $period_key)
{
    return ("report-card:".(int)$student_id.":".(string)$period_key);
}
