<?php

function get_registered_teams($inst, $session = NULL)
{
    global $Database;
    global $Language;

    if (isset($inst["id"]))
	$inst = $inst["id"];
    if ($session != NULL)
	$session = " AND session.id = ".$session["id_session"];
    else
	$session = "";

    $teams = db_select_all("
      team.id as id,
      team.team_name as team_name,
      team.id_session as id_session,
      team.present as present,
      team.commentaries as commentaries
      FROM team
        LEFT JOIN instance ON team.id_instance = instance.id
        LEFT JOIN session ON instance.id = session.id_instance -- AND team.id_session = session.id
      WHERE instance.id = $inst $session
      GROUP BY team.id
    ");
    foreach ($teams as $i => $v)
	$teams[$i]["user"] = db_select_all("
          user.id as id,
          user.codename as codename,
          user.avatar as avatar,
          user_team.id as user_team_id,
          user_team.status as status,
          user_team.commentaries as commentaries
          FROM user_team
          LEFT JOIN user ON user_team.id_user = user.id
          WHERE user_team.id_team = ".$v["id"]."
	  ", "id");
    return ($teams);
}

