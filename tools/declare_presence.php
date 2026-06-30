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

function presence_declaration_check_physical_room($activity, $user)
{
    if (presence_declaration_is_remote_activity($activity))
	return (new ValueResponse(true));
    if (($wai = where_am_i($user)) == [])
	return (new ErrorResponse("YouAreNotInClass"));
    if (count($activity->unique_session->room) == 0)
	return (new ValueResponse(true));
    if ($activity->unique_session->room_space == -1)
	return (new ValueResponse(true));
    foreach ($activity->unique_session->room as $r)
	if ($r["id"] == $wai["id"])
	    return (new ValueResponse(true));
    return (new ErrorResponse("YouAreInAWrongRoom"));
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

