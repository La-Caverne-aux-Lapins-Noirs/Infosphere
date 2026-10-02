<?php

function destroy_team_fully($team_id)
{
    global $Database;
    $Database->query("DELETE FROM user_team WHERE id_team = $team_id"); // Pour retirer les membres non confirmés.
    add_log(TRACE, "Deleting team #$team_id subscriptions");
    $Database->query("DELETE FROM team WHERE id = $team_id");
    $Database->query("UPDATE appointment_slot SET id_team = -1 WHERE id_team = $team_id");
    add_log(TRACE, "Deleting team #$team_id which is now empty");
}

function unsubscribe_user_from_team($team_id, $id_user)
{
    global $Database;

    $team_id = (int)$team_id;
    $id_user = (int)$id_user;
    if ($team_id <= 0 || $id_user <= 0)
        return (new ErrorResponse("InvalidParameter"));
    $membership = db_select_one("id, status FROM user_team WHERE id_team = $team_id AND id_user = $id_user");
    if ($membership == NULL)
        return (new ErrorResponse("TeamDoesNotExist"));

    $count = db_select_one("COUNT(user_team.id) as count FROM user_team WHERE id_team = $team_id");
    $count = (int)($count["count"] ?? 0);
    $status = (int)$membership["status"];
    $Database->query("DELETE FROM user_team WHERE id_user = $id_user AND id_team = $team_id");
    $idcn = get_codename("user", $id_user);
    add_log(TRACE, "Remove user $idcn #$id_user from team #$team_id");

    if ($count - 1 <= 0)
        destroy_team_fully($team_id);
    else if ($status == 2)
    {
        $all = db_select_all("* FROM user_team WHERE id_team = $team_id AND status != 0");
        if ($all == [])
            destroy_team_fully($team_id);
        else
        {
            shuffle($all);
            $Database->query("UPDATE user_team SET status = 2 WHERE id = ".(int)$all[0]["id"]);
            $new_id = (int)$all[0]["id_user"];
            $new_codename = get_codename("user", $new_id);
            add_log(TRACE, "Promoting $new_codename #$new_id to administrator of team #$team_id");
        }
    }
    return (new ValueResponse(""));
}

function unsubscribe_from_instance($activity, $login = NULL, $admin = false)
{
    global $Database;
    global $User;

    if (is_object($activity) == false && is_number($activity))
    {
	$x = new FullActivity;
	$x->build($activity, false, false, -1);
	$activity = $x;
    }
    if ($admin == false)
    {
	if ($activity->can_subscribe == false && $activity->registered == false)
	    return (new ErrorResponse("NotMyActivity"));
	if ($activity->registered == false)
	    return (new ErrorResponse("YouAreNotSubscribed"));
	if ($activity->allow_unregistration == false)
	    return (new ErrorResponse("UnsubscribeForbidden"));
	if (!period($activity->registration_date, $activity->close_date, now()))
	    return (new ErrorResponse("SubscriptionAreClosed"));
    }

    if ($activity->unique_session)
	$id_session = $activity->unique_session->id;
    else
	$id_session = -1;

    if ($login == NULL)
	$id = $User["id"];
    else
    {
	if (($logins = split_symbols($login))->is_error())
    return ($logins);
	$logins = $logins->value;
	if (count($logins) > 1)
	{
	    if ($admin == false)
		return (new ErrorResponse("PermissionDenied"));
	    foreach ($logins as $log)
	    {
		if (($x = unsubscribe_from_instance($activity, $log, $admin))->is_error())
		    return ($x);
	    }
	    return (new ValueResponse(""));
	}
	else
	    $login = $logins[0];
	if (($id = resolve_codename("user", $login))->is_error())
	    return ($id);
	if (($id = $id->value) < 0)
	    $id = -$id;
    }

    // On recupere des informations sur l'equipe. C'est quel equipe?
    if (count($team = db_select_one("
      team.id as id
      FROM team
      LEFT JOIN user_team ON user_team.id_team = team.id
      WHERE team.id_activity = $activity->id AND user_team.id_user = $id
      GROUP BY team.id
      ")) == 0)
        return (new ErrorResponse("TeamDoesNotExist"));
    $team_id = $team["id"];

    return (unsubscribe_user_from_team($team_id, $id));
}
