<?php

require ("activities.php");

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
    $ptype = [
	"present" => 1,
	"late" => -1,
	"missing" => -2
    ];
    if (!isset($ptype[$data["subaction"]]))
	bad_request();
    db_update_one("team", $team["id"], [
	"present" => $ptype[$data["subaction"]],
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

$Tab = [
    "GET" => [], // Peut etre plus tard
    "POST" => [],
    "PUT" => [
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

