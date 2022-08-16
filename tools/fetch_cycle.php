<?php

function fetch_cycle($id = -1, $by_name = false, $fulluser = false, $activities = true)
{
    global $Language;

    if (($years = fetch_data(
	"cycle", $id, [], "codename", $by_name, true, true, [], [
	    // En SQL, l'index 0 est l'index 1... WTF.
	    "cycle.done ASC, SUBSTRING(cycle.codename, 1, 8) ASC, cycle.cycle ASC"
	    // "SUBSTRING(cycle.codename, 1, 8) ASC, cycle.cycle ASC"
    ])
    )->is_error())
	return ([]);
    $years = $years->value;
    foreach ($years as $i => &$v)
    {
	$v["teacher"] = db_select_all("
            user.id as id_user,
            user.codename as codename_user,
            laboratory.id as id_laboratory,
            laboratory.codename as codename_laboratory
            FROM cycle_teacher
            LEFT JOIN user ON cycle_teacher.id_user = user.id
            LEFT JOIN laboratory ON cycle_teacher.id_laboratory = laboratory.id
            WHERE cycle_teacher.id_cycle = ".$v["id"]."
	    ", $by_name ? "codename" : "");
	$v["user"] = db_select_all("
            user.id as id,
            user.codename as codename,
            user_cycle.hidden as hidden
            FROM user_cycle
            LEFT JOIN user ON user_cycle.id_user = user.id
            WHERE user_cycle.id_cycle = ".$v["id"]."
	    ", $by_name ? "codename" : "");
	if ($fulluser)
	{
	    foreach ($v["user"] as &$usr)
	    {
		$usr = get_full_profile($usr, ["laboratory", "teacher", "module"]);
	    }
	}
	if ($activities)
	{
	    $v["activity"] = db_select_all("
              activity.id as id, activity_cycle.id as id_activity_cycle,
              activity.codename as codename, activity.emergence_date as emergence_date,
              activity.subscription as subscription,
              template.{$Language}_name as name
              FROM activity_cycle
              LEFT JOIN activity ON activity_cycle.id_activity = activity.id
              LEFT JOIN activity as template ON activity.id_template = template.id
              WHERE activity_cycle.id_cycle = ".$v["id"]."
                AND activity.deleted = 0
                AND activity.parent_activity = -1
                AND activity.is_template = 0
	      ");
	    foreach ($v["activity"] as &$act)
	    {
		$act["user"] = db_select_all("
                  user.codename as codename FROM team
                  LEFT JOIN user_team ON team.id = user_team.id_team
                  LEFT JOIN user ON user_team.id_user = user.id
                  WHERE team.id_activity = {$act["id"]}
	      ");
	    }
	}
    }
    if ($id != -1)
	return ($years[array_key_first($years)]);
    return ($years);
}


