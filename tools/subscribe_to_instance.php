<?php

function subscribe_to_instance($activity, $login = NULL, $target_team = -1, $admin = false, $accept = false)
{
    global $Database;
    global $User;
    global $Auth;

    if (is_object($activity) == false && is_number($activity))
    {
	$x = new FullActivity;
	$x->build($activity, false, false, -1);
	$activity = $x;
    }

    // On recupere l'identifiant de l'utilisateur
    if ($login == NULL)
    {
	$id = $User["id"];
	$login = $User["codename"];
    }
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
		if (($x = subscribe_to_instance($activity, $log, $target_team, $admin))->is_error())
		    return ($x);
	    }
	    return (new ValueResponse(""));
	}
	else
	    $login = $logins[0];
	if (($id = resolve_codename("user", $login))->is_error())
	    return ($id);
	$id = $id->value;
    }

    if ($admin == false)
    {
	if ($activity->can_subscribe == false)
	    return (new ErrorResponse("NotMyActivity"));
	if (!period($activity->registration_date, $activity->close_date, time()))
	    return (new ErrorResponse("SubscriptionAreClosed"));
	if ($activity->registered)
	    return (new ErrorResponse("AlreadySubscribed"));
    }
    if ($activity->unique_session && $activity->unique_session->full)
	return (new ErrorResponse("RoomIsFull"));
    if (!$activity->unique_session && $activity->full)
	return (new ErrorResponse("RoomIsFull"));


    // On regarde si la demande est d'ajouter dans une equipe existante...
    if (!isset($target_team) || !is_number($target_team))
	$target_team = -1;
    if ($activity->unique_session)
	$id_session = $activity->unique_session->id;
    else
	$id_session = -1;

    // On regarde si l'utilisateur fait deja parti d'une equipe...
    $team = db_select_one("
      team.id as id, team.id_session as id_session
      FROM team
      LEFT JOIN user_team
        ON team.id = user_team.id_team
      WHERE
         team.id_activity = $activity->id
      && user_team.id_user = $id
    ");
    // On verifie si l'equipe n'est pas inscrite AILLEURS.
    if ($team != NULL && $id_session != $team["id_session"])
    	return (new ErrorResponse("SubscribedElsewhere"));

    // L'utilisateur ne fait parti d'aucune equipe.
    if ($team == NULL)
    {
	// Si on ne visait pas une equipe en particulier, on en crée une.
	if ($target_team == -1)
	{
	    $team_name = new_team_name();
	    if (!$Database->query
		("INSERT INTO team (team_name, id_activity, id_session) VALUES ('$team_name', $activity->id, $id_session)"))
		return (new ErrorResponse("CannotCreateNewTeam"));
	    $target_team = $Database->insert_id;
	    $team_id = $Database->insert_id;
	    add_log(TRACE, "New team $target_team");
	    if (!$Database->query("INSERT INTO user_team (id_team, id_user, status) VALUES ($target_team, $id, 2)"))
		return (new ErrorResponse("CannotAddUserToTeam"));
	    add_log(TRACE, "User $id join team $target_team");

	    // Si il y a des sous activité, on s'inscrit à tous ce qui est d'inscription automatique
	    $sub = db_select_all("
                activity.id FROM activity
                LEFT JOIN activity as template
                ON activity.id_template = template.id
	        WHERE activity.parent_activity = {$activity->id}
                AND activity.is_template = 0
                AND (activity.subscription = 2 OR (activity.subscription IS NULL AND template.subscription = 2))
		");
	    foreach ($sub as $s)
	    {
		if (($err = subscribe_to_instance($s["id"], $id, -1, $admin, $accept))->is_error())
		    return ($err);
	    }
	}
	// Sinon on verifie que l'equipe qu'on veut rejoindre existe
	else if (check_id("team", $target_team) == false)
	    return (new ErrorREsponse("TeamDoesNotExist"));
	else
	{
	    // L'equipe existe bel et bien.
	    // Est elle verrouillée ?
	    $tem = db_select_one("canjoin FROM team WHERE id = $target_team");
	    if ($tem["canjoin"] == false)
		return (new ErrorResponse("TeamIsLocked"));
	    //Y a t il assez de place?
	    // On verifie que l'equipe n'est pas pleine...
	    $total = $Database->query("
              SELECT COUNT(user_team.id) as cnt
              FROM user_team
              WHERE user_team.id_team = $target_team
              GROUP BY user_team.id_team
	    ");
	    $total = $total->fetch_assoc();
	    // Si l'équipe est pleine et qu'on est pas en train de forcer l'inscription.
	    if ($total["cnt"] >= $activity->min_team_size && $activity->is_teacher == false)
		return (new ErrorResponse("TeamIsFull"));
	    if (!$Database->query("INSERT INTO user_team (id_team, id_user, status) VALUES ($target_team, $id, ".($accept ? 1 : 0).")"))
		return (new ErrorResponse("CannotAddUserToTeam"));
	    add_log(TRACE, "User $id join team $target_team");
	    $team_id = $target_team;
	}
    }
    // L'utilisateur est deja dans une equipe.
    // Si aucune n'était précisée, ce n'est pas grave, on la prend.
    // Si une était précisé et que ce n'est pas la bonne, on arrête
    else if ($target_team == -1)
	$team_id = $team["id"];
    else if (($team_id = $team["id"]) != $target_team)
    	return (new ErrorResponse("AlreadySubscribed"));

    add_log(TRACE, "Team $team_id join activity $activity->id");
    return (new ValueResponse(["id_team" => $team_id]));
}
