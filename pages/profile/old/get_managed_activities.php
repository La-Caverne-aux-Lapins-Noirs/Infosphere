<?php

function get_managed_session($user)
{
    global $Language;

    $all = db_select_all("
       laboratory.{$Language}_name as laboratory_name,
       activity.id as id,
       activity.{$Language}_name as activity_name,
       activity.codename as activity_codename,
       pactivity.codename as pactivity_codename,
       pactivity.{$Language}_name as pactivity_name,
       pactivity.id as id_pactivity,
       session.id as id_session,
       session.begin_date as begin_date,
       session.end_date as end_date,
       TIMESTAMPDIFF(SECOND, session.begin_date, session.end_date) as duration
       FROM activity_teacher as teacher
       LEFT JOIN activity ON teacher.id_activity = activity.id
       LEFT JOIN session ON session.id_activity = activity.id
       LEFT JOIN activity as pactivity ON activity.parent_activity = pactivity.id
       LEFT JOIN laboratory ON laboratory.id = teacher.id_laboratory
       LEFT JOIN user_laboratory ON user_laboratory.id_laboratory = laboratory.id
       WHERE teacher.id_user = $user OR user_laboratory.id_user = $user
       GROUP BY session.id
       ORDER BY session.begin_date DESC
    ");
    foreach ($all as &$a)
    {
	$a["cycle"] = db_select_all("
          cycle.codename as cycle,
          cycle.id as id
          FROM activity_cycle
          LEFT JOIN cycle ON activity_cycle.id_cycle = cycle.id
          WHERE activity_cycle.id_activity = ".$a["id_session"]."
	  ");
    }
    return ($all);
}

function get_managed_instances($user)
{
    return ([]);
}
