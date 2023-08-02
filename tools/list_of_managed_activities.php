<?php

function sort_session($a, $b)
{
    return (date_to_timestamp($a["begin_date"]) - date_to_timestamp($b["begin_date"]));
}

function sort_activities($a, $b)
{
    if ($a["pickup_date"])
	$ldate = $a["pickup_date"];
    else if ($a["close_date"])
	$ldate = $a["close_date"];
    else
	$ldate = 0;
    if ($b["pickup_date"])
	$rdate = $b["pickup_date"];
    else if ($a["close_date"])
	$rdate = $b["close_date"];
    else
	$rdate = 0;
    if ($ldate)
	$ldate = date_to_timestamp($ldate);
    if ($rdate)
	$rdate = date_to_timestamp($rdate);
    return ($ldate - $rdate);
}

function list_of_managed_activities(&$usr, $matter = true, $activ = true, $sess = true, $actid = 0)
{
    global $User;
    global $Language;

    if ($actid != 0)
	$actid = " AND activity.id = $actid ";
    else
	$actid = "";
    
    $adm = "";
    if (is_admin())
	$adm = " OR 1 ";
    
    $id = $usr->id;
    if (!$matter)
	$matters = [];
    else
	$matters = db_select_all("
          laboratory.codename as laboratory_codename,
          laboratory.id as id_laboratory,
          laboratory.{$Language}_name as laboratory_name,
          user_laboratory.authority as authority,
          activity.codename as activity_codename,
          activity.codename as codename,
          activity.type as type,
          activity.parent_activity as parent_activity,
          activity.id as id_activity,
          activity.pickup_date as pickup_date,
          activity.close_date as close_date,
          activity.{$Language}_name as activity_name,
          activity.{$Language}_name as name,
          activity.is_template as is_template,
          activity_teacher.teacher_pay as teacher_pay,
          activity_teacher.assistant_pay as assistant_pay
          FROM activity
          LEFT JOIN activity_teacher ON activity_teacher.id_activity = activity.id
          LEFT JOIN laboratory ON activity_teacher.id_laboratory = laboratory.id
          LEFT JOIN user_laboratory ON user_laboratory.id_laboratory = laboratory.id
          WHERE (activity.parent_activity IS NULL OR activity.parent_activity = -1)
          AND activity.deleted IS NULL
          AND activity.is_template = 0
          AND (activity_teacher.id_user = $id OR user_laboratory.id_user = $id $adm)
          $actid
        ", "activity_codename");

    if (!$activ)
	$activities = [];
    else
	$activities = db_select_all("
          laboratory.codename as laboratory_codename,
          laboratory.id as id_laboratory,
          laboratory.{$Language}_name as laboratory_name,
          user_laboratory.authority as authority,
          activity.codename as activity_codename,
          activity.codename as codename,
          activity.type as type,
          activity.parent_activity as parent_activity,
          activity.id as id_activity,
          activity.{$Language}_name as activity_name,
          activity.{$Language}_name as name,
          activity.is_template as is_template,
          activity_teacher.teacher_pay as teacher_pay,
          activity_teacher.assistant_pay as assistant_pay
          FROM activity
          LEFT JOIN activity_teacher ON activity_teacher.id_activity = activity.id
          LEFT JOIN laboratory ON activity_teacher.id_laboratory = laboratory.id
          LEFT JOIN user_laboratory ON user_laboratory.id_laboratory = laboratory.id
          WHERE (activity.parent_activity IS NOT NULL AND activity.parent_activity != -1)
          AND activity.deleted IS NULL
          AND activity.is_template = 0
          AND (activity_teacher.id_user = $id OR user_laboratory.id_user = $id $adm)
          $actid
        ", "activity_codename");
 
    foreach ($matters as $mat)
    {
	$act = db_select_all("
           activity.id as id_activity,
           activity.close_date as close_date,
           activity.pickup_date as pickup_date,
	   activity.codename as activity_codename,
	   activity.codename as codename,
           activity.type as type,
	   activity.{$Language}_name as activity_name,
	   activity.{$Language}_name as name,
	   activity.parent_activity as parent_activity
           FROM activity
           WHERE parent_activity = {$mat["id_activity"]}
           AND activity.deleted IS NULL
	   ", "activity_codename");
	foreach ($act as $k => $v)
	{
	    if (!isset($activities[$k]) || ($activities[$k]["authority"] < $mat["authority"]))
	    {
		$v["matter_name"] = $mat["name"];
		$v["matter_codename"] = $mat["codename"];
		$v["is_template"] = 0;
		$activities[$k] = $v;
	    }
	}
    }
    foreach ($activities as &$act)
    {
	if (!$sess)
	    $act["session"] = [];
	else
	    $act["session"] = db_select_all("
               id as id_session, begin_date, end_date
               FROM session WHERE id_activity = {$act["id_activity"]}
               AND session.deleted IS NULL
	       ORDER BY begin_date
	       ");
    }
    $all = array_merge($matters, $activities);
    foreach ($all as $a)
    {
	if (!isset($a["authority"]) || $a["authority"] == NULL || $a["authority"] >= 2)
	    $usr->teacher = true;
	else
	    $usr->assistant = true;
    }
    $ses = [];
    $misc = [];
    foreach ($all as $a)
    {
	if (isset($a["session"]))
	{
	    foreach ($a["session"] as $s)
	    {
		$ses[] = array_merge($a, $s);
	    }
	}
	else
	    $misc[] = $a;
    }
    uasort($misc, "sort_activities");
    uasort($ses, "sort_session");
    return (["activities" => $misc, "sessions" => $ses]);
}
