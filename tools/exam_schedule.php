<?php

function exam_schedule_publish()
{
    global $Database;

    // The database stores local wall time. Distrans and Persoc use Unix time.
    $now = time();
    $from = date('Y-m-d H:i:s', $now);
    $until = date('Y-m-d H:i:s', $now + 86400);
    $frequency = activity_automatic_evaluation_frequency_sql();
    $rows = $Database->query("
        SELECT DISTINCT session.id AS session_id, session.begin_date, session.end_date,
               room.codename AS room, user.codename AS login,
               activity.reference_activity, activity.subject_appeir_date, $frequency AS frequency,
               COALESCE(NULLIF(activity.repository_name, ''), NULLIF(template.repository_name, ''), '') AS repository
        FROM session
        INNER JOIN activity ON activity.id = session.id_activity
        LEFT JOIN activity AS template ON template.id = activity.id_template AND activity.template_link = 1
        INNER JOIN session_room ON session_room.id_session = session.id
        INNER JOIN room ON room.id = session_room.id_room AND room.deleted IS NULL
        LEFT JOIN team ON (
            (activity.reference_activity > 0 AND team.id_activity = activity.reference_activity)
            OR (team.id_activity = activity.id AND (
                team.id_session = session.id
                OR (COALESCE(team.id_session, 0) <= 0 AND NOT EXISTS (
                    SELECT 1 FROM session AS other
                    WHERE other.id_activity = activity.id AND other.id <> session.id AND other.deleted IS NULL
                ))
            ))
        )
        LEFT JOIN user_team ON user_team.id_team = team.id AND user_team.status > 0
        LEFT JOIN user ON user.id = user_team.id_user AND user.deleted IS NULL AND user.profile_status = 'member'
        WHERE session.deleted IS NULL AND activity.deleted IS NULL AND activity.is_template = 0
          AND activity.type BETWEEN 5 AND 9
          AND session.begin_date <= '$until' AND session.end_date > '$from'
          AND session.end_date > session.begin_date
    ");
    $desk_rows = $Database->query("
        SELECT DISTINCT room.codename AS room, room_desk.mac
        FROM room_desk
        INNER JOIN room ON room.id = room_desk.id_room AND room.deleted IS NULL
        WHERE room_desk.mac IS NOT NULL AND room_desk.mac <> ''
    ");
    if (!$rows || !$desk_rows)
        return (false);

    $schedule = [];
    while ($row = $rows->fetch_assoc())
    {
        $room = $row['room'];
        $id = (int)$row['session_id'];
        if (!isset($schedule[$room][$id]))
        {
            $start = strtotime($row['begin_date']);
            $end = strtotime($row['end_date']);
            $schedule[$room][$id] = [
                'room' => $room,
                'session_id' => $id,
                'start_at' => $start,
                'end_at' => $end,
                'users' => [],
            ];
            $interval = (int)$row['frequency'] * 60;
            if ($row['repository'] !== '' && (int)$row['reference_activity'] <= 0 && $interval > 0)
            {
                $first = exam_workflow_first_intermediate_at($start, $interval, $row['subject_appeir_date']);
                if ($first < $end)
                {
                    $schedule[$room][$id]['first_intermediate_at'] = $first;
                    $schedule[$room][$id]['intermediate_every'] = $interval;
                }
            }
        }
        $login = $row['login'];
        if ($login !== NULL && preg_match('/^[a-z]+\.[a-z]+[0-9]*$/D', $login))
            $schedule[$room][$id]['users'][$login] = true;
    }

    $sessions = [];
    foreach ($schedule as $room_sessions)
        foreach ($room_sessions as $session)
        {
            $session['users'] = array_keys($session['users']);
            $sessions[] = $session;
        }

    $desks = [];
    while ($desk = $desk_rows->fetch_assoc())
        if (filter_var($desk['mac'], FILTER_VALIDATE_MAC) !== false)
            $desks[] = $desk;

    $response = hand_request(['command' => 'set_exam_schedule', 'sessions' => $sessions, 'desks' => $desks], true, 10);
    return (($response['result'] ?? '') === 'ok');
}

function exam_room_statuses()
{
    $response = hand_request(['command' => 'get_room_exam_states'], false, 3);
    if (($response['result'] ?? '') !== 'ok' || !is_array($response['rooms'] ?? NULL))
        return (NULL);
    foreach ($response['rooms'] as $state)
        if (!is_array($state) || !in_array($state['mode'] ?? NULL, ['normal', 'upcoming', 'exam'], true))
            return (NULL);
    return ($response['rooms']);
}
