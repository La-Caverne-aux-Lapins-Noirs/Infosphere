<?php

function everybody($id = -1)
{
    return (true);
}

function logged_in($id = -1)
{
    global $User;

    return ($User != "");
}

function is_me($id)
{
    global $User;

    return ($User != NULL && $User["id"] == $id);
}

function is_assistant($usr = NULL)
{
    return (is_teacher(NULL, $usr, 1));
}

function is_teacher($id = NULL, $usr = NULL, $lvl = 2)
{
    global $User;

    if (!$User && $usr == NULL)
	return (false);
    if ($usr == NULL)
	$usr = $User;
    if (is_admin())
	return (true);
    $ret = db_select_one("
	activity_teacher.id
        FROM activity_teacher
	LEFT JOIN user_laboratory
        ON user_laboratory.id_laboratory = activity_teacher.id_laboratory
        AND user_laboratory.authority >= $lvl
	WHERE activity_teacher.id_user = {$usr["id"]}
	OR user_laboratory.id_user = {$usr["id"]}
	");
    return (!!$ret);
}

function is_assistant_for_activity($id, $activity = NULL)
{
    global $User;
    
    if (is_admin())
	return (true);
    if ($activity == NULL)
	($activity = new FullActivity)->build($id, false, false);
    return ($activity->is_assistant);
}

function is_assistant_for_team($id, $activity = NULL)
{
    global $User;

    $id = (int)$id;
    if (!($act = db_select_one("id_activity FROM team WHERE id = $id")))
	return (false);
    if (is_admin())
	return (true);
    ($activity = new FullActivity)->build($act["id_activity"], false, false);
    return ($activity->is_assistant);
}

function is_assistant_for_session($id)
{
    global $Database;
    global $User;
    
    if (is_admin())
	return (true);
    if (($ida = db_select_one("id_activity FROM session WHERE id = $id")) == NULL)
	return (false);
    $ida = $ida["id_activity"];
    ($activity = new FullActivity)->build($ida, false, false);
    return ($activity->is_assistant);
}

function is_teacher_or_director_for_session($id)
{
    global $Database;
    global $User;
    
    if (is_admin())
	return (true);
    if (($ida = db_select_one("id_activity FROM session WHERE id = $id")) == NULL)
	return (false);
    $ida = $ida["id_activity"];
    ($activity = new FullActivity)->build($ida, false, false);
    return ($activity->is_director || $activity->is_teacher);
}

function is_teacher_or_director_for_activity($id)
{
    if (is_admin())
	return (true);
    ($activity = new FullActivity)->build($id, false, false);
    return ($activity->is_director || $activity->is_teacher);
}

function is_teacher_for_session($id)
{
    global $Database;
    global $User;
    
    if (is_admin())
	return (true);
    if (($ida = db_select_one("id_activity FROM session WHERE id = $id")) == NULL)
	return (false);
    $ida = $ida["id_activity"];
    ($activity = new FullActivity)->build($ida, false, false);
    return ($activity->is_teacher);
}

function is_teacher_for_activity($id)
{
    global $User;
    
    if (is_admin())
	return (true);
    if (is_object($id))
	return ($id->is_teacher);
    ($activity = new FullActivity)->build($id, false, false);
    return ($activity->is_teacher);
}

function is_teacher_for_student($id_student)
{
    global $User;

    if (($id_student = resolve_codename("user", $id_student))->is_error())
	return (false);
    $id_student = $id_student->value;
    foreach (db_select_all("
	activity.id FROM user_team
        LEFT JOIN team ON user_team.id_team = team.id
        LEFT JOIN activity ON team.id_activity = activity.id
	WHERE id_user = $id_student AND status > 0
        AND activity.parent_activity IS NULL 
    ") as $module)
    {
	if (is_teacher_for_activity($module["id"]))
	    return (true);
    }
    return (false);
}

function is_leader_or_assistant_for_activity($id)
{
    global $User;

    ($activity = new FullActivity)->build($id, false, false);
    return ($activity->is_leader || $activity->is_assistant);
}

function is_student($id = -1) // cycle id?
{
    global $User;

    if (is_admin())
	return (true);
    if (!$User)
	return (false);
    if ($id == -1)
	return (!!db_select_one("id FROM user_cycle WHERE id_user = {$User["id"]}"));
    return (!!db_select_one("
      id FROM user_cycle WHERE id_user = {$User["id"]} AND id_cycle = $id
      "));
}

function is_subscribed($id = -1)
{
    global $User;

    if (!$User)
	return (false);
    if ($id == -1)
	return (false);
    ($activity = new FullActivity)->build($id, false, false);
    return ($activity->registered);
}

function is_my_team($id = -1)
{
    global $User;

    if (is_admin())
	return (true);
    if (!$User)
	return (false);
    if ($id == -1)
	return (false);
    return (!!db_select_one("
      id FROM user_team WHERE id_user = {$User["id"]} AND id_team = $id
      "));
}

function is_my_team_or_assistant($id = -1)
{
    global $User;
    
    if (is_admin())
	return (true);
    if (is_my_team($id))
	return (true);
    return (is_teacher(NULL, [
	"id" => $User["id"]
    ], 1));
}

function is_subscribed_or_teacher($id = -1)
{
    global $Database;
    
    if ($id == -1)
	return (false);
    if (is_admin())
	return (true);
    ($activity = new FullActivity)->build($id, false, false);
    return ($activity->registered || $activity->is_teacher);
}

function is_subscribed_or_assistant($id = -1)
{
    global $Database;
    
    if ($id == -1)
	return (false);
    if (is_admin())
	return (true);
    ($activity = new FullActivity)->build($id, false, false);
    return ($activity->registered || $activity->is_assistant);
}

function is_cycle_director($id_user = -1)
{
    return (is_cycle_director_of($id_user));
}

function is_cycle_director_of($id_user = -1, $id_cycle = -1)
{
    global $User;

    if (!$User)
	return (false);
    if (is_admin())
	return (true);
    if ($id_user == -1)
	$id_user = $User["id"];
    $id_cycle = (int)$id_cycle;
    if ($id_cycle == -1)
	$id_cycle = "";
    else
	$id_cycle = " AND cycle_teacher.id_cycle = $id_cycle ";
    return (db_select_one("
        cycle_teacher.id_user, user_laboratory.id_user
        FROM cycle_teacher
        LEFT JOIN laboratory ON cycle_teacher.id_laboratory
        LEFT JOIN user_laboratory ON user_laboratory.id_laboratory = laboratory.id
        WHERE (cycle_teacher.id_user = $id_user
        OR user_laboratory.id_user = $id_user
        )
	$id_cycle
    ") != NULL);
}

function is_cycle_director_for_student($id_student)
{
    global $User;

    if (($id_student = resolve_codename("user", $id_student))->is_error())
	return (false);
    $id_student = $id_student->value;
    foreach (db_select_all("
        id_cycle FROM user_cycle
        WHERE user_cycle.id_user = $id_student
    ") as $cyc)
    {
	if (is_director_for_cycle($cyc["id_cycle"]))
	    return (true);
    }
    return (false);
}

function is_director_for_cycle($id)
{
    return (is_cycle_director_of(-1, $id));
}

function am_i_cycle_director()
{
    return (is_cycle_director_of());
}

function is_director_for_student($id, $big_admin = true)
{
    if ($big_admin && is_admin())
	return (true);
    global $User;

    if (($user = resolve_codename("user", $id))->is_error())
	return (false);
    $user = ["id" => $user->value];
    get_user_school($User, true);
    get_user_school($user, true);
    foreach ($user["school"] as $school)
    {
	if ($school["authority"] != 0)
	    continue ;
	if (isset($User["school"][$school["codename"]]["authority"])
	    && $User["school"][$school["codename"]]["authority"] == 1
	)
	    return (true);
    }
    return (false);
}

function is_me_or_director_for_student($id)
{
    if (is_me($id))
	return (true);
    return (is_director_for_student($id));
}

function is_director_for_session($id)
{
    global $Database;
    global $User;
    
    if (is_admin())
	return (true);
    if (($ida = db_select_one("id_activity FROM session WHERE id = $id")) == NULL)
	return (false);
    $ida = $ida["id_activity"];
    ($activity = new FullActivity)->build($ida, false, false);
    return ($activity->is_director);
}

function is_director_for_activity($id)
{
    global $User;
    
    if (is_admin())
	return (true);
    ($activity = new FullActivity)->build($id, false, false);
    return ($activity->is_director);
}

function is_director_for_school($id)
{
    global $User;

    if (is_admin())
	return (true);
    $id = $id == -1 ? "" : " AND id_school = $id ";
    $match = db_select_one("authority FROM user_school WHERE id_user = {$User["id"]} AND authority = 1 $id");
    return ($match != NULL);
}

function is_director_for_room($id)
{
    global $User;

    if (is_admin())
	return (true);
    get_user_school($User);
    foreach ($User["school"] as $school)
    {
	if ($school["authority"] == 0)
	    continue ;
	$ret = db_select_one("
	    id FROM school_room
	    WHERE id_school = {$school["id_school"]}
	    AND id_room = $id
	    ");
	if ($ret)
	    return (true);
    }
    return (false);
}

function is_director($id = -1)
{
    global $User;

    if (!$User)
	return (false);
    if (is_admin())
	return (true);
    if ($id == -1)
	$id = $User["id"];
    if ($User["id"] == $id)
	return ($User["school_authority"] > 0);
    return (false);
    // Normalement devenu inutile
    return (db_select_one("
        cycle_teacher.id
        FROM cycle_teacher
	LEFT JOIN user_laboratory
          ON user_laboratory.id_laboratory = cycle_teacher.id_laboratory
	WHERE cycle_teacher.id_user = {$User["id"]}
	OR user_laboratory.id_user = {$User["id"]}
	"));
}

function am_i_director()
{
    global $User;

    if (!$User)
	return (false);
    return (is_director($User["id"]));
}

function am_i_director_of($id_school)
{
    global $User;
    
    if (is_array($id_school))
    {
	foreach ($id_school as $sc)
	{
	    if (is_array($sc))
	    {
		if (am_i_director_of($sc["id"]))
		    return (true);
	    }
	    else
	    {
		if (am_i_director_of($sc))
		    return (true);
	    }
	}
	return (false);
    }
    get_user_school($User);
    foreach ($User["school"] as $sc)
    {
	if ($sc["id_school"] != $id_school)
	    continue ;
	if ($sc["authority"] > 0)
	    return (true);
    }
    return (false);
}

function am_i_dir_or_cdir()
{
    if (am_i_director())
	return (true);
    if (am_i_cycle_director())
	return (true);
    return (false);
}

function is_my_director($id)
{
    global $User;

    if (is_admin())
	return (true);
    $usr = ["id" => $id];
    get_user_school($usr);
    foreach ($usr["school"] as $school)
    {
	foreach ($User["school"] as $ms)
	{
	    if (abs($ms["id_school"]) != abs($school["id_school"]))
		continue ;
	    if ($ms["authority"] > $school["authority"])
		return (true);
	}
    }
    return (false);
}

function is_me_or_my_director($id)
{
    if (is_me($id))
	return (true);
    return (is_my_director($id));
}

function is_me_or_admin($id)
{
    return (is_me($id) || is_admin());
}

function only_admin($id)
{
    return (is_admin());
}

function is_member_of_laboratory($id_lab)
{
    global $User;

    if (is_admin())
	return (true);
    if (($id_lab = resolve_codename("laboratory", $id_lab))->is_error())
	return (false);
    $id_lab = $id_lab->value;
    return (db_select_one("
        id FROM user_laboratory
        WHERE id_user = {$User["id"]} 
        AND id_laboratory = $id_lab
	") != NULL);
}

