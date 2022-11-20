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
    return ($ret);
}

function is_assistant_for_activity($id)
{
    global $User;
    
    if (is_admin())
	return (true);
    ($activity = new FullActivity)->build($id, false, false);
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
    ($activity = new FullActivity)->build($id, false, false);
    return ($activity->is_teacher);
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

function is_director_for_cycle($id)
{
    global $Database;
    global $User;

    if (!$User)
	return (false);
    if (is_admin())
	return (true);
    if (($ida = db_select_one("
        cycle_teacher.id_user, user_laboratory.id_user
        FROM cycle_teacher
        LEFT JOIN laboratory ON cycle_teacher.id_laboratory
        LEFT JOIN user_laboratory ON user_laboratory.id_laboratory = laboratory.id
        WHERE (cycle_teacher.id_user = {$User["id"]}
        OR user_laboratory.id_user = {$User["id"]})
        AND cycle_teacher.id_cycle = $id
    ")) == NULL)
	return (false);
    return (true);
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
    return (db_select_one("
        cycle_teacher.id
        FROM cycle_teacher
	LEFT JOIN user_laboratory
          ON user_laboratory.id_laboratory = cycle_teacher.id_laboratory
	WHERE cycle_teacher.id_user = {$User["id"]}
	OR user_laboratory.id_user = {$User["id"]}
	"));
}

function is_me_or_admin($id)
{
    return (is_me($id) || is_admin());
}

function only_admin($id)
{
    return (is_admin());
}

