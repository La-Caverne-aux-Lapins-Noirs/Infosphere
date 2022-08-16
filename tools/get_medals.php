<?php

function get_all_sub_instance($instance)
{
    $sub = db_select_all("id FROM instance WHERE parent_instance = $instance");
    foreach ($sub as $d)
    {
	$new_sub = get_all_sub_instance($d["id"]);
	$sub = array_merge($sub, $new_sub);
    }
    return (array_unique($sub, SORT_NUMERIC));
}

function get_medal_status($user, $instance)
{
    global $Language;

    return (db_select_all("
       medal.id as id, medal.codename as codename,
       medal.{$Language}_name as name,
       medal.{$Language}_description as description,
       medal.icon as icon,
       medal.type as type,
       instance_user_medal.result as result,
       activity_medal.local as local,
       activity_medal.grade_a as grade_a,
       activity_medal.grade_b as grade_b,
       activity_medal.grade_c as grade_c,
       activity_medal.bonus as bonus,
       COUNT(CASE WHEN instance_user_medal.result >= 0 THEN 1 END) as success,
       COUNT(CASE WHEN instance_user_medal.result = -1 THEN 1 END) as failure
       FROM user_medal
       LEFT JOIN medal ON user_medal.id_medal = medal.id
       LEFT JOIN instance_user_medal ON user_medal.id = instance_user_medal.id_user_medal
       LEFT JOIN instance ON instance.id = instance_user_medal.id_instance
       LEFT JOIN activity_medal ON activity_medal.id_medal = medal.id AND activity_medal.id_activity = instance.id_activity
       WHERE user_medal.id_user = $user
       AND instance_user_medal.id_instance = $instance AND instance.id = $instance
       GROUP BY user_medal.id_medal
    ", "id"));
}

function get_module_medals($user, $instance, $activity)
{
    global $Language;

    $medals = [];
    $medals = db_select_all("
       medal.id as id, medal.codename as codename,
       medal.{$Language}_name as name,
       medal.{$Language}_description as description,
       medal.icon as icon,
       medal.type as type,
       activity_medal.grade_a as grade_a,
       activity_medal.grade_b as grade_b,
       activity_medal.grade_c as grade_c,
       activity_medal.bonus as bonus,
       activity_medal.local as local
       FROM medal
       LEFT JOIN activity_medal ON medal.id = activity_medal.id_medal
       LEFT JOIN instance ON activity_medal.id_activity = instance.id_activity
       LEFT JOIN instance_school_year ON instance.id = instance_school_year.id_instance
       LEFT JOIN user_school_year ON user_school_year.id_school_year = instance_school_year.id_school_year
       WHERE user_school_year.id_user = $user AND instance.id = $instance AND medal.deleted = 0
    ", "id");
    if ($medals == [])
	return ([]);
    /*
    $sub = get_all_sub_instance($user, $activity);
    foreach ($sub as $d)
    {
	if (!isset($medals[$med["id"]]))
	    continue ;
	if (!isset($medals[$med["id"]]["status"]))
	    $medals[$med["id"]]["status"] = get_medal_status($user, $d);
	else
	{
	    $x = get_medal_status($user, $d);
	    $medals[$med["id"]]["status"]["success"] += $x["success"];
	    $medals[$med["id"]]["status"]["failure"] += $x["failure"];
	}
    }
    */
    return ($medals);
}

