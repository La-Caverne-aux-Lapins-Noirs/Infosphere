<?php

function fetch_cycle($type = "cycle", $id = -1, $by_name = false, $fulluser = false, $activities = true)
{
    global $Language;
    global $one_week;

    if (($years = fetch_data(
	"cycle", $id, ["name"], "codename", $by_name, true, true, ["is_template" => $type == "cursus"], [
	    "cycle.done ASC",
	    "SUBSTRING(cycle.codename, 1, 4) ASC", // En SQL, l'index 0 est l'index 1... WTF.
	    "cycle.cycle ASC" // "SUBSTRING(cycle.codename, 1, 8) ASC, cycle.cycle ASC"
    ])
    )->is_error())
	return ([]);
    $years = $years->value;
    foreach ($years as $i => &$v)
    {
	if ($v["is_template"])
	{
	    $v["instance"] = [];
	    $tmp = db_select_all("
               id, codename, first_day FROM cycle WHERE id_template = {$v["id"]}
	       ");
	    foreach ($tmp as $inst)
	    {
		$inst["last_day"] = date_to_timestamp($inst["first_day"]) + 15 * $one_week;
		if ($inst["last_day"] > now() + 60 * 60 * 24 * 10)
		{
		    $inst["last_day"] = db_form_date($inst["last_day"]);
		    $v["instance"][] = $inst;
		}
	    }
	}
	else
	{
	    if (@$v["id_template"] && ($d = db_select_one("codename FROM cycle WHERE id = {$v["id_template"]}")))
		$v["codename_template"] = $d["codename"];
	    else
		$v["codename_template"] = NULL;
	}
	$v["last_day"] = date_to_timestamp($years[$i]["first_day"]) + 15 * $one_week;
	$v["last_day"] = db_form_date($v["last_day"]);
	$teacher = db_select_all("
            user.id as id_user,
            user.codename as codename_user,
            laboratory.id as id_laboratory,
            laboratory.codename as codename_laboratory
            FROM cycle_teacher
            LEFT JOIN user ON cycle_teacher.id_user = user.id
            LEFT JOIN laboratory ON cycle_teacher.id_laboratory = laboratory.id
            WHERE cycle_teacher.id_cycle = ".$v["id"]."
	    ", $by_name ? ["codename_user", "codename_laboratory"] : "");
	$v["school"] = db_select_all("
	    school.id as id_school,
            school.id as id,
            school.codename as codename,
            school.{$Language}_name as name
            FROM school_cycle
            LEFT JOIN school ON school_cycle.id_school = school.id
            WHERE school_cycle.id_cycle = ".$v["id"]."
            AND deleted IS NULL
	", $by_name ? "codename" : "");
	$v["teacher"] = [];
	foreach ($teacher as $t)
	{
	    $nod = [];
	    if (isset($t["codename_laboratory"]))
	    {
		$nod["id"] = $t["id_laboratory"];
		$nod["laboratory"] = $nod["codename"] = "#".$t["codename_laboratory"];
	    }
	    else
	    {
		$nod["id"] = $t["id_user"];
		$nod["teacher"] = $nod["codename"] = $t["codename_user"];
	    }
	    $v["teacher"][] = $nod;
	}
	$v["user"] = db_select_all("
            user.id as id,
            user.id as id_user,
            user.codename as codename,
            user_cycle.hidden as hidden,
	    user_cycle.cursus as cursus
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
              activity.id as id,
              activity.id as id_activity,
              activity.is_template as is_template,
              activity_cycle.id as id_activity_cycle,
              activity_cycle.cursus as cursus,
              activity.codename as codename,
              activity.emergence_date as emergence_date,
              activity.subscription as subscription,
	      activity.credit_d,
              activity.credit_a,
              template.{$Language}_name as name
              FROM activity_cycle
              LEFT JOIN activity ON activity_cycle.id_activity = activity.id
              LEFT JOIN activity as template ON activity.id_template = template.id
              WHERE activity_cycle.id_cycle = ".$v["id"]."
                AND activity.deleted IS NULL
                AND activity.disabled IS NULL
              ORDER BY codename
	      ");
	    $v["min_credit"] = 0;
	    $v["max_credit"] = 0;
	    foreach ($v["activity"] as $act)
	    {
		$v["min_credit"] += $act["credit_d"];
		$v["max_credit"] += $act["credit_a"];
	    }
	}
    }
    if ($id != -1 && 0)
	return ($years[array_key_first($years)]);
    return ($years);
}


