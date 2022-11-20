<?php

function compute_student_log($user = NULL, $type = 0, $date = NULL, $client_ip = NULL)
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
    if ($today["last_ip"] != $client_ip || $date - $last_log > 30 * 60)
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

