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

/*
 * Access predicate naming convention:
 *   is_<role>($id_user)          -> role carried by that user.
 *   am_i_<role>()                -> role carried by the current actor
 *                                    (global administrators are accepted).
 *   is_<role>_for_<resource>($id)-> current actor may act in that resource
 *                                    context (global administrators may be
 *                                    accepted by the contextual predicate).
 *
 * api/calltab.php always passes the route resource ID to authorization
 * callbacks. Actor-wide route checks must therefore use am_i_* (or an
 * explicit ID-ignoring adapter such as only_admin), never is_<role>.
 */

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
        6 => "ACCOUNTANT",
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

function is_teacher($id_user = -1, $lvl = 2)
{
    global $User;

    if ($id_user == -1)
    {
        if (!$User)
            return (false);
        $id_user = (int)$User["id"];
    }
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);

    $usr = db_select_one("id, profile_status FROM user WHERE id = $id_user");
    if ($usr == NULL || !is_intranet_member_profile($usr))
        return (false);

    $lvl = (int)$lvl;
    return (db_select_one("
        activity_teacher.id
        FROM activity_teacher
        LEFT JOIN user_laboratory
          ON user_laboratory.id_laboratory = activity_teacher.id_laboratory
         AND user_laboratory.authority >= $lvl
        WHERE activity_teacher.id_user = $id_user
           OR user_laboratory.id_user = $id_user
    ") != NULL);
}

function is_assistant($id_user = -1)
{
    return (is_teacher($id_user, ASSISTANT));
}

function can_manage_corrections()
{
    return (am_i_assistant() || am_i_director() || am_i_cycle_director());
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

function can_view_session($id)
{
    global $User;

    if (!is_intranet_member_profile() || !is_array($User))
        return (false);
    $id = (int)$id;
    $session = db_select_one("id, id_activity, id_user, id_laboratory FROM session WHERE id = $id AND deleted IS NULL");
    if ($session == NULL)
        return (false);
    if (session_is_standalone($session))
        return (session_is_visible_to_user($session, (int)$User["id"]) || session_standalone_can_manage($session));

    // Preserve the historical GET policy for pedagogical sessions: any
    // teacher could open the session detail endpoint.
    return (am_i_teacher());
}

function is_assistant_for_session($id)
{
    global $User;

    if (!is_intranet_member_profile())
        return (false);
    if (is_admin())
        return (true);
    $id = (int)$id;
    $session = db_select_one("id, id_activity, id_user, id_laboratory FROM session WHERE id = $id AND deleted IS NULL");
    if ($session == NULL)
        return (false);
    if (session_is_standalone($session))
        return (session_standalone_can_manage($session));

    $ida = (int)$session["id_activity"];
    if ($ida <= 0)
        return (false);
    ($activity = new FullActivity)->build($ida, false, false);
    $teachers = function_exists("fetch_session_teachers")
        ? fetch_session_teachers($id, true, true, $ida, $activity)
        : $activity->teacher;
    return (retrieve_authority($teachers) >= ASSISTANT);
}

function is_teacher_or_director_for_session($id)
{
    if (!is_intranet_member_profile())
        return (false);
    if (is_admin())
        return (true);
    $id = (int)$id;
    $session = db_select_one("id, id_activity, id_user, id_laboratory FROM session WHERE id = $id AND deleted IS NULL");
    if ($session == NULL)
        return (false);
    if (session_is_standalone($session))
        return (session_standalone_can_manage($session));

    $ida = (int)$session["id_activity"];
    if ($ida <= 0)
        return (false);
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
    if (!is_intranet_member_profile())
        return (false);
    if (is_admin())
        return (true);
    $id = (int)$id;
    $session = db_select_one("id, id_activity, id_user, id_laboratory FROM session WHERE id = $id AND deleted IS NULL");
    if ($session == NULL)
        return (false);
    if (session_is_standalone($session))
    {
        if (session_standalone_kind($session) === "laboratory")
            return (session_laboratory_authority(
                (int)$GLOBALS["User"]["id"],
                session_reference_id($session, "id_laboratory")
            ) >= TEACHER || am_i_director());
        return (am_i_director());
    }

    $ida = (int)$session["id_activity"];
    if ($ida <= 0)
        return (false);
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

    if ($id_user == -1)
    {
        if (!$User)
            return (false);
        $id_user = (int)$User["id"];
    }
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);

    $id_cycle = (int)$id_cycle;
    if ($id_cycle == -1)
        $cycle_filter = "";
    else
        $cycle_filter = " AND cycle_teacher.id_cycle = $id_cycle ";
    return (db_select_one("
        cycle_teacher.id_user, user_laboratory.id_user
        FROM cycle_teacher
        LEFT JOIN laboratory ON cycle_teacher.id_laboratory = laboratory.id
        LEFT JOIN user_laboratory ON user_laboratory.id_laboratory = laboratory.id
        WHERE (cycle_teacher.id_user = $id_user
        OR user_laboratory.id_user = $id_user
        )
        $cycle_filter
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
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    return (is_cycle_director_of((int)$User["id"], $id));
}

function am_i_cycle_director()
{
    global $User;

    if (!$User)
        return (false);
    return (is_admin() || is_cycle_director((int)$User["id"]));
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

function can_export_student_logs($id)
{
    global $User;

    $id = (int)$id;
    if ($id <= 0 || !logged_in() || !$User)
        return (false);
    // Require an actual student enrollment, including for global administrators.
    if (!count(user_school_ids($id, "STUDENT")))
        return (false);
    return (is_admin() || is_director_for_student($id, false));
}

function can_manage_student_documents($id)
{
    global $User;

    if (is_director_for_student($id))
        return (true);
    if (!$User)
        return (false);
    if (($student = resolve_codename("user", $id))->is_error())
        return (false);
    foreach (user_school_ids($student->value, "STUDENT") as $id_school)
        if (is_commercial_for_school($id_school))
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

// Password and NFC credentials are more sensitive than ordinary profile data.
// A school director/secretariat may manage them only for a non-administrator
// attached to one of their own schools; global administrators remain the
// operational fallback for every account.
function can_manage_user_credentials($id)
{
    global $User;

    if (!logged_in())
        return (false);
    if (!$User || ($target = resolve_codename("user", $id, "codename", true))->is_error())
        return (false);

    $target = $target->value;
    if (!is_array($target)
        || (int)($target["id"] ?? 0) <= 1
        || trim((string)($target["password"] ?? "")) == ""
        || (string)($target["profile_status"] ?? "") !== "member"
        || ($target["deleted"] ?? NULL) !== NULL)
        return (false);
    if (is_admin())
        return (true);
    if ((int)($target["authority"] ?? USER) >= ADMINISTRATOR)
        return (false);

    foreach (user_school_ids((int)$target["id"]) as $id_school)
        if (is_director_for_school($id_school)
            || is_secretariat_for_school($id_school))
            return (true);
    return (false);
}

function is_director_for_session($id)
{
    if (is_admin())
        return (true);
    $id = (int)$id;
    $session = db_select_one("id, id_activity, id_user, id_laboratory FROM session WHERE id = $id AND deleted IS NULL");
    if ($session == NULL)
        return (false);
    if (session_is_standalone($session))
        return (am_i_director());

    $ida = (int)$session["id_activity"];
    if ($ida <= 0)
        return (false);
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

function is_director_for_school($id_school)
{
    global $User;

    if (!logged_in())
        return (false);
    if (is_admin())
        return (true);
    return (user_has_school_authority($User["id"], "DIRECTOR", $id_school));
}

function can_identify_school_nfc_card($id_school)
{
    global $User;

    $id_school = (int)$id_school;
    if (!logged_in() || !$User || $id_school <= 0)
        return (false);
    if (is_admin())
        return (true);

    $student_authority = user_school_student_authority_sql();
    return (db_select_one("
        user_school.id as id
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = ".((int)$User["id"])."
          AND user_school.id_school = $id_school
          AND user_school.authority <> $student_authority
          AND school.deleted IS NULL
    ") !== NULL);
}

function is_secretariat_for_school($id_school)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    return (user_has_school_authority($User["id"], "SECRETARIAT", $id_school));
}

function is_commercial_for_school($id_school)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    return (user_has_school_authority($User["id"], "COMMERCIAL", $id_school));
}

function is_accountant_for_school($id_school)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    return (user_has_school_authority($User["id"], "ACCOUNTANT", $id_school));
}

function is_teacher_for_school($id_school)
{
    global $User;

    if (!is_intranet_member_profile())
        return (false);
    if (is_admin())
        return (true);
    return (user_has_school_authority($User["id"], "TEACHER", $id_school));
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

function can_edit_supports()
{
    // Support categories are global resources: the API route ID is a
    // category/support identifier, not an establishment identifier.
    return (is_teacher_for_school(-1));
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

function is_director($id_user = -1)
{
    global $User;

    if ($id_user == -1)
    {
        if (!$User)
            return (false);
        $id_user = (int)$User["id"];
    }
    return (user_has_school_authority((int)$id_user, "DIRECTOR"));
}

function am_i_director()
{
    global $User;

    if (!$User)
        return (false);
    return (is_admin() || is_director((int)$User["id"]));
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

function is_librarian_for_school($id_school)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    return (user_has_school_authority((int)$User["id"], "LIBRARIAN", (int)$id_school));
}

function is_librarian($id_user = -1)
{
    global $User;

    if ($id_user == -1)
    {
        if (!$User)
            return (false);
        $id_user = (int)$User["id"];
    }
    return (user_has_school_authority((int)$id_user, "LIBRARIAN"));
}

function am_i_librarian()
{
    global $User;

    if (!$User)
        return (false);
    return (is_admin() || is_librarian((int)$User["id"]));
}

// L'adm au sens des étudiants
function is_secretariat($id_user = -1)
{
    global $User;

    if ($id_user == -1)
    {
        if (!$User)
            return (false);
        $id_user = (int)$User["id"];
    }
    return (user_has_school_authority((int)$id_user, "SECRETARIAT"));
}

function am_i_secretariat()
{
    global $User;

    if (!$User)
        return (false);
    return (is_admin() || is_secretariat((int)$User["id"]));
}

function is_commercial($id_user = -1)
{
    global $User;

    if ($id_user == -1)
    {
        if (!$User)
            return (false);
        $id_user = (int)$User["id"];
    }
    return (user_has_school_authority((int)$id_user, "COMMERCIAL"));
}

function am_i_commercial()
{
    global $User;

    if (!$User)
        return (false);
    return (is_admin() || is_commercial((int)$User["id"]));
}

function is_accountant($id_user = -1)
{
    global $User;

    if ($id_user == -1)
    {
        if (!$User)
            return (false);
        $id_user = (int)$User["id"];
    }
    return (user_has_school_authority((int)$id_user, "ACCOUNTANT"));
}

function am_i_accountant()
{
    global $User;

    if (!$User)
        return (false);
    return (is_admin() || is_accountant((int)$User["id"]));
}
