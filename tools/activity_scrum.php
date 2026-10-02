<?php

/**
 * Scrum activity cascade generator.
 *
 * A Scrum occurrence is deliberately modelled as one activity owning one
 * session. Repetition is therefore expressed by several activities, not by one
 * activity carrying a collection of repeated sessions.
 */

function activity_scrum_parse_clock($value)
{
    if (!is_string($value) && !is_int($value))
        return (NULL);
    $value = trim((string)$value);
    if (!preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $value, $m))
        return (NULL);
    return ((int)$m[1] * 3600 + (int)$m[2] * 60);
}

function activity_scrum_day_start($timestamp)
{
    return ((int)floor(((int)$timestamp) / 86400) * 86400);
}

function activity_scrum_weekday($day_start)
{
    // Infosphere stores/handles pedagogical dates without localisation. gmdate
    // therefore follows the same day boundaries as date_to_timestamp()/datex().
    return ((int)gmdate('N', (int)$day_start)); // Monday = 1, Sunday = 7
}

function activity_scrum_next_weekday($day_start)
{
    do
        $day_start += 86400;
    while (activity_scrum_weekday($day_start) > 5);
    return ($day_start);
}

function activity_scrum_week_monday($day_start)
{
    return ($day_start - (activity_scrum_weekday($day_start) - 1) * 86400);
}

function activity_scrum_week_friday($day_start)
{
    return (activity_scrum_week_monday($day_start) + 4 * 86400);
}

function activity_scrum_add_occurrence(array &$out, array &$counters, $kind, $type, $prefix, $project_codename, $day, $begin, $end, $slot_duration)
{
    $number = $counters[$kind]++;
    $out[] = [
        'kind' => $kind,
        'type' => $type,
        'codename' => $project_codename.'-'.$prefix.'-'.sprintf('%03d', $number),
        'begin' => (int)$begin,
        'end' => (int)$end,
        'day' => (int)$day,
        'slot_duration' => (int)$slot_duration,
        'progressive_slot_opening' => 1,
        'team_based_slot_opening' => 1,
    ];
}

/**
 * Build the deterministic Scrum calendar without touching the database.
 *
 * $subject_appeir and $pickup are timestamps in the same convention as
 * Infosphere activity dates (real dates for instances, date0-relative dates for
 * templates). The returned end date is the Friday of the pickup week, so that
 * the pickup week is included as requested.
 */
function activity_scrum_plan($project_codename, $subject_appeir, $pickup, $sprint_weeks, $window_begin, $window_end)
{
    $errors = [];
    $project_codename = trim((string)$project_codename);
    $sprint_weeks = (int)$sprint_weeks;
    $window_begin_seconds = activity_scrum_parse_clock($window_begin);
    $window_end_seconds = activity_scrum_parse_clock($window_end);

    if ($project_codename === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $project_codename))
        $errors[] = 'codename de projet invalide';
    if ($subject_appeir === NULL || $subject_appeir === '' || !is_numeric($subject_appeir))
        $errors[] = "date d'apparition du sujet manquante";
    if ($pickup === NULL || $pickup === '' || !is_numeric($pickup))
        $errors[] = 'date de ramassage manquante';
    if ($sprint_weeks < 1 || $sprint_weeks > 3)
        $errors[] = 'la longueur du sprint doit être comprise entre 1 et 3 semaines';
    if ($window_begin_seconds === NULL || $window_end_seconds === NULL || $window_begin_seconds >= $window_end_seconds)
        $errors[] = 'la plage quotidienne Scrum est invalide';
    if ($errors)
        return (['ok' => false, 'errors' => $errors, 'activities' => []]);

    $subject_appeir = (int)$subject_appeir;
    $pickup = (int)$pickup;
    if ($pickup < $subject_appeir)
        return (['ok' => false, 'errors' => ['le ramassage doit être postérieur à l’apparition du sujet'], 'activities' => []]);

    $period_end_day = activity_scrum_week_friday(activity_scrum_day_start($pickup));
    $period_end = $period_end_day + $window_end_seconds;

    // Find the first usable meeting window after subject appearance. If the
    // subject appears while the daily window is open, planning starts at that
    // precise minute; if less than 30 minutes remain, planning moves to the next
    // working day. This guarantees that no generated meeting predates the subject.
    $planning_day = activity_scrum_day_start($subject_appeir);
    while (activity_scrum_weekday($planning_day) > 5)
        $planning_day = activity_scrum_next_weekday($planning_day);

    while (true)
    {
        $planning_begin = $planning_day + $window_begin_seconds;
        if ($planning_day == activity_scrum_day_start($subject_appeir))
            $planning_begin = max($planning_begin, $subject_appeir);
        if ($planning_begin + 30 * 60 <= $planning_day + $window_end_seconds)
            break ;
        $planning_day = activity_scrum_next_weekday($planning_day);
        if ($planning_day + $window_begin_seconds > $period_end)
            return (['ok' => false, 'errors' => ['aucune plage Scrum disponible après l’apparition du sujet'], 'activities' => []]);
    }

    if ($planning_begin > $period_end)
        return (['ok' => false, 'errors' => ['aucune plage Scrum disponible avant la fin de la période'], 'activities' => []]);

    $out = [];
    $counters = ['planning' => 0, 'daily' => 0, 'retrospective' => 0];

    while ($planning_day <= $period_end_day)
    {
        // The first sprint may start mid-week. Subsequent sprints start on a
        // Monday. Sprint length is measured in calendar weeks containing the
        // planning occurrence; a final sprint is truncated by the pickup week.
        $sprint_monday = activity_scrum_week_monday($planning_day);
        $sprint_end_day = $sprint_monday + (($sprint_weeks - 1) * 7 + 4) * 86400;
        if ($sprint_end_day > $period_end_day)
            $sprint_end_day = $period_end_day;

        $this_planning_begin = $planning_day + $window_begin_seconds;
        if (!count($out) && $planning_day == activity_scrum_day_start($subject_appeir))
            $this_planning_begin = max($this_planning_begin, $subject_appeir);
        activity_scrum_add_occurrence(
            $out,
            $counters,
            'planning',
            'PlanificationMeeting',
            'PLN',
            $project_codename,
            $planning_day,
            $this_planning_begin,
            $planning_day + $window_end_seconds,
            30
        );

        $day = activity_scrum_next_weekday($planning_day);
        while ($day <= $sprint_end_day)
        {
            if ($day == $sprint_end_day)
            {
                // Planning wins over retrospective in the degenerate case where
                // the first partial sprint starts on its final Friday.
                if ($day != $planning_day)
                    activity_scrum_add_occurrence(
                        $out,
                        $counters,
                        'retrospective',
                        'RetrospectiveMeeting',
                        'RET',
                        $project_codename,
                        $day,
                        $day + $window_begin_seconds,
                        $day + $window_end_seconds,
                        30
                    );
                break ;
            }

            activity_scrum_add_occurrence(
                $out,
                $counters,
                'daily',
                'DailyMeeting',
                'TLJ',
                $project_codename,
                $day,
                $day + $window_begin_seconds,
                $day + $window_end_seconds,
                10
            );
            $day = activity_scrum_next_weekday($day);
        }

        $planning_day = activity_scrum_next_weekday($sprint_end_day);
        if (activity_scrum_weekday($planning_day) != 1)
            $planning_day = activity_scrum_week_monday($planning_day) + 7 * 86400;
        if ($planning_day > $period_end_day)
            break ;
    }

    return (['ok' => true, 'errors' => [], 'activities' => $out]);
}

