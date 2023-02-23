<?php

function get_activity_medal_for_user(&$student, $idact)
{
    global $Language;

    $student["medal"] = db_select_all("
	medal.*,
        activity_user_medal.id as id_activity_user_medal,
        {$Language}_name as name,
        {$Language}_description as description,
        activity_user_medal.result as result
	FROM activity_user_medal
        LEFT JOIN user_medal ON activity_user_medal.id_user_medal = user_medal.id
        LEFT JOIN medal ON medal.id = user_medal.id_medal
	WHERE activity_user_medal.id_activity = $idact
          AND user_medal.id_user = {$student["id"]}
          AND medal.deleted IS NULL
	  ");
    foreach ($student["medal"] as &$med)
	$med["icon"] = "/genicon.php?function=".$med["codename"];
    return ($student);
}

