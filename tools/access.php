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

function is_intranet_member_profile($usr = NULL)
{
    global $User;

    if ($usr == NULL)
	$usr = $User;
    if (!is_array($usr))
	return (false);
    if (isset($usr["profile_status"]) && $usr["profile_status"] != "member")
	return (false);
    return (true);
}

function normalize_school_authority($authority)
{
    static $numeric = [
        0 => "STUDENT",
        1 => "DIRECTOR",
        2 => "SECRETARIAT",
        3 => "COMMERCIAL",
        4 => "TEACHER",
        5 => "LIBRARIAN",
    ];

    if (is_int($authority)
        || (is_string($authority) && preg_match('/^-?[0-9]+$/', trim($authority))))
        return ($numeric[(int)$authority] ?? "");
    return (strtoupper(trim((string)$authority)));
}

function user_school_authorities($id_user, $id_school = -1)
{
    $id_user = (int)$id_user;
    $id_school = (int)$id_school;
    if ($id_user <= 0)
        return ([]);

    $school_filter = $id_school == -1
        ? ""
        : " AND user_school.id_school = $id_school ";
    $rows = db_select_all("
        user_school.id_school AS id_school,
        user_school.authority AS authority
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id_user
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
          $school_filter
    ");
    $out = [];
    foreach ($rows as $row)
    {
        $authority = normalize_school_authority($row["authority"] ?? "");
        if ($authority == "")
            continue ;
        $school = (int)$row["id_school"];
        if (!isset($out[$school]))
            $out[$school] = [];
        $out[$school][$authority] = true;
    }
    return ($out);
}

function user_has_school_authority($id_user, $authority, $id_school = -1)
{
    $authority = normalize_school_authority($authority);
    if ($authority == "")
        return (false);

    foreach (user_school_authorities($id_user, $id_school) as $authorities)
        if (isset($authorities[$authority]))
            return (true);
    return (false);
}

function user_school_ids($id_user, $authority = NULL)
{
    $authorities = user_school_authorities($id_user);
    if ($authority === NULL)
        return (array_keys($authorities));

    $authority = normalize_school_authority($authority);
    $out = [];
    foreach ($authorities as $id_school => $roles)
        if (isset($roles[$authority]))
            $out[] = (int)$id_school;
    return ($out);
}

function is_assistant($usr = NULL)
{
    return (is_teacher(NULL, $usr, 1));
}

function can_manage_corrections()
{
    return (is_assistant() || am_i_director() || am_i_cycle_director());
}

function is_teacher($id = NULL, $usr = NULL, $lvl = 2)
{
    global $User;

    if (!$User && $usr == NULL)
	return (false);
    if ($usr == NULL)
	$usr = $User;
    if (!is_intranet_member_profile($usr))
	return (false);
    if ($User && $usr["id"] == $User["id"] && is_admin())
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
    
    if (!is_intranet_member_profile())
	return (false);
    if (is_admin())
	return (true);
    $id = (int)$id;
    if (($ida = db_select_one("id_activity FROM session WHERE id = $id")) == NULL)
	return (false);
    $ida = $ida["id_activity"];
    ($activity = new FullActivity)->build($ida, false, false);
    $teachers = function_exists("fetch_session_teachers")
        ? fetch_session_teachers($id, true, true, $ida, $activity)
        : $activity->teacher;
    return (retrieve_authority($teachers) >= ASSISTANT);
}

function is_teacher_or_director_for_session($id)
{
    global $Database;
    global $User;
    
    if (!is_intranet_member_profile())
	return (false);
    if (is_admin())
	return (true);
    $id = (int)$id;
    if (($ida = db_select_one("id_activity FROM session WHERE id = $id")) == NULL)
	return (false);
    $ida = $ida["id_activity"];
    ($activity = new FullActivity)->build($ida, false, false);
    $teachers = function_exists("fetch_session_teachers")
        ? fetch_session_teachers($id, true, true, $ida, $activity)
        : $activity->teacher;
    return ($activity->is_director || retrieve_authority($teachers) >= TEACHER);
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
    
    if (!is_intranet_member_profile())
	return (false);
    if (is_admin())
	return (true);
    $id = (int)$id;
    if (($ida = db_select_one("id_activity FROM session WHERE id = $id")) == NULL)
	return (false);
    $ida = $ida["id_activity"];
    ($activity = new FullActivity)->build($ida, false, false);
    $teachers = function_exists("fetch_session_teachers")
        ? fetch_session_teachers($id, true, true, $ida, $activity)
        : $activity->teacher;
    return (retrieve_authority($teachers) >= TEACHER);
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
    if (is_admin())
	return (true);
    if (is_my_team($id))
	return (true);
    return (is_assistant_for_team($id));
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
        LEFT JOIN laboratory ON cycle_teacher.id_laboratory = laboratory.id
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
    global $User;

    if ($big_admin && is_admin())
	return (true);
    if (!$User)
        return (false);
    if (($user = resolve_codename("user", $id))->is_error())
	return (false);

    foreach (user_school_ids($user->value, "STUDENT") as $id_school)
        if (user_has_school_authority($User["id"], "DIRECTOR", $id_school))
            return (true);
    return (false);
}

function is_me_or_director_for_student($id)
{
    if (is_me($id))
	return (true);
    return (is_director_for_student($id));
}

function is_identity_authority_for_user($id)
{
    if (is_admin())
        return (true);
    if (!logged_in())
        return (false);
    if (is_director_for_student($id, false))
        return (true);

    foreach (user_school_ids((int)$id) as $id_school)
        if (is_secretariat_for_school($id_school))
            return (true);
    return (false);
}

function can_view_user_identity($id)
{
    return (is_me($id) || is_identity_authority_for_user($id));
}

function can_edit_user_profile($id)
{
    return (can_view_user_identity($id));
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

    if (!logged_in())
	return (false);
    if (is_admin())
	return (true);
    return (user_has_school_authority($User["id"], "DIRECTOR", $id));
}

function is_secretariat_for_school($id)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
	return (true);
    return (user_has_school_authority($User["id"], "SECRETARIAT", $id));
}

