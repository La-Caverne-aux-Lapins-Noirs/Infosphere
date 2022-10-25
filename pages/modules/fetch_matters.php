<?php

function fetch_matters($id_user, $id_cycle)
{
    global $Language;

    $act = db_select_all("
      activity.id, activity.{$Language}_name as name,
      template.{$Language}_name as tname,
      activity.codename,
      template.codename as tcodename
      FROM activity
      LEFT JOIN activity as template ON template.id = activity.id_template
      LEFT JOIN activity_cycle ON activity.id = activity_cycle.id_activity
      LEFT JOIN team ON activity.id = team.id_activity
      LEFT JOIN user_team ON user_team.id_team = team.id
        AND user_team.id_user = $id_user
      WHERE activity_cycle.id_cycle = $id_cycle
      AND activity.parent_activity = -1
      GROUP BY activity.id
      ORDER BY
        CASE WHEN user_team.id IS NULL
          THEN 0 ELSE 1 END,
      activity.{$Language}_name ASC
    ", "id");
    $vities = [];
    foreach ($act as $a)
    {
	$vities[$a["id"]] = [
	    "id" => $a["id"],
	    "name" => strlen($a["name"]) ? $a["name"] : $a["tname"],
	    "codename" => strlen($a["tcodename"]) ? $a["tcodename"] : $a["codename"],
	];
    }
    return ($vities);
}

