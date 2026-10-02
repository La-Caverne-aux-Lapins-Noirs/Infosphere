<?php

require ("activities.php");

function DeclareSessionTeacherPresence($id, $data, $method, $output, $module)
{
    global $SUBID, $User, $Database, $Dictionnary;

    if ($id <= 0 || $SUBID <= 0 || !is_array($User) || !is_intranet_member_profile())
	bad_request();
    if (!session_signin_schema_ready())
	return (new ErrorResponse("CannotExecute", "Les tables SQL de l'émargement de session doivent être installées."));
    $session = db_select_one("* FROM session WHERE id = ".(int)$SUBID." AND id_activity = ".(int)$id." AND deleted IS NULL");
    if (!$session)
	not_found();
    if (!session_signin_teacher_is_assigned($SUBID, $User["id"]))
	forbidden();
    $begin = date_to_timestamp($session["begin_date"]);
    $end = date_to_timestamp($session["end_date"]);
    if (!$begin || !$end || !period($begin - 10 * 60, $end))
	return (new ErrorResponse("SessionTeacherPresencePeriod"));

    $uid = (int)$User["id"];
    $today = datex("Y-m-d");
    if (!$Database->query("
	INSERT INTO session_teacher_presence (id_session, id_user, attendance_day, declared_at)
	VALUES (".(int)$SUBID.", $uid, '$today', NOW())
	ON DUPLICATE KEY UPDATE id_user = id_user
    "))
	return (new ErrorResponse("CannotEdit"));

    ob_start();
    session_signin_teacher_button((int)$id, (int)$SUBID);
    return (new ValueResponse([
	"msg" => $Dictionnary["SessionTeacherPresenceRecorded"],
	"content" => ob_get_clean(),
    ]));
}

function SetSessionTeacherPresenceAdmin($id, $data, $method, $output, $module)
{
    global $SUBID, $User, $Database;

    if ($id <= 0 || $SUBID <= 0 || !is_array($User) || !is_admin())
        forbidden();
    if (!session_signin_schema_ready())
        return (new ErrorResponse("CannotExecute", "Les tables SQL de l'émargement de session doivent être installées."));

    $session = db_select_one("* FROM session WHERE id = ".(int)$SUBID." AND id_activity = ".(int)$id." AND deleted IS NULL");
    if (!$session)
        not_found();
    $id_teacher = (int)($data["teacher"] ?? 0);
    $attendance_day = trim((string)($data["attendance_day"] ?? ""));
    $present = (int)($data["present"] ?? 0) === 1;
    if ($id_teacher <= 0 || !session_signin_teacher_is_assigned((int)$SUBID, $id_teacher))
        return (new ErrorResponse("CannotExecute", "Ce formateur n'est pas affecté à cette session."));
    if (!in_array($attendance_day, session_signin_attendance_days($session), true))
        return (new ErrorResponse("CannotExecute", "Le jour de présence ne fait pas partie de cette session."));

    $id_session = (int)$SUBID;
    $pdf = session_signin_pdf_path($id_session);
    if ($pdf && is_file($pdf) && !@unlink($pdf))
        return (new ErrorResponse("CannotExecute", "Le PDF existant ne peut pas être invalidé. Corrige ses permissions avant de modifier la présence."));
    if ($present)
    {
        if (!$Database->query("
            INSERT INTO session_teacher_presence (id_session, id_user, attendance_day, declared_at)
            VALUES ($id_session, $id_teacher, '$attendance_day', NOW())
            ON DUPLICATE KEY UPDATE declared_at = declared_at
        "))
            return (new ErrorResponse("CannotEdit"));
    }
    else if (!$Database->query("
        DELETE FROM session_teacher_presence
        WHERE id_session = $id_session AND id_user = $id_teacher
          AND attendance_day = '$attendance_day'
    "))
        return (new ErrorResponse("CannotEdit"));

    if (!$Database->query("
        UPDATE session
        SET signin_generated_at = NULL, signin_id_actor = NULL, signin_sha256 = NULL
        WHERE id = $id_session
    "))
        return (new ErrorResponse("CannotEdit"));

    add_log(EDITING_OPERATION,
        "Session teacher presence #$id_session / user #$id_teacher / $attendance_day set to ".($present ? "present" : "absent"),
        (int)$User["id"]);
    return (new ValueResponse(["msg" => $present ? "Présence formateur enregistrée." : "Présence formateur retirée."]));
}

function SetPresenceDeclaration($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $User;
    global $Configuration;
    global $Dictionnary;
    global $ARGV;

    if ($id == -1)
	bad_request();
    ($activity = new FullActivity)->build($id);

    if ($SUBID == -1)
    {
	if (is_assistant_for_activity($id, $activity))
	    bad_request();
	if ($activity->teamable)
	    forbidden(); // Si on est en équipe, c'est le prof qui émarge.
	$team = db_select_one("
           team.*
           FROM team LEFT JOIN user_team ON team.id = user_team.id_team
	   WHERE id_activity = $id AND user_team.id_user = {$User["id"]}
	   ");
	if ($team == NULL || $team["id_session"] == -1)
	    not_found();
	$ret = declare_presence($activity, $team["id_session"]);
	ob_start();
	($activity = new FullActivity)->build($id);
	require_once ("./pages/instance/about_buttons.php");
	$content = ob_get_clean();
	return (new ValueResponse([
	    "msg" => (string)$ret,
	    "content" => $content
	]));	
    }

    if (!isset($data["subaction"]))
	bad_request();
    if (!is_assistant_for_activity($id, $activity))
	forbidden();
    $team = db_select_one("
           team.*
           FROM team
	   WHERE id_activity = $id AND id = $SUBID
    ");
    if ($team == NULL || $team["id_session"] == -1)
	not_found();

    if (in_array($data["subaction"], ["justify", "unjustify"], true))
    {
	if ((int)$team["present"] != -2)
	    bad_request();
	db_update_one("team", $team["id"], [
	    "absence_justified" => $data["subaction"] == "justify" ? 1 : 0,
	]);

	ob_start();
	($activity = new FullActivity)->build($id);
	foreach ($activity->team as $cteam)
	{
	    if ($cteam["id"] != $team["id"])
		continue ;
	    require ("./pages/instance/single_team_presence.php");
	    break ;
	}
	return (new ValueResponse([
	    "msg" => $data["subaction"] == "justify"
		? "Absence marquée comme justifiée."
		: "Justification de l'absence retirée.",
	    "content" => ob_get_clean(),
	]));
    }

    $ptype = [
	"present" => 1,
	"late" => -1,
	"missing" => -2
    ];
    if (!isset($ptype[$data["subaction"]]))
	bad_request();
    db_update_one("team", $team["id"], [
	"present" => $ptype[$data["subaction"]],
	"absence_justified" => 0,
	"declaration_date" => db_form_date(now()),
	"late_time" => NULL
    ]);

    ob_start();
    ($activity = new FullActivity)->build($id);
    foreach ($activity->team as $cteam)
    {
	if ($cteam["id"] != $team["id"])
	    continue ;
	require_once ("./pages/instance/single_team_presence.php");
	break ;
    }
    return (new ValueResponse([
	"msg" => $Dictionnary["PresenceDeclared"],
	"content" => ob_get_clean()
    ]));
}

function AcceptOrRefuseMember($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Dictionnary;
    global $Database;
    global $User;

    if ($id == -1 || $SUBID == -1)
	bad_request();
    $SUBID = abs($SUBID);
    ($activity = new FullActivity)->build($id);
    if (!$activity->is_assistant)
    {
	$usr = NULL;
	$cteam = $activity->user_team;
	foreach ($cteam["user"] as $user)
	{
	    if ($user["id"] != $SUBID)
		continue ;
	    $usr = $user;
	    break ;
	}
	if ($usr == NULL)
	    not_found();
    }
    else
    {
	$cteam = NULL;
	foreach ($activity->team as $team)
	{
	    foreach ($team["user"] as $user)
	    {
		if ($user["id"] != $SUBID)
		    continue ;
		$cteam = $team;
		$usr = $user;
		break 2;
	    }
	}
	if ($cteam == NULL)
	    not_found();
    }

    if ($usr["status"] != 0)
	bad_request();

    if ($method == "PUT")
    {
	if (!$activity->is_assistant && $cteam["real_members"] >= $activity->max_team_size)
            return (new ErrorResponse("TeamIsFull"));
	$Database->query("
            UPDATE user_team
            SET status = 1
            WHERE id_team = {$cteam["id"]} AND id_user = {$usr["id"]}
	    ");	
    }
    else if (($ret = unsubscribe_from_instance($activity, $usr["id"], true))->is_error())
	return ($ret);

    // On rafraichit l'équipe
    ($activity = new FullActivity)->build($id);
    foreach ($activity->team as $team)
    {
	if ($team["id"] != $cteam["id"])
	    continue ;
	$cteam = $team;
	break ;
    }
    ob_start();
    require_once ("./pages/instance/single_team.phtml");
    return (new ValueResponse([
	"msg" => $Dictionnary[$method == "PUT" ? "MembershipAccepted" : "MembershipRefused"],
	"content" => ob_get_clean()
    ]));
}

function LockUnlockTeam($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Dictionnary;
    global $User;
    
    if ($id == -1)
	bad_request();
    ($activity = new FullActivity)->build($id);
    if (!$activity->is_assistant)
    {
	if ($SUBID != -1)
	    bad_request();
	$cteam = $activity->user_team;
	$SUBID = $cteam["id"];
    }
    else
    {
	if ($SUBID == -1)
	    bad_request();
	$SUBID = abs($SUBID);
	foreach ($activity->team as $team)
	{
	    if ($team["id"] != $SUBID)
		continue ;
	    $cteam = $team;
	    break ;
	}
	if ($cteam == NULL)
	    not_found();
    }
    foreach ($cteam["user"] as $usr)
	if ($usr["status"] == 0)
	    return (new ErrorResponse("CannotLockSomeMembersAreStillPending"));
    if (!$activity->is_assistant && $cteam["real_members"] < $activity->min_team_size)
	return (new ErrorResponse("YourTeamIsIncomplete"));
    db_update_one("team", $SUBID, [
	"canjoin" => $method == "DELETE" ? 1 : 0
    ]);
    
    ob_start();
    ($activity = new FullActivity)->build($id);
    if (!$activity->is_assistant)
	$cteam = $activity->user_team;
    else
    {
	foreach ($activity->team as $t)
	{
	    if ($t["id"] != $SUBID)
		continue ;
	    $cteam = $t;
	    break ;
	}
    }
    require_once ("./pages/instance/single_team.phtml");
    return (new ValueResponse([
	"msg" => $Dictionnary[$method == "PUT" ? "TeamLocked" : "TeamUnlocked"],
	"content" => ob_get_clean()
    ]));
}

function EditComment($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Database;
    global $User;
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    $id = (int)$id;
    $type = 0;
    if (($user = (int)$SUBID) != -1)
    {
	if (($idut = db_select_one("id FROM user_team WHERE id_team = $id AND id_user = $user")) == NULL)
	    not_found();
	$id = $idut["id"];
	$type = 1;
    }
    $commentaries = strip_tags($data["commentaries"]);
    $commentaries = $Database->real_escape_string($commentaries);
    $author = $User["id"];
    $now = db_form_date(now());
    
    $Database->query("
	INSERT INTO comment (id_user, id_misc, misc_type, content)
	VALUES ($author, $id, $type, '$commentaries')
    ");
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"]
    ]));
}

function SetMedal($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    $id = (int)$id;
    if (!($id_activity = db_select_one("id_activity FROM team WHERE id = $id")))
	not_found();
    if (@strlen($medals = $data["id_medal"]) == 0)
	return (new ValueResponse([
	    "msg" => $Dictionnary["NothingToBeDone"]
	]));
    $id_team = (int)$id;

    if ($SUBID != -1)
    {
	if (($id_user = resolve_codename("user", $SUBID))->is_error())
	    return ($id_user);
	else
	    $id_user = $id_user->value;
	if (!($id_user_team = db_select_one("
	  id FROM user_team
	  WHERE id_team = $id_team AND id_user = $id_user
	")))
	    not_found();
	$id_user_team = $id_user_team["id"];
    }
    else
    {
	$id_user = -1;
	$id_user_team = -1;
    }

    if (($ret = edit_medal($medals, $id_user, $id_team, $id_user_team))->is_error())
	return ($ret);
    ($activity = new FullActivity)->build($id_activity);
    ob_start();
    $cteam = NULL;
    foreach ($activity->team as $team)
    {
	if ($team["id"] != $id_team)
	    continue ;
	$cteam = $team;
	break ;
    }
    if ($id_user == -1)
    {
	get_activity_medal_for_team(
	    $cteam,
	    $activity->reference_activity == -1 ?
	    $activity->id :
	    $activity->reference_activity
	);
	$medalteam = true;
        $medlist = $cteam["medal"];
    }
    else
    {
	$usr = NULL;
	foreach ($cteam["user"] as $user)
        {
	    if ($user["id"] != $id_user)
		continue ;
	    $usr = $user;
	    break ;
	}
	get_activity_medal_for_user(
	    $usr,
	    $activity->reference_activity == -1 ?
	    $activity->id :
	    $activity->reference_activity
	);
	$medalteam = false;
	$medlist = $usr["medal"];
    }
    require ("./pages/instance/medal_list.php");
    return (new ValueResponse([
	"msg" => $Dictionnary["MedalEdited"],
	"content" => ob_get_clean()
    ]));
}


function GetAutomaticEvaluationStatus($id, $data, $method, $output, $module)
{
    if ($id == -1)
        bad_request();
    if (($activity = new FullActivity)->build($id, false, false, -1) == false)
        not_found();
    ob_start();
    require ("./pages/instance/automatic_evaluation_status.php");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

$Tab = [
    "GET" => [
        "automatic_evaluation" => [
            "is_subscribed_or_assistant",
            "GetAutomaticEvaluationStatus",
        ],
    ],
    "POST" => [],
    "PUT" => [
	"teacher_presence_admin" => [
	    "logged_in",
	    "SetSessionTeacherPresenceAdmin",
	],
	"teacher_presence" => [
	    "logged_in",
	    "DeclareSessionTeacherPresence",
	],
	"declare" => [
	    "is_leader_or_assistant_for_activity",
	    "SetPresenceDeclaration",
	],
	"member" => [
	    "is_leader_or_assistant_for_activity",
	    "AcceptOrRefuseMember",
	],
	"subscribe" => [
	    "everybody",
	    "SetActivityRegistration",
	],
	"lock" => [
	    "is_leader_or_assistant_for_activity",
	    "LockUnlockTeam",
	],
	"comment" => [
	    "is_assistant_for_team",
	    "EditComment",
	],
	"medal" => [
	    "is_assistant_for_team",
	    "SetMedal",
	],  
    ],
    "DELETE" => [
	"subscribe" => [
	    "everybody",
	    "SetActivityRegistration",
	],
	"team" => [
	    "is_assistant_for_activity",
	    "SetActivityRegistration",
	],
	"member" => [
	    "is_leader_or_assistant_for_activity",
	    "AcceptOrRefuseMember",
	],
	"lock" => [
	    "is_leader_or_assistant_for_activity",
	    "LockUnlockTeam",
	],
	"comment" => [
	    "is_assistant_for_team",
	    "EditComment",
	],
    ],
];
