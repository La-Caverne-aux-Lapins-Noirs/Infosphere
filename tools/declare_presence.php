<?php

function presence_declaration_is_exam_activity($activity)
{
    return ((int)$activity->type >= 5 && (int)$activity->type <= 9);
}

function presence_declaration_is_available_for_activity($activity)
{
    if (!is_object($activity))
	return (false);
    if (presence_declaration_is_exam_activity($activity))
	return (false);
    if (isset($activity->teamable) && $activity->teamable)
	return (false);
    return (true);
}

function presence_declaration_is_remote_activity($activity)
{
    return (isset($activity->declaration_type) && (int)$activity->declaration_type == 2);
}

function presence_declaration_requires_locality($activity)
{
    return (isset($activity->declaration_type) && (int)$activity->declaration_type == 1);
}

function presence_declaration_session_disables_locality($session)
{
    if (!is_object($session) || !isset($session->id))
	return (true);
    if (!isset($session->room) || count($session->room) == 0)
	return (true);
    $wildcard = db_select_one("
        id_room
        FROM session_room
        WHERE id_session = ".((int)$session->id)."
          AND (id_room = 0 OR id_room = -1)
	");
    return ($wildcard != NULL);
}

function presence_declaration_hand_url_ips()
{
    global $Configuration;

    $host = trim((string)@$Configuration->Properties["handurl"]);
    if ($host == "")
	return ([]);

    if (strpos($host, "://") === false)
	$parsed = parse_url("ssh://".$host);
    else
	$parsed = parse_url($host);
    if ($parsed !== false && isset($parsed["host"]))
	$host = $parsed["host"];
    $host = trim($host, "[]");

    if (filter_var($host, FILTER_VALIDATE_IP))
	return ([$host]);

    $ips = @gethostbynamel($host);
    if ($ips === false || $ips == NULL)
    {
	$ip = @gethostbyname($host);
	if ($ip != $host && filter_var($ip, FILTER_VALIDATE_IP))
	    return ([$ip]);
	return ([]);
    }
    return (array_values(array_unique($ips)));
}

function presence_declaration_is_from_school_ip($wai = NULL)
{
    $client_ip = get_client_ip();
    if (strpos($client_ip, ",") !== false)
	$client_ip = trim(explode(",", $client_ip)[0]);
    $client_ip = trim($client_ip);
    if ($client_ip == "")
	return (false);

    foreach (presence_declaration_hand_url_ips() as $ip)
	if ($client_ip == $ip)
	    return (true);

    // En intranet pur, Infosphere peut voir directement l'IP privée du poste
    // plutôt que l'IP publique/gateway utilisée par hand_request. Dans ce cas,
    // on accepte aussi l'IP du poste détecté par Persoc/Distrans, tout en
    // gardant la vérification de salle ci-dessous.
    if (is_array($wai) && isset($wai["desk_ip"]) && trim((string)$wai["desk_ip"]) == $client_ip)
	return (true);

    return (false);
}

function presence_declaration_user_is_remote($user)
{
    if (is_array($user) && array_key_exists("remote", $user))
        return ((int)$user["remote"] != 0);
    if (is_object($user) && isset($user->remote))
        return ((int)$user->remote != 0);

    $id = NULL;
    if (is_array($user) && isset($user["id"]))
        $id = (int)$user["id"];
    else if (is_object($user) && isset($user->id))
        $id = (int)$user->id;
    if ($id == NULL || $id <= 0)
        return (false);

    $remote = db_select_one("
        remote
        FROM user
        WHERE id = ".$id."
        ");
    return ((int)$remote != 0);
}

function presence_declaration_check_physical_room($activity, $user)
{
    if (!presence_declaration_requires_locality($activity))
	return (new ValueResponse(true));
    if (presence_declaration_user_is_remote($user))
        return (new ValueResponse(true));
    if (presence_declaration_session_disables_locality($activity->unique_session))
	return (new ValueResponse(true));
    if (($wai = where_am_i($user)) == [])
	{
	    log_presence_declaration_refusal($activity, $user, "not in class");
	    return (new ErrorResponse("YouAreNotInClass"));
	}
    if (!presence_declaration_is_from_school_ip($wai))
    {
	log_presence_declaration_refusal($activity, $user, "wrong network", ["room" => $wai["id"]]);
	return (new ErrorResponse("YouAreNotInClass"));
    }
    foreach ($activity->unique_session->room as $r)
	if (isset($r["id"]) && (int)$r["id"] == (int)$wai["id"])
	    return (new ValueResponse(true));
    log_presence_declaration_refusal($activity, $user, "wrong room");
    return (new ErrorResponse("YouAreInAWrongRoom"));
}



function presence_declaration_log_contexts($activity, $user = NULL)
{
    $contexts = [];
    if (is_object($activity) && isset($activity->id))
	$contexts[] = ["activity", $activity->id];
    if (is_object($activity) && isset($activity->unique_session) && is_object($activity->unique_session) && isset($activity->unique_session->id))
	$contexts[] = ["session", $activity->unique_session->id];
    if (is_object($activity) && isset($activity->user_team) && is_array($activity->user_team) && isset($activity->user_team["id"]))
	$contexts[] = ["team", $activity->user_team["id"]];
    if (is_array($user) && isset($user["id"]))
	$contexts[] = ["user", $user["id"]];
    if (is_object($activity) && isset($activity->cycle) && is_array($activity->cycle))
	foreach ($activity->cycle as $cycle)
	    if (is_array($cycle) && isset($cycle["id"]))
		$contexts[] = ["cycle", $cycle["id"]];
    return ($contexts);
}

function log_presence_declaration_refusal($activity, $user, $reason, $details = [])
{
    $parts = [];
    if (is_array($user) && isset($user["codename"]))
	$parts[] = "user ".$user["codename"]." #".$user["id"];
    if (is_object($activity) && isset($activity->codename))
	$parts[] = "activity ".$activity->codename." #".$activity->id;
    if (is_object($activity) && isset($activity->unique_session) && is_object($activity->unique_session) && isset($activity->unique_session->id))
	$parts[] = "session #".$activity->unique_session->id;
    $parts[] = "reason ".$reason;
    $parts[] = "ip ".get_client_ip();
    foreach ($details as $k => $v)
	$parts[] = $k." ".$v;
    add_log(
	REPORT,
	"Presence declaration refused: ".implode(", ", $parts),
	-1,
	presence_declaration_log_contexts($activity, $user)
    );
}

function declare_presence($activity, $session, $user = NULL)
{
    global $User;
    global $five_minute;

    if ($user == NULL)
	$user = $User;
    if (!is_object($activity) && is_number($activity))
	($activity = new FullActivity)->build($activity);
    foreach ($activity->session as &$act)
    {
	if ($act->id == $session)
	{
	    $activity->unique_session = &$act;
	    break ;
	}
    }
    if (!$activity->unique_session)
	return (new ErrorResponse("ActivityNotFound"));
    if ($activity->registered_elsewhere)
	return (new ErrorResponse("RegisteredElsewhere"));
    if (!$activity->registered)
	return (new ErrorResponse("YouAreNotSubscribed"));
    if (!presence_declaration_is_available_for_activity($activity))
	return (new ErrorResponse("YouAreNotConcerned"));
    if ($activity->user_team["present"] != 0)
	return (new ErrorResponse("PresenceAlreadyDeclared"));
    if (date_to_timestamp($activity->unique_session->begin_date) - 2 * $five_minute > now())
	return (new ErrorResponse("DeclarationPeriodIsNotOpenedYet"));
    // Si la moitié de l'activité est passé, c'est mort.
    if (date_to_timestamp($activity->unique_session->begin_date) +
	(date_to_timestamp($activity->unique_session->end_date) -
	 date_to_timestamp($activity->unique_session->begin_date)) / 2
	< now())
    {
	if (($request = @update_table(
	    "team", $activity->user_team["id"], ["present" => -2, "declaration_date" => db_form_date(now())]
	))->is_error())
	    return ($request);
	return (new ErrorResponse("DeclarationPeriodIsClosed"));
    }
    
    // PRESENCE_PHYSICAL_ROOM_GUARD_BEGIN
    // Bloc volontairement très visible: commente ce bloc si tu veux
    // neutraliser temporairement la vérification Persoc/Distrans/Infosphere.
    // Règle: hors activité distante, l'étudiant doit être vu en X local,
    // non SSH et non verrouillé; si une salle est indiquée, il doit être
    // dans l'une des salles de la session.
    if (($physical_presence = presence_declaration_check_physical_room($activity, $user))->is_error())
	return ($physical_presence);
    // PRESENCE_PHYSICAL_ROOM_GUARD_END

    if (period(date_to_timestamp($activity->unique_session->begin_date) - 2 * $five_minute,
	       date_to_timestamp($activity->unique_session->begin_date) + $five_minute * 3))
    {
	if (($request = @update_table(
	    "team", $activity->user_team["id"], ["present" => 1, "declaration_date" => db_form_date(now())]
	))->is_error())
	    return ($request);
	return (new ValueResponse("PresenceDeclared"));
    }

    if (($request = @update_table(
	"team", $activity->user_team["id"],
	[
	    "present" => -1,
	    "declaration_date" => db_form_date(now()),
	    "late_time" => db_form_date(date_to_timestamp(now())
				      - date_to_timestamp($activity->unique_session->begin_date)),
	]
    ))->is_error())
        return ($request);
    return (new ValueResponse("YouAreLate"));    
}

