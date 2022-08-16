<?php

function get_instance_medal($user, $inst)
{
    global $Language;

    $tab = db_select_all("
       medal.id as id,
       medal.icon as icon,
       medal.codename as codename,
       medal.{$Language}_name as name,
       medal.{$Language}_description as description,
       instance_user_medal.id as id_instance_user_medal,
       instance_user_medal.result as result,
       instance_user_medal.result as count,
       activity_medal.local as local,
       activity_medal.grade_a as grade_a,
       activity_medal.grade_b as grade_b,
       activity_medal.grade_c as grade_c,
       activity.parent_activity as parent_activity,
       user_medal.id as id_user_medal
       FROM medal
       LEFT JOIN user_medal ON medal.id = user_medal.id_medal
       LEFT JOIN instance_user_medal ON user_medal.id = instance_user_medal.id_user_medal
       LEFT JOIN instance ON instance_user_medal.id_instance = instance.id
       LEFT JOIN activity ON instance.id_activity = activity.id
       LEFT JOIN activity_medal ON activity.id = activity_medal.id AND activity_medal.id_medal = medal.id
       WHERE user_medal.id_user = $user AND instance_user_medal.id_instance = $inst
       ORDER BY medal.{$Language}_name ASC
    ", "codename");

    foreach ($tab as &$t)
    {
	$pm = db_select_one("
           *
           FROM activity_medal
           WHERE id_medal = ".$t["id"]." AND id_activity = ".$t["parent_activity"]."
	");
	if ($pm == NULL || $pm["mandatory"] == NULL)
	    continue ;
	$t["parent_mandatory"] = $pm["mandatory"];
    }
    return ($tab);
}

