<?php

function halfday_presence_table_available()
{
    static $available = NULL;

    if ($available !== NULL)
        return ($available);
    $tables = db_get_tables();
    return ($available = isset($tables["user_log_halfday"]));
}

function halfday_presence_periods_for_day($day)
{
    $day = remove_hour($day);
    return ([
        0 => [
            "name" => "AM",
            "begin" => $day + 8 * 60 * 60,
            "end" => $day + 13 * 60 * 60,
            "threshold" => 3 * 60 * 60,
        ],
        1 => [
            "name" => "PM",
            "begin" => $day + 13 * 60 * 60,
            "end" => $day + 19 * 60 * 60,
            "threshold" => 2 * 60 * 60,
        ],
    ]);
}

function halfday_presence_add_interval($user, $type, $begin, $end)
{
    global $Database;

    if (!halfday_presence_table_available())
        return ;
    if (!in_array((int)$type, user_log_valid_activity_types()))
        return ;
    if (is_array($user))
        $user = $user["id"];
    else if (is_object($user))
        $user = $user->id;
    if (!is_number($user))
        return ;
    $user = (int)$user;
    $type = (int)$type;
    $begin = (int)$begin;
    $end = (int)$end;
    if ($end <= $begin)
        return ;

    for ($day = remove_hour($begin); $day <= remove_hour($end); $day += 24 * 60 * 60)
    {
        foreach (halfday_presence_periods_for_day($day) as $period => $cfg)
        {
            $overlap = min($end, $cfg["end"]) - max($begin, $cfg["begin"]);
            if ($overlap <= 0)
                continue ;
            $date = db_form_date($day, true);
            $Database->query("\n                INSERT INTO user_log_halfday\n                (id_user, log_date, period, type, duration)\n                VALUES ($user, '$date', $period, $type, $overlap)\n                ON DUPLICATE KEY UPDATE duration = duration + VALUES(duration)\n            ");
        }
    }
}

function halfday_presence_user_ids($users)
{
    $ids = [];
    foreach ($users as $usr)
    {
        if (is_array($usr))
            $id = try_get($usr, "id_user", try_get($usr, "id", NULL));
        else if (is_object($usr))
            $id = $usr->id;
        else
            $id = $usr;
        if (is_number($id))
            $ids[(int)$id] = (int)$id;
    }
    return (array_values($ids));
}

function halfday_presence_load_log_durations($user_ids, $start, $days)
{
    $out = [];
    if (!halfday_presence_table_available())
        return ($out);
    $ids = halfday_presence_user_ids($user_ids);
    if (!count($ids))
        return ($out);
    $start = remove_hour($start);
    $end = $start + ($days - 1) * 24 * 60 * 60;
    $idlist = implode(", ", array_map("intval", $ids));
    $types = user_log_sql_type_list(user_log_valid_activity_types());
    $start_sql = db_form_date($start, true);
    $end_sql = db_form_date($end, true);

    $query = db_select_all("\n        id_user, log_date, period, SUM(duration) as duration\n        FROM user_log_halfday\n        WHERE id_user IN ($idlist)\n          AND type IN ($types)\n          AND log_date >= '$start_sql'\n          AND log_date <= '$end_sql'\n        GROUP BY id_user, log_date, period\n    ");
    foreach ($query as $row)
    {
        $uid = (int)$row["id_user"];
        $day = (int)(remove_hour($row["log_date"]) / (24 * 60 * 60));
        $period = (int)$row["period"];
        if (!isset($out[$uid]))
            $out[$uid] = [];
        if (!isset($out[$uid][$day]))
            $out[$uid][$day] = [];
        $out[$uid][$day][$period] = (int)$row["duration"];
    }
    return ($out);
}