function is_commercial_for_school($id)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
	return (true);
    return (user_has_school_authority($User["id"], "COMMERCIAL", $id));
}

function is_teacher_for_school($id)
{
    global $User;

    if (!is_intranet_member_profile())
	return (false);
    if (is_admin())
	return (true);
    return (user_has_school_authority($User["id"], "TEACHER", $id));
}

function is_assistant_for_school($id)
{
    global $User;

    if (!is_intranet_member_profile())
        return (false);
    if (is_admin())
        return (true);

    $id = (int)$id;
    if ($id <= 0)
        return (false);
    $uid = (int)$User["id"];

    // Direct school responsibilities. Administrative roles such as
    // secretariat/commercial are deliberately not pedagogical authorities.
    if (user_has_school_authority($uid, "DIRECTOR", $id)
        || user_has_school_authority($uid, "TEACHER", $id))
        return (true);

    // Direct cycle responsibility, or responsibility inherited through a
    // laboratory attached to the cycle (assistant/professor/chief).
    if (db_select_one("
        cycle_teacher.id
        FROM cycle_teacher
        LEFT JOIN school_cycle
          ON school_cycle.id_cycle = cycle_teacher.id_cycle
        LEFT JOIN user_laboratory
          ON user_laboratory.id_laboratory = cycle_teacher.id_laboratory
         AND user_laboratory.id_user = $uid
         AND user_laboratory.authority >= " . ASSISTANT . "
        WHERE school_cycle.id_school = $id
          AND (cycle_teacher.id_user = $uid OR user_laboratory.id_user = $uid)
    ") != NULL)
        return (true);

    // Same inheritance for activities linked to a cycle of the school.
    if (db_select_one("
        activity_teacher.id
        FROM activity_teacher
        LEFT JOIN activity_cycle
          ON activity_cycle.id_activity = activity_teacher.id_activity
        LEFT JOIN school_cycle
          ON school_cycle.id_cycle = activity_cycle.id_cycle
        LEFT JOIN user_laboratory
          ON user_laboratory.id_laboratory = activity_teacher.id_laboratory
         AND user_laboratory.id_user = $uid
         AND user_laboratory.authority >= " . ASSISTANT . "
        WHERE school_cycle.id_school = $id
          AND (activity_teacher.id_user = $uid OR user_laboratory.id_user = $uid)
    ") != NULL)
        return (true);

    // A laboratory can also be attached directly to an establishment.
    return (db_select_one("
        user_laboratory.id
        FROM user_laboratory
        LEFT JOIN school_laboratory
          ON school_laboratory.id_laboratory = user_laboratory.id_laboratory
        WHERE user_laboratory.id_user = $uid
          AND user_laboratory.authority >= " . ASSISTANT . "
          AND school_laboratory.id_school = $id
    ") != NULL);
}

function can_edit_supports($id = -1)
{
    return (is_teacher_for_school($id));
}

function is_director_for_room($id)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
	return (true);
    $id = (int)$id;
    foreach (db_select_all("id_school FROM school_room WHERE id_room = $id") as $school)
        if (user_has_school_authority($User["id"], "DIRECTOR", $school["id_school"]))
            return (true);
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
    if ((int)$User["id"] != (int)$id)
        return (false);
    return (user_has_school_authority($User["id"], "DIRECTOR"));
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
    if (is_array($id_school))
    {
	foreach ($id_school as $sc)
	{
            if (is_array($sc))
                $sc = $sc["id_school"] ?? ($sc["id"] ?? -1);
            if (am_i_director_of($sc))
                return (true);
	}
	return (false);
    }
    return (is_director_for_school((int)$id_school));
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

    if (!$User)
        return (false);
    if (is_admin())
	return (true);
    $id = (int)$id;
    if ($id <= 0)
        return (false);

    foreach (user_school_ids($id) as $id_school)
        if (user_has_school_authority($User["id"], "DIRECTOR", $id_school)
            && !user_has_school_authority($id, "DIRECTOR", $id_school))
            return (true);
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

function is_librarian($id = -1)
{
    global $User;

    if (!$User)
	return (false);
    if (is_admin())
	return (true);
    return (user_has_school_authority($User["id"], "LIBRARIAN", $id));
}

// L'adm au sens des étudiants
function is_secretariat($id = -1)
{
    global $User;

    if (!$User)
	return (false);
    if (is_admin())
	return (true);
    return (user_has_school_authority($User["id"], "SECRETARIAT", $id));
}

function is_commercial($id = -1)
{
    global $User;

    if (!$User)
	return (false);
    if (is_admin())
	return (true);
    return (user_has_school_authority($User["id"], "COMMERCIAL", $id));
}

