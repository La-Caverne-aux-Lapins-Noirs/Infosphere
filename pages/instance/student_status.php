<?php

function export_student_status($instance, $session = -1, $export = false)
{
    if ($session != -1)
	$users = db_select_all("
           user.codename, team.present as present FROM team
           LEFT JOIN user_team ON team.id = user_team.id_team
           LEFT JOIN user ON user_team.id_user = user.id
           WHERE team.id_session = $session
	");
    else
	$users = db_select_all("
           user.codename, team.present as present FROM team
           LEFT JOIN user_team ON team.id = user_team.id_team
           LEFT JOIN user ON user_team.id_user = user.id
           WHERE team.id_instance = $instance
	");

    if ($export == false)
    {
	export_csv($users);
    }
    else
    {
	/// A PROGRAMMER
    }
}
