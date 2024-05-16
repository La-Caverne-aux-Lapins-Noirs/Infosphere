<?php

function edit_medalf($medal, array $conf)
{
    $def = ["user" => -1, "team" => -1, "user_team" => -1];
    $conf = array_merge($def, $conf);
    return (edit_medal($medal, $conf["user"], $conf["team"], $conf["user_team"]));
}

function edit_medal($medal, $user = -1, $team = -1, $user_team = -1)
{
    global $Database;
    global $User;

    $team = (int)$team;
    $user_team = (int)$user_team;
    $activity = -1;

    if ($user == -1 && $team == -1 && $user_team == -1)
	return (new ErrorResponse("InvalidParameter", "user, team, user_team"));
    if ($user_team != -1)
    {
	$user_team = db_select_one("
		user_team.id, user_team.id_user, user_team.id_team, team.id_activity
		FROM user_team LEFT JOIN team ON user_team.id_team = team.id
		WHERE user_team.id = $user_team
	");
	$activity = $user_team["id_activity"];
	$team = $user_team["id_team"];
	if ($user != -1 && $user != $user_team["id_user"])
	    return (new ErrorResponse("InvalidParameter", "user, user_team"));
	$user = $user_team["id_user"];
	$user_team = $user_team["id"];
    }
    else if ($team != -1)
    {
	$team = db_select_one("id, id_activity FROM team WHERE id = $team");
	$activity = $team["id_activity"];
	$team = $team["id"];
	$user_team = -1;
	$users = db_select_all("
	  * FROM user_team WHERE id_team = $team AND user_team.status > 0
	");
	if ($user == -1)
	{
	    foreach ($users as $usr)
	    {
		$usr = $usr["id_user"];
		if (($ret = edit_medal($medal, $usr, $team, -1))->is_error())
		    return ($ret);
	    }
	    return (new Response);
	}
	$fnd = false;
	foreach ($users as $usr)
	{
	    if ($usr["id_user"] != $user)
		continue ;
	    $fnd = true;
	    break ;
	}
	if (!$fnd)
	    return (new ErrorResponse("InvalidParameter", "user, team"));
    }

    if ($activity != -1)
    {
	if (($act = new FullActivity)->build($activity, false, false) == false)
	    return (new ErrorResponse("InvalidEntry", "activity $activity"));
	if ($act->parent_activity == -1 && !$act->is_teacher)
	{
	    @add_log(REPORT, "I have tried to edit medal $medal for module ".
			     "$activity to $user and I am not a teacher.");
	    return (new ErrorResponse("PermissionDenied"));
	}
	if (!$act->is_assistant)
	{
	    @add_log(REPORT, "I have tried to edit medal $medal for activity ".
			     "$activity to $user and I am not an assistant.");
	    return (new ErrorResponse("PermissionDenied"));
	}
    }
    else if (!is_director_for_student($user))
    {
	@add_log(REPORT, "I have tried to edit medal $medal for ".
			 "$user and I am not a director.");
	return (new ErrorResponse("PermissionDenied"));
    }

    if (($medal = split_symbols($medal))->is_error())
	return ($medal);
    $medal = $medal->value;
    // On effectue la distribution
    foreach ($medal as $med)
    {
	$med = get_prefix($med);
	if (is_number($med["label"])
	    && (int)$med["label"] >= 0 && (int)$med["label"] <= 20)
	$med = "note".sprintf("%02d", (int)$med["label"]);
	
	if (($id_medal = resolve_codename("medal", $med["label"]))->is_error())
	    return ($id_medal);
	$id_medal = $id_medal->value;

	// On cherche s'il existe une entrée similaire à celle qu'on a
	$um = db_select_one("
		* FROM user_medal
		WHERE id_user = $user
		AND id_medal = $id_medal
                AND id_activity = $activity
		AND id_team = $team
		AND id_user_team = $user_team	
	");
	
	// Retrait de médaille
	if ($med["negative"])
	{
	    if ($um == NULL)
		return (new ErrorResponse("NotFound"));
	    $Database->query("DELETE FROM user_medal WHERE id = ".$um["id"]);
	    add_log(TRACE, "Removing medal {$med["label"]}#$id_medal to $user for $activity, team $team, user team $user_team.");
	    continue ;
	}
	
	/// On verifie le prefixe
	$result = 1;
	$strength = 2;
	if (strchr($med["prefix"], "#") !== false)
	    $result = -1;
	else if (count($med["parameters"]))
	    $strength = (int)$med["parameters"][0];

	if ($um == NULL)
	{
	    $Database->query($str = "
	    INSERT INTO user_medal
	    (id_user, id_medal, id_activity, id_team, id_user_team, result, strength)
	    VALUES
	    ($user, $id_medal, $activity, $team, $user_team, $result, $strength)
	    ");
	    add_log(TRACE, "Adding a medal {$med["label"]}#$id_medal to $user for $activity, team $team, user team $user_team with results $result and strength $strength.");
	}
	else
	{
	    $Database->query("
            UPDATE user_medal SET result = $result, strength = $strength WHERE id = ".$um["id"]
	    );
	    add_log(TRACE, "Editing user_medal ".$um["id"]." with results $result and strength $strength.");
	}
    }
    return (new Response);
}

