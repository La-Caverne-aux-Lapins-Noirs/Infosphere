<?php

function get_activity_medal_for_user(&$student, $idact, $get_failed = true)
{
    global $Language;

    if (!$get_failed)
	$get_failed =  " AND result = 1 ";
    else
	$get_failed = "";
    $student["medal"] = db_select_all("
	medal.*,
        medal.{$Language}_name as name,
        medal.{$Language}_description as description,
        user_medal.id as id_user_medal,
        user_medal.result,
        user_medal.strength,
        user_medal.id_team,
        user_medal.id_user_team,
        user_medal.insert_date
	FROM user_medal
        LEFT JOIN medal ON medal.id = user_medal.id_medal
	WHERE user_medal.id_activity = $idact
          AND user_medal.id_user = {$student["id"]}
          AND medal.deleted IS NULL
          $get_failed
        GROUP BY user_medal.id_medal
	  ");
    foreach ($student["medal"] as &$med)
	$med["icon"] = "/genicon.php?function=".$med["codename"];
    return ($student);
}