function activity_scrum_supported_project_type($type_name)
{
    return (in_array((string)$type_name, ['MiniProject', 'Project', 'Product', 'Rush'], true));
}

function activity_scrum_insert_session($id_activity, array $occurrence)
{
    global $Database;

    $begin = $Database->real_escape_string(db_form_date($occurrence['begin']));
    $end = $Database->real_escape_string(db_form_date($occurrence['end']));
    if ($Database->query("\n        INSERT INTO session\n          (id_activity, id_laboratory, id_team, id_user, begin_date, end_date, maximum_subscription)\n        VALUES\n          (".(int)$id_activity.", -1, -1, -1, '$begin', '$end', NULL)\n    ") === false)
        return (NULL);
    return ((int)$Database->insert_id);
}

function activity_scrum_existing_codename($codename)
{
    global $Database;

    $codename = $Database->real_escape_string((string)$codename);
    return (db_select_one("id, deleted FROM activity WHERE codename = '$codename'") != NULL);
}

function GenerateActivityScrum($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    if (($project = new FullActivity)->build($id, false, false) == false)
        return (new ErrorResponse('NotFound'));
    if ($project->parent_activity == -1 || !activity_scrum_supported_project_type($project->type_name))
        return (new ErrorResponse('InvalidRequest', 'Scrum: type d’activité incompatible'));
    if ($project->subject_appeir_date === NULL || $project->pickup_date === NULL)
        return (new ErrorResponse('InvalidRequest', 'SubjectAppeirDate / PickupDate'));

    $sprint_weeks = isset($data['sprint_weeks']) ? (int)$data['sprint_weeks'] : 1;
    $window_begin = isset($data['scrum_begin']) ? $data['scrum_begin'] : '';
    $window_end = isset($data['scrum_end']) ? $data['scrum_end'] : '';
    $plan = activity_scrum_plan(
        $project->codename,
        $project->subject_appeir_date,
        $project->pickup_date,
        $sprint_weeks,
        $window_begin,
        $window_end
    );
    if (!$plan['ok'])
        return (new ErrorResponse('InvalidRequest', implode(' | ', $plan['errors'])));

    foreach ($plan['activities'] as $occurrence)
        if (activity_scrum_existing_codename($occurrence['codename']))
            return (new ErrorResponse('CodeNameAlreadyUsed', $occurrence['codename']));

    if ($Database->query('START TRANSACTION') === false)
        return (new ErrorResponse('CannotAdd'));

    $created = 0;
    try
    {
        foreach ($plan['activities'] as $occurrence)
        {
            $fields = [
                'codename' => $occurrence['codename'],
                'parent_activity' => $project->parent_activity,
                'reference_activity' => $project->codename,
                'type' => $occurrence['type'],
                'slot_duration' => $occurrence['slot_duration'],
                'progressive_slot_opening' => 1,
                'team_based_slot_opening' => 1,
                // This date gives the generated cascade a chronological order
                // in activity lists without constraining appointment opening.
                'subject_appeir_date' => db_form_date($occurrence['begin']),
                'min_team_size' => $project->min_team_size,
                'max_team_size' => $project->max_team_size,
            ];
            if (($ret = add_activity($fields, [], (bool)$project->is_template))->is_error())
                throw new RuntimeException(strval($ret));
            $id_activity = (int)$ret->value['id'];
            if (activity_scrum_insert_session($id_activity, $occurrence) === NULL)
                throw new RuntimeException('CannotAdd session '.$occurrence['codename']);
            ++$created;
        }

        if ($Database->query('COMMIT') === false)
            throw new RuntimeException('CannotCommit');
    }
    catch (Throwable $e)
    {
        $Database->query('ROLLBACK');
        return (new ErrorResponse('CannotAdd', $e->getMessage()));
    }

    return (new ValueResponse([
        'msg' => ($Dictionnary['ScrumCascadeCreated'] ?? 'Cascade Scrum créée')." ($created)",
        'created' => $created,
    ]));
}