function halfday_presence_load_activity_status($user_ids, $start, $days, $cycle_id = NULL)
{
    global $Database;

    $out = [];
    $ids = halfday_presence_user_ids($user_ids);
    if (!count($ids))
        return ($out);
    $start = remove_hour($start);
    $end = $start + $days * 24 * 60 * 60;
    $idlist = implode(", ", array_map("intval", $ids));
    $start_sql = db_form_date($start);
    $end_sql = db_form_date($end);

    $cycle_join = "";
    $cycle_where = "";
    if ($cycle_id !== NULL)
    {
        $cycle_id = (int)$cycle_id;
        $cycle_join = "LEFT JOIN activity_cycle ON activity_cycle.id_activity = session.id_activity";
        $cycle_where = "AND activity_cycle.id_cycle = $cycle_id";
    }

    $query = db_select_all("\n        user_team.id_user as id_user,\n        team.present as present,\n        session.begin_date as begin_date,\n        COALESCE(session.end_date, DATE_ADD(session.begin_date, INTERVAL 1 HOUR)) as end_date\n        FROM user_team\n        LEFT JOIN team ON team.id = user_team.id_team\n        LEFT JOIN session ON session.id = team.id_session\n        LEFT JOIN activity ON activity.id = session.id_activity\n        $cycle_join\n        WHERE user_team.id_user IN ($idlist)\n          AND session.deleted IS NULL\n          AND activity.deleted IS NULL\n          AND session.begin_date < '$end_sql'\n          AND COALESCE(session.end_date, DATE_ADD(session.begin_date, INTERVAL 1 HOUR)) > '$start_sql'\n          $cycle_where\n    ");

    foreach ($query as $row)
    {
        $uid = (int)$row["id_user"];
        $present = (int)$row["present"];
        $begin = date_to_timestamp($row["begin_date"]);
        $session_end = date_to_timestamp($row["end_date"]);
        for ($day = remove_hour($begin); $day <= remove_hour($session_end); $day += 24 * 60 * 60)
        {
            foreach (halfday_presence_periods_for_day($day) as $period => $cfg)
            {
                if (min($session_end, $cfg["end"]) <= max($begin, $cfg["begin"]))
                    continue ;
                $day_key = (int)($day / (24 * 60 * 60));
                if (!isset($out[$uid]))
                    $out[$uid] = [];
                if (!isset($out[$uid][$day_key]))
                    $out[$uid][$day_key] = [];
                if (!isset($out[$uid][$day_key][$period]))
                    $out[$uid][$day_key][$period] = ["present" => false, "absent" => false];
                if ($present == 1 || $present == -1)
                    $out[$uid][$day_key][$period]["present"] = true;
                else if ($present == -2)
                    $out[$uid][$day_key][$period]["absent"] = true;
            }
        }
    }
    return ($out);
}

function halfday_presence_build($users, $start, $days, $cycle_id = NULL)
{
    $ids = halfday_presence_user_ids($users);
    $start = remove_hour($start);
    $has_halfday_logs = halfday_presence_table_available();
    $logs = halfday_presence_load_log_durations($ids, $start, $days);
    $activities = halfday_presence_load_activity_status($ids, $start, $days, $cycle_id);
    $now = now();
    $out = [];

    foreach ($ids as $uid)
    {
        $out[$uid] = [];
        for ($i = 0; $i < $days; ++$i)
        {
            $day = $start + $i * 24 * 60 * 60;
            $day_key = (int)($day / (24 * 60 * 60));
            $out[$uid][$day_key] = [];
            foreach (halfday_presence_periods_for_day($day) as $period => $cfg)
            {
                $duration = (int)try_get(try_get(try_get($logs, $uid, []), $day_key, []), $period, 0);
                $act = try_get(try_get(try_get($activities, $uid, []), $day_key, []), $period, ["present" => false, "absent" => false]);
                $status = "future";
                $source = "";
                if ($act["present"])
                {
                    $status = "present";
                    $source = "activity";
                }
                else if ($act["absent"])
                {
                    $status = "activity_absent";
                    $source = "activity";
                }
                else if (!$has_halfday_logs)
                {
                    $status = "unknown";
                    $source = "";
                }
                else if ($duration >= $cfg["threshold"])
                {
                    $status = "present";
                    $source = "log";
                }
                else if ($cfg["end"] <= $now)
                {
                    $status = "absent";
                    $source = "log";
                }
                else if ($cfg["begin"] <= $now)
                {
                    $status = "pending";
                    $source = "log";
                }
                $out[$uid][$day_key][$period] = [
                    "status" => $status,
                    "source" => $source,
                    "duration" => $duration,
                    "threshold" => $cfg["threshold"],
                    "period" => $cfg["name"],
                ];
            }
        }
    }
    return ($out);
}
