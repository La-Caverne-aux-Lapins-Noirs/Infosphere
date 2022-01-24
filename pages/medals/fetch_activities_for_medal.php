<?php

require_once (__DIR__."/../fetch_activities.php");

function fetch_activities_for_medal($id, $by_name = false)
{
    global $Database;
    global $Language;
    global $User;

    if (($id = resolve_codename("medal", $id, "codename"))->is_error())
	return ($id);
    $id = $id->value;

    $lng = forge_language_fields(["name", "description"], true, true);
    $ret = db_select_one("*, $lng FROM medal WHERE id = $id");
    $lng = forge_language_fields(["name", "description"], true, true, "activity");

    $cyc = ["0"];
    foreach ($User["cycle"] as $c)
    {
	$cyc[] = "activity_cycle.id_cycle = ".$c["id"];
    }
    $cycles = implode(" OR ", $cyc);
    $modules = db_select_all("
	activity.id FROM activity_cycle
	LEFT JOIN activity ON activity.id = activity_cycle.id_activity
	WHERE activity.type = 18 AND parent_activity = -1
	AND ( $cycles )
    ");

    // Les activités portant cette médaille
    $tmp = db_select_all("
      activity.*, $lng FROM activity
      LEFT JOIN activity_medal ON activity_medal.id_activity = activity.id
      WHERE activity_medal.id_medal = $id AND activity.deleted = 0
    ", $by_name ? "codename" : "");
    $ret["activity"] = [];
    $index = 0;
    foreach ($tmp as $i => $v)
    {
	if (is_admin())
	{
	    $ret["activity"][$index++] = db_select_one("
                       *, {$Language}_name as name, {$Language}_description as description
                       FROM activity WHERE id = ".$v["id"]."
		       ");
	    continue ;
	}
	foreach ($modules as $mod)
	{
	    $act = db_select_all("* FROM activity WHERE parent_activity = ".$mod["id"]);
	    foreach ($act as $a)
	    {
		if ($a["id"] == $v["id"])
		{
		    $ret["activity"][$index++] = db_select_one("
                       *, {$Language}_name as name, {$Language}_description as description
                       FROM activity WHERE id = ".$v["id"]."
		       ");
		    break 2;
		}
	    }
	}
    }

    // Les utilisateurs porteurs de cette médaille
    $ret["user"] = db_select_all("
            user.id, user.codename, COUNT(activity_user_medal.id) as got
            FROM user_medal
            LEFT JOIN user ON user_medal.id_user = user.id
            LEFT JOIN activity_user_medal ON user_medal.id = activity_user_medal.id_user_medal
            WHERE user_medal.id_medal = $id AND activity_user_medal.result > 0
              AND user.authority >= 0
            GROUP BY user.id
    ", $by_name ? "codename" : "");
    return (new ValueResponse($ret));
}



