<?php

function trace_setting($name, $default)
{
    global $Configuration;

    if (isset($Configuration)
        && isset($Configuration->Properties)
        && isset($Configuration->Properties[$name])
        && is_numeric($Configuration->Properties[$name]))
        return ($Configuration->Properties[$name] + 0);
    return ($default);
}

function trace_client_ip_key($ip)
{
    // The trace.ip column is currently varchar(15). Keep IPv4 readable and
    // store a deterministic compact key for IPv6/proxy chains instead of
    // letting MySQL truncate unpredictably.
    if (strlen($ip) <= 15)
        return ($ip);
    return ("v6".substr(md5($ip), 0, 13));
}

function trace_user_condition($id_user)
{
    if ($id_user === NULL)
        return ("id_user IS NULL");
    return ("id_user = ".((int)$id_user));
}

function trace_report_message($fast_visit_count, $ip, $id_user)
{
    global $OriginalUser;

    if ($id_user !== NULL && isset($OriginalUser) && $OriginalUser != NULL)
        $user = $OriginalUser["codename"];
    else
        $user = "anonymous";
    return ("Trace report: high request rate ($fast_visit_count fast hits) for $ip ($user)");
}

function trace_should_report($old_fast_visit_count, $fast_visit_count, $threshold, $report_every)
{
    if ($fast_visit_count < $threshold)
        return (false);
    if ($old_fast_visit_count < $threshold)
        return (true);
    return (floor($fast_visit_count / $report_every) > floor($old_fast_visit_count / $report_every));
}

function trace()
{
    global $Database;
    global $OriginalUser;

    $ip = $Database->real_escape_string(trace_client_ip_key(get_client_ip()));
    if ($OriginalUser != NULL)
        $id_user = (int)$OriginalUser["id"];
    else
        $id_user = NULL;

    $fast_window = max(1.0, (float)trace_setting("DdosFastWindowSeconds", 2.0));
    $cooldown = max($fast_window, (float)trace_setting("DdosCooldownSeconds", 20.0));
    $threshold = max(1, (int)trace_setting("DdosFastThreshold", 30));
    $report_every = max(1, (int)trace_setting("DdosReportEvery", 10));
    $retention_days = max(1, (int)trace_setting("DdosTraceRetentionDays", 31));
    $user_condition = trace_user_condition($id_user);

    $visit = db_select_one("
      id, id_user, visit_count, fast_visit_count,
      UNIX_TIMESTAMP(last_visit) as last_visit,
      UNIX_TIMESTAMP(NOW(6)) as now
      FROM trace
      WHERE $user_condition
      AND ip = '$ip'
      AND last_visit >= DATE_SUB(NOW(6), INTERVAL 1 DAY)
      ORDER BY last_visit DESC
    ");

    if (!$visit)
    {
        $sql_id_user = $id_user === NULL ? "NULL" : (int)$id_user;
        $Database->query("
            INSERT INTO trace (id_user, ip, last_visit, visit_count, fast_visit_count)
            VALUES ($sql_id_user, '$ip', NOW(6), 1, 1)
        ");
    }
    else
    {
        $id = (int)$visit["id"];
        $visit_count = ((int)$visit["visit_count"]) + 1;
        $old_fast_visit_count = (int)$visit["fast_visit_count"];
        $last = (float)$visit["last_visit"];
        $now = (float)$visit["now"];
        $elapsed = max(0.0, $now - $last);

        if ($elapsed <= $fast_window)
            $fast_visit_count = $old_fast_visit_count + 1;
        else if ($elapsed <= $cooldown)
        {
            $decay = max(1, (int)floor($elapsed / $fast_window));
            $fast_visit_count = max(1, $old_fast_visit_count - $decay) + 1;
        }
        else
            $fast_visit_count = 1;

        if (trace_should_report($old_fast_visit_count, $fast_visit_count, $threshold, $report_every))
        {
            add_log(
                defined("REPORT") ? REPORT : "6",
                trace_report_message($fast_visit_count, $ip, $id_user),
                1
            );
        }

        $Database->query("
            UPDATE trace
            SET last_visit = NOW(6),
                fast_visit_count = $fast_visit_count,
                visit_count = $visit_count
            WHERE id = $id
        ");
    }
    $Database->query("
        DELETE FROM trace WHERE last_visit < DATE_SUB(NOW(), INTERVAL $retention_days DAY)
    ");
}
