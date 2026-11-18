<?php

function exam_workflow_teams(int $now): array
{
    $from = date('Y-m-d H:i:s', $now - 7200);
    $until = date('Y-m-d H:i:s', $now);
    $deadline = 'COALESCE(session.end_date, activity.pickup_date)';
    $frequency = activity_automatic_evaluation_frequency_sql();
    return db_select_all("
        activity.id AS id_activity, activity.codename AS activity_name, activity.subject_appeir_date,
        team.id AS id_team, user.id AS id_user, user.codename AS login, session.id AS id_session,
        session.begin_date, $deadline AS deadline,
        COALESCE(NULLIF(activity.repository_name, ''), NULLIF(template.repository_name, ''), '') AS repository,
        CASE WHEN COALESCE(activity.reference_activity, 0) <= 0 THEN $frequency ELSE 0 END AS frequency
        FROM activity
        INNER JOIN team ON team.id_activity = activity.id
        INNER JOIN user_team ON user_team.id_team = team.id AND user_team.status = 2
        INNER JOIN user ON user.id = user_team.id_user AND user.deleted IS NULL
        LEFT JOIN activity AS template ON template.id = activity.id_template AND activity.template_link = 1
        LEFT JOIN session ON session.id_activity = activity.id AND session.deleted IS NULL
            AND session.end_date > session.begin_date AND (
                session.id = team.id_session OR (
                    COALESCE(team.id_session, 0) <= 0 AND NOT EXISTS (
                        SELECT 1 FROM session AS other
                        WHERE other.id_activity = activity.id AND other.id <> session.id AND other.deleted IS NULL
                    )
                )
            )
        WHERE activity.is_template = 0 AND activity.deleted IS NULL AND activity.type BETWEEN 5 AND 9
          AND (activity.repository_name <> '' OR template.repository_name <> '')
          AND (session.id IS NOT NULL OR NOT EXISTS (
              SELECT 1 FROM session AS planned WHERE planned.id_activity = activity.id
                AND planned.deleted IS NULL AND planned.end_date > planned.begin_date
          ))
          AND $deadline >= '$from'
          AND ($deadline <= '$until' OR session.begin_date <= '$until')
        ORDER BY $deadline, team.id
    ");
}

function exam_workflow_final_marker(int $team_id)
{
    $delivered = activity_delivery_final_status_delivered();
    $missing = activity_delivery_final_status_missing();
    return db_select_one("
        status, observation FROM pickedup_work
        WHERE id_team = $team_id AND status IN ('$delivered', '$missing')
        ORDER BY id DESC
    ");
}

function exam_workflow_archive_name(?array $marker): ?string
{
    if (($marker['status'] ?? null) !== activity_delivery_final_status_delivered()
        || !preg_match('/^Official exam pickup ([A-Za-z0-9._-]+_official\.tar\.gz)$/D', (string)($marker['observation'] ?? ''), $match))
        return null;
    return $match[1];
}

function exam_workflow_collect_due(): void
{
    $now = time();
    foreach (exam_workflow_teams($now) as $team)
    {
        $deadline = strtotime($team['deadline']);
        $team_id = (int)$team['id_team'];
        if (!$deadline || $deadline > $now || exam_workflow_final_marker($team_id))
            continue;
        $response = hand_request([
            'command' => 'pickup',
            'secretary' => $team['login'],
            'subject' => $team['repository'],
            'is_exam' => true,
            'official' => true,
            'exam_deadline' => $deadline,
        ], true, 65);
        if (($response['result'] ?? '') === 'ok' && is_string($response['archive_name'] ?? null))
            activity_delivery_record_final($team_id, true, 'Official exam pickup ' . $response['archive_name']);
        elseif (($response['code'] ?? '') === 'EmptyExamRepository')
        {
            if (activity_delivery_record_final($team_id, false, 'Empty exam repository'))
                activity_delivery_award_no_delivery((int)$team['id_activity'], $team_id);
        }
        else
            add_log(REPORT, 'Exam pickup failed for team #' . $team_id . '. It will be retried by Albedo.', 1);
    }
}

function exam_workflow_first_intermediate_at(int $start, int $interval, ?string $release): int
{
    $release_at = $release === null ? $start : strtotime($release);
    return $start + intdiv(max($interval, $release_at - $start) + $interval - 1, $interval) * $interval;
}

function exam_workflow_intermediate_due(array $team, int $now): ?int
{
    $frequency = (int)$team['frequency'];
    if ($frequency <= 0 || empty($team['begin_date']))
        return null;
    $start = strtotime($team['begin_date']);
    $end = strtotime($team['deadline']);
    if (!$start || !$end || $now >= $end)
        return null;
    $interval = $frequency * 60;
    $first = exam_workflow_first_intermediate_at($start, $interval, $team['subject_appeir_date']);
    if ($now < $first)
        return null;
    return $first + intdiv($now - $first, $interval) * $interval;
}

function exam_workflow_evaluate(array $team, ?string $archive): ?string
{
    $activity = new FullActivity;
    if (!$activity->build((int)$team['id_activity'], false, false) || !$activity->current_configuration)
        return null;
    [$configuration, $functions] = build_evaluator_configuration($activity, (int)$team['id_user']);
    if ($configuration === null || $functions === null)
        return null;

    $response = hand_request([
        'command' => 'retrieve',
        'user' => $team['login'],
        'repo' => $team['repository'],
        'is_exam' => true,
        'correction' => true,
        'alive' => $archive === null,
        'official' => $archive !== null,
        'archive_name' => $archive,
        'configuration' => base64_encode($configuration),
        'allowFunc' => base64_encode($functions),
    ], true, 185);
    if (($response['result'] ?? '') !== 'ok' || !is_string($response['content'] ?? null))
        return null;
    $bytes = base64_decode($response['content'], true);
    return $bytes !== false && $bytes !== '' ? $bytes : null;
}

function exam_workflow_trace_text(int $slot, ?string $report): string
{
    $lines = ['Moulinette intermédiaire du ' . date('d/m/Y à H:i', $slot), ''];
    if ($report === null)
        $lines[] = 'Aucun résultat disponible pour ce passage.';
    else
    {
        $model = evaluation_history_model($report);
        $labels = ['Success' => 'Réussi', 'Failure' => 'Échec', 'Critical' => 'Erreur de correction'];
        foreach ($model['results'] as $result)
        {
            $name = ($result['context'] === '' ? '' : $result['context'] . ' / ') . $result['name'];
            $name = str_replace(["\r", "\n"], ' ', $name);
            $label = $labels[$result['status']] ?? 'Sans résultat';
            $lines[] = $name . ' — ' . $label;
        }
        if (!$model['results'])
            $lines[] = 'Aucun exercice reconnu dans le rapport.';
    }
    $text = preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/', '', implode("\n", $lines) . "\n");
    return strlen($text) <= 65536 ? $text : substr($text, 0, 65000) . "\n";
}

function exam_workflow_run_intermediate(array $team, int $slot): void
{
    $team_id = (int)$team['id_team'];
    $deadline = strtotime($team['deadline']);
    $local_slot = date('Y-m-d H:i:s', $slot);
    if ($deadline <= time() || activity_automatic_evaluation_last_run($team_id) >= date_to_timestamp($local_slot))
        return;
    $archive = exam_workflow_evaluate($team, null);
    $report = $archive !== null ? evaluation_history_extract_report($archive) : null;
    $members = array_column(db_select_all("
        user.codename FROM user_team
        INNER JOIN user ON user.id = user_team.id_user AND user.deleted IS NULL
        WHERE user_team.id_team = $team_id AND user_team.status > 0
    "), 'codename');
    $response = hand_request([
        'command' => 'deliver_to_inbox',
        'members' => $members,
        'end_at' => $deadline,
        'exam_trace' => base64_encode(exam_workflow_trace_text($slot, $report)),
    ], true, 15);
    if (($response['result'] ?? '') !== 'ok')
        return;
    activity_automatic_evaluation_record($team_id, $report !== null, 'Intermediate exam evaluation', $local_slot);
}

function exam_workflow_run_official(array $team): void
{
    global $Dictionnary;
    $id = (int)$team['id_activity'];
    $team_id = (int)$team['id_team'];
    $name = exam_workflow_archive_name(exam_workflow_final_marker($team_id));
    if ($name === null)
        return;
    foreach (evaluation_history_list($id, $team_id) as $previous)
        if (!empty($previous['official']) && $previous['origin'] === 'exam_pickup')
            return;

    $bytes = exam_workflow_evaluate($team, $name);
    $report = $bytes !== null ? evaluation_history_extract_report($bytes) : null;
    if ($report === null || !evaluation_history_save($id, $team_id, true, $bytes, 'exam_pickup'))
        return;
    official_correction_mark_missing_medals_as_failed($id, $team_id);
    user_money_reward_completed_activity_for_team($id, $team_id, true);
    $students = activity_automatic_evaluation_students_mail($team_id);
    if ($students)
        send_mail($students, $Dictionnary['EvaluationReport'] . ' ' . $team['activity_name'],
            'This evaluation has been run automatically and is official.', null, [['report.tar.gz' => $bytes]],
            false, null, null, evaluation_history_email_html($team['activity_name'], true, $report));
}

function exam_workflow_correct(): void
{
    $now = time();
    $teams = exam_workflow_teams($now);
    foreach ($teams as $team)
    {
        if (($slot = exam_workflow_intermediate_due($team, $now)) !== null)
            exam_workflow_run_intermediate($team, $slot);
    }
    foreach ($teams as $team)
        if (strtotime($team['deadline']) <= time())
            exam_workflow_run_official($team);
}

function exam_workflow_start_worker(): void
{
    if (!function_exists('exec') || !is_executable(PHP_BINARY))
        return;
    if (exam_workflow_teams(time()))
    {
        $worker = __DIR__ . '/process_exam_corrections.php';
        exec('nohup ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker)
            . ' </dev/null >/dev/null 2>&1 &');
    }
}
