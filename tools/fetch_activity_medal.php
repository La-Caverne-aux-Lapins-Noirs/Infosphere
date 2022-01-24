<?php

function fetch_activity_medal($id, $by_name = false) // id_activity
{
    global $Language;

    $all = db_select_all("
       medal.*,
       medal.{$Language}_name as name,
       medal.{$Language}_description as description,
       activity_medal.mandatory as mandatory,
       activity_medal.grade_a as grade_a,
       activity_medal.grade_b as grade_b,
       activity_medal.grade_c as grade_c,
       activity_medal.bonus as bonus,
       activity_medal.local as local
       FROM activity_medal
       LEFT JOIN medal ON activity_medal.id_medal = medal.id
       WHERE activity_medal.id_activity = $id AND deleted = 0
       ORDER BY medal.codename ASC
    ", $by_name ? "codename" : "");

    if (($template = db_select_one("
       id_template, medal_template FROM activity WHERE id = $id
    ")) != NULL)
    {
	if ($template["id_template"] != -1 && $template["medal_template"])
	{
	    $all = array_merge($all, fetch_activity_medal(
		$template["id_template"], $by_name
	    ));
	}
    }
    return ($all);
}

