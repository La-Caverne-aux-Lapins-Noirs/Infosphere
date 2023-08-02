<?php

function add_medal($user, $medal, $activity)
{
    return (edit_medal($user, $medal, $activity));
}

function edit_medal($user, $medal, $activity)
{
    global $Database;
    global $User;

    //////////////////////////////////////////////////////////////////
    // On demande a mettre une médaille pour une activité spécifique
    if ($activity != -1)
    {
	if (($activity = resolve_codename("activity", $activity))->is_error())
	    return ($activity);
	$activity = $activity->value;
	$act = new FullActivity;
	if ($act->build($activity, false, false) == false)
	    return (new ErrorResponse("InvalidEntry", $activity));
	if ($act->is_teacher == false)
	{
	    @add_log(REPORT, "I have tried to edit medal $medal for activity $activity to $user and I am not a teacher.");
	    return (new ErrorResponse("PermissionDenied"));
	}
    }
    // On a pas demandé une médaille spécifique, on a donc une médaille de profil
    // Seul les admins peuvent faire une chose pareille
    else if (!is_admin())
    {
	@add_log(REPORT, "I have tried to edit medal $medal for $user and I am not an admin.");
	return (new ErrorResponse("PermissionDenied"));
    }

    // On récupère les informations sur l'utilisateur.
    if (($user = resolve_codename("user", $user))->is_error())
	return ($user);
    $user = $user->value;

    // On effectue un traitement pour pouvoir agir sur un tableau de médaille
    if (!is_array($medal))
	$medal = [$medal];
    foreach ($medal as &$med)
    {
	if (is_number($med) && (int)$med >= 0 && (int)$med <= 20)
	    $med = "note".sprintf("%02d", (int)$med);
    }
    if (($medal = resolve_codename("medal", $medal))->is_error())
	return ($medal);
    if (!is_array($medal = $medal->value))
	$medal = [$medal];

    // On rassemble les médailles induites par celles qu'on ajoute
    do
    {
	$lastcount = count($medal);
	$allmed = [];
	foreach ($medal as $med)
	{
	    $allmed[$med] = $med;
	    $pfx = substr($med, 0, 1);
	    // Si c'est une demande de retrait ou d'ajout en echec, on n'induit rien.
	    if ($pfx == "#" || $pfx == "-")
		continue ;
	    $imp = db_select_all("id_implied_medal FROM medal_medal WHERE id_medal = ".$med);
	    foreach ($imp as $im)
	    {
		$im = $im["id_implied_medal"];
		$allmed[$im] = $im;
	    }
	}
	$medal = $allmed;
    }
    // On continue tant qu'on en a ajouté précédemment
    while (count($medal) != $lastcount);

    // On verifie que l'utilisateur est bien inscrit
    if ($activity != -1)
    {
	$sub = db_select_one("
           * FROM team
           LEFT JOIN user_team ON team.id = user_team.id_team
           WHERE user_team.id_user = $user
           AND team.id_activity = $activity
	");
	if ($sub == NULL)
	    return (new ErrorResponse("NotSubscribed"));
    }

    // On parcoure les médailles a ajouter
    $medtext = "";
    foreach ($medal as $med)
    {
	// On verifie qu'il n'a pas deja la médaille de cette activité.
	$status = 1;
	$deleted = false;
	$weak = false;
	if (substr($med, 0, 1) == "#") // Ajout d'une medaille en mode echec
	{
	    $med = substr($med, 1);
	    $status = -1;
	}
	else if (substr($med, 0, 1) == "-") // Retrait d'une medaille
	{
	    $med = substr($med, 1);
	    $deleted = true;
	}
	else if (substr($med, 0, 1) == "$") // Ajout d'une médaille en mode faible
	{
	    $med = substr($med, 1);
	    $status = 2;
	}

	// On cherche si une médaille a deja été acquise - ou marquée comme perdue
	$r = db_select_one("
                 activity_user_medal.id_activity as id_activity,
                 activity_user_medal.result as result,
                 user_medal.id as id,
                 activity_user_medal.id as id_activity_user_medal
          FROM user_medal
          LEFT OUTER JOIN activity_user_medal ON user_medal.id = activity_user_medal.id_user_medal
          WHERE user_medal.id_user = $user AND activity_user_medal.id_activity = $activity
          && user_medal.id_medal = $med
	");
	// Si on ne récupère rien, c'est que la médaille n'a jamais été acquise. On l'ajoute donc.
	if ($r == NULL)
	{
	    if ($deleted)
		continue ;
	    $Database->query("INSERT INTO user_medal (id_user, id_medal) VALUES ($user, $med)");
	    $last_id = $Database->insert_id;
	}
	// Si on récupère quelque chose, c'est que peut-être, l'activité a déja donné une medaille...
	// ou alors que la medaille est deja acquise. On verifie:
	else
	{
	    if ($deleted) // Retrait d'une medaille (quelque soit le mode)
	    {
		$Database->query("DELETE FROM activity_user_medal WHERE id = {$r["id_activity_user_medal"]}");
		continue ;
	    }
	    // L'activité a deja donné un résultat
	    if (isset($r["id_activity"]) && $r["id_activity"] == $activity && $r["result"] != 0)
	    {
		if ($r["result"] == -1)
		{
		    $Database->query("
                       UPDATE activity_user_medal
                       SET result = 1
                       WHERE id_activity = $activity
                       AND id_user_medal = {$r["id_activity_user_medal"]}
		       ");
		    return (new ValueResponse(""));
		}
		continue ;
	    }
	    $last_id = $r["id_activity_user_medal"];
	}
	$forge = "INSERT INTO activity_user_medal (id_user_medal, id_activity, result) VALUES ($last_id, $activity, $status)";
	$Database->query($forge);
	$medtext .= $med;
    }
    if ($medtext != "")
	add_log(TRACE, "I am adding a medal $medtext to user $user for activity $activity with status $status.");
    return (new ValueResponse(""));
}

