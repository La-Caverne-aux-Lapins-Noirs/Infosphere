<?php

const USER_LOG_SSH_IDLE = -2;
const USER_LOG_LOCK = -1;
const USER_LOG_INTRA = 0;
const USER_LOG_WORK = 1;
const USER_LOG_DISTANT = 2;

function user_log_valid_activity_types()
{
    return ([USER_LOG_INTRA, USER_LOG_WORK, USER_LOG_DISTANT]);
}

function user_log_sql_type_list($types)
{
    if (!is_array($types))
        $types = [$types];
    $out = [];
    foreach ($types as $type)
        $out[] = (int)$type;
    return (implode(", ", array_unique($out)));
}

function compute_student_log($user = NULL, $type = USER_LOG_INTRA, $date = NULL, $client_ip = NULL, $distant = false)
{
    global $OriginalUser;
    global $Database;

    $type = (int)$type;
    $client_ip = $Database->real_escape_string($client_ip);
    if ($date === NULL)
	$date = now();
    if ($user == NULL)
	if (($user = &$OriginalUser) == NULL)
	    return ;
    $today = db_select_one("
      * FROM user_log
      WHERE id_user = {$user["id"]}
      AND log_date >= '".db_form_date($date, true)."'
      AND log_date < '".db_form_date($date + 60 * 60 * 24, true)."'
      AND type = $type
	");
    if ($today == NULL)
    {
	if ($client_ip === NULL)
	    $client_ip = get_client_ip();
	$Database->query("
           INSERT INTO user_log
           (id_user, log_date, last_log, last_ip, duration, type)
           VALUES ({$user["id"]}, '".db_form_date($date, true)."', NOW(), '$client_ip', 0, $type)
	   ");
	return ;
    }
    $last_log = date_to_timestamp($today["last_log"]);
    if ($today["last_ip"] != $client_ip || $date - $last_log > 5 * 60)
    {
	$Database->query("
          UPDATE user_log
          SET last_log = NOW(), last_ip = '$client_ip'
          WHERE id_user = {$user["id"]}
          AND log_date >= '".db_form_date($date, true)."'
          AND log_date < '".db_form_date($date + 60 * 60 * 24, true)."'
          AND type = $type
	  ");
	return ;
    }
    if ($date < $last_log)
	$acc = $today["duration"];
    else
	$acc = $today["duration"] + $date - $last_log;
    $Database->query("
       UPDATE user_log
       SET last_log = NOW(), duration = $acc
       WHERE id_user = {$user["id"]}
       AND log_date >= '".db_form_date($date, true)."'
       AND log_date < '".db_form_date($date + 60 * 60 * 24, true)."'
       AND type = $type
    ");
}

function get_student_log($user = NULL, $date = NULL, $types = NULL)
{
    global $OriginalUser;

    if ($user == NULL)
	if (($user = &$OriginalUser) == NULL)
	    return ;
    if ($date == NULL)
	$date = now();
    else
	$date = date_to_timestamp($date);
    if ($types === NULL)
        $types = user_log_valid_activity_types();
    $types = user_log_sql_type_list($types);
    $fetch = db_select_one("
       COALESCE(SUM(duration), 0) as duration FROM user_log
       WHERE id_user = {$user["id"]}
       AND log_date >= '".db_form_date($date, true)."'
       AND log_date < '".db_form_date($date + 60 * 60 * 24, true)."'
       AND type IN ($types)
       ");
    if ($fetch == NULL)
	return (0);
    return ($fetch["duration"]);
}

function get_week_average($user, $since = 60 * 60 * 24 * 14)
{
    // Cette fonction ne devrait pas accumuler intra et travail mais établir
    // un ensemble basé sur le temps seulement passé.

    if (is_array($user))
	$user = $user["id"];
    else if (is_object($user))
	$user = $user->id;
    else if (!is_number($user))
	return ;

    $end = now();
    $start = $end - $since;
    $end = db_form_date($end);
    $start = db_form_date($start);
    $types = user_log_sql_type_list(user_log_valid_activity_types());

    $query = db_select_all("
  log_date, duration FROM user_log WHERE type IN ($types) AND id_user = $user
  AND log_date >= '$start' AND log_date <= '$end'
    ");

    $total = 0;
    foreach ($query as $vv)
	$total += $vv["duration"] / (60 * 60);
    return ($total);
}

function get_last_activities_report($user, $since = 60 * 60 * 24 * 14)
{
    $act = collect_dashboard_activities(now() - $since, now(), false);
    $pres = 0;
    $total = 0;
    foreach ($act["participate"] as $a)
    {
	if ($a["present"] > 0 || $a["present"] == -1)
	    $pres += 1;
	$total += 1;
    }
    if ($total == 0)
	return ([0.5, 0, 1]);
    return ([$pres, 0, $total]);
}

function get_last_medals_report($user, $since = 60 * 60 * 24 * 14)
{
    $act = collect_dashboard_activities(now() - $since, now(), false);
    $prj = collect_dashboard_projects(now(), $since, true);

    $medal = 0;
    $total = 0;
    foreach ($act["participate"] as $a)
    {
	$medal += $a["medal_got"];
	$total += $a["medal"];
    }
    foreach ($prj as $a)
    {
	$medal += $a["medal_got"];
	$total += $a["medal"];
    }
    if ($total == 0)
	return ([0.5, 0, 1]);
    return ([$medal, 0, $total]);
}

