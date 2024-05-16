<?php

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
    if ($activity->user_team["present"] != 0)
	return (new ErrorResponse("PresenceAlreadyDeclared"));
    if (date_to_timestamp($activity->unique_session->begin_date) - 2 * $five_minute > now())
	return (new ErrorResponse("DeclarationPeriodIsNotOpenedYet"));
    if (date_to_timestamp($activity->unique_session->end_date) < now())
	return (new ErrorResponse("DeclarationPeriodIsClosed"));
    
    $position_valid = false;
    if (count($activity->unique_session->room) == 0)
	$position_valid = true;
    else if ($activity->unique_session->room_space == -1)
	$position_valid = true;
    else
    {
	if (($wai = where_am_i($user)) == [])
	    return (new ErrorResponse("YouAreNotInClass"));
	foreach ($activity->unique_session->room as $r)
	{
	    if ($r["id"] == $wai["id"])
	    {
		$position_valid = true;
		break ;
	    }
	}
	if ($position_valid == false)
	    return (new ErrorResponse("YouAreInAWrongRoom"));
    }

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

