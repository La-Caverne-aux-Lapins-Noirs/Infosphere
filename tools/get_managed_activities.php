<?php

function get_managed_activities($user)
{
    global $ActivityType;

    $start = first_day_of_week(time());
    $today = first_second_of_day(time());
    $stop = $start + 60 * 60 * 24 * 7 * 2;

    $act = [];
    $activities = db_select_all("
       *
       FROM session
       WHERE begin_date >= $start AND end_date <= $stop
    ");
    foreach ($activities as $session)
    {
	$activity = new FullActivity;
	$activity->build($session["id_activity"], false, false, $session["id"], NULL, $user);
	if ($ActivityType[$activity->type]["type"] != 2)
	    continue ;
	$act[] = [
	    "pickup_date" => $activity->pickup_date,
	    "name" => $activity->name,
	    "id" => $activity->id,
	    "soon" => $activity->unique_session->begin_date > $today
		 && $activity->unique_session->end_date < $today + 60 * 60 * 24,
	    "week" => $activity->unique_session->begin_date > $start
		 && $activity->unique_session->end_date < $start + 60 * 60 * 24 * 7,
	];
    }
    return ($act);
}

function get_all_managed_activities($user)
{
    $out = [];
    $act = db_select_all("
       activity.id as id
       FROM activity_teacher
       LEFT JOIN activity
         ON activity_teacher.id_activity = activity.id
       LEFT JOIN user_laboratory
         ON activity_teacher.id_laboratory = user_laboratory.id_laboratory
       WHERE (user_laboratory.id_user = {$user["id"]}
          OR activity_teacher.id_user = {$user["id"]})
         AND activity.deleted = 0
	  ");
    foreach ($act as $a)
    {
	$out[] = $a["id"];
	$sub = db_select_all("
	    activity.id as id
            FROM activity
            WHERE parent_activity = {$a["id"]}
	    ");
	foreach ($sub as $s)
	{
	    $out[] = $s["id"];
	}
    }
    return ($out);
}
