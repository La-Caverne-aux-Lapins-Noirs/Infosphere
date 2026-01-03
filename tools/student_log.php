<?php

function compute_student_log($user = NULL, $type = 0, $date = NULL, $client_ip = NULL, $distant = false)
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

function get_student_log($user = NULL, $date = NULL)
{
    global $OriginalUser;
    global $Database;

    if ($user == NULL)
	if (($user = &$OriginalUser) == NULL)
	    return ;
    if ($date == NULL)
	$date = now();
    else
	$date = date_to_timestamp($date);
    $fetch = db_select_one("
       * FROM user_log
       WHERE id_user = {$user["id"]}
       AND log_date >= '".db_form_date($date, true)."'
       AND log_date < '".db_form_date($date + 60 * 60 * 24, true)."'
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

    $intra_logs = [];
    $query = db_select_all("
  log_date, duration FROM user_log WHERE type = 0 AND id_user = $user
  AND log_date >= '$start' AND log_date <= '$end'
    ", "log_date");
    foreach ($query as $kk => $vv)
	$intra_logs[date_to_timestamp($kk) / 60 / 60 / 24] = $vv["duration"] / (60 * 60);

    $work_logs = [];
    $query = db_select_all("
  log_date, duration FROM user_log WHERE type = 1 AND id_user = $user
  AND log_date >= '$start' AND log_date <= '$end'
    ", "log_date");
    foreach ($query as $kk => $vv)
	$work_logs[date_to_timestamp($kk) / 60 / 60 / 24] = $vv["duration"] / (60 * 60);

    $distant_logs = [];
    $query = db_select_all("
  log_date, duration FROM user_log WHERE type = 2 AND id_user = $user
  AND log_date >= '$start' AND log_date <= '$end'
    ", "log_date");
    foreach ($query as $kk => $vv)
	$distant_logs[date_to_timestamp($kk) / 60 / 60 / 24] = $vv["duration"] / (60 * 60);

    $total = 0;
    foreach ($intra_logs as $l)
	$total += $l;
    foreach ($work_logs as $l)
	$total += $l;
    foreach ($distant_logs as $l)
	$total += $l;
    return ($total);
}

function get_last_activities_report($user, $since = 60 * 60 * 24 * 14)
{
    $act = collect_dashboard_activities(time() - $since, time(), false);
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
    $act = collect_dashboard_activities(time() - $since, time(), false);
    $prj = collect_dashboard_projects(time(), $since, true);

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

