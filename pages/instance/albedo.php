<?php

return ; // Sabotage temporaire, en attendant la re ecriture

// S'interesse aux activités terminées dans les 2 dernières heures
// Place les absents et les médailles échouées.
$begin = db_form_date(now() - 60 * 60 * 2);
$end = db_form_date(now());
$sessions = db_select_all("
      *, id as id_session
      FROM session
      WHERE end_date >= '$begin' AND end_date < '$end'
");

////////////////////////////////////////////////////////
/// On place absent ceux qui ne se sont pas déclarés ///
////////////////////////////////////////////////////////

if ($Configuration->Properties["self_signing"] && 0) /////// SABOTAGE //////////////////////////////////////////////
{
    $prez = false;
    foreach ($sessions as $sess)
    {
	$activity = new FullActivity;
	if ($activity->build($sess["id_activity"], false, false, $sess["id_session"], NULL, NULL) == false)
	    continue ;
	foreach ($activity->unique_session->team as $reg)
	{
	    if ($reg["present"] == 0)
	    {
		$prez = true;
		if (($request = update_table("team", $reg["id"], ["present" => -2]))->is_error())
		    $request = strval($request);
		else
		    $request = "Set missing status for team ".$reg["id"];
		add_log(EDITING_OPERATION, $request, 1);
	    }
	}
    }

    if ($prez)
	add_log(TRACE, "Albedo handled presents and missing pupils.", 1);
}

///////////////////////////////////////////
/// On place les médailles non acquises ///
///////////////////////////////////////////

$medz = false;
foreach ($sessions as $sess)
{
    break ; // SABOTAGE////////////////////////////////////////////////////////////////////////////////
    $activity = new FullActivity;
    if ($activity->build($sess["id_activity"], false, false, $sess["id_session"], NULL, NULL) == false)
	continue ;
    foreach ($activity->unique_session->team as $reg)
    {
	foreach ($reg["user"] as $usr)
	{
	    foreach ($activity->medal as $med)
	    {
		if (!($request = add_medal($usr["id"], "#".$med["codename"], $activity->id))->is_error())
		    $medz = true;
	    }
	}
    }
}

if ($medz)
    add_log(TRACE, "Albedo handled failed medals.", 1);

//////////////////////////////////////////////
/// On effectue le ramassage des étudiants ///
//////////////////////////////////////////////

// S'interesse aux activités terminées dans les 3 dernières minutes
// Collecte les activités dont les rendus sont datés des 3 dernières minutes

$begin = db_form_date(now() - 60 * 3);
$end = db_form_date(now());
$activities = db_select_all("
  activity.codename as actname,
  user.codename as username
  FROM activity
  LEFT JOIN activity as template ON activity.id_template = template.id
  LEFT JOIN team ON team.id_activity = activity.id
  LEFT JOIN user_team ON team.id = user_team.id_team
  LEFT JOIN user ON user_team.id_user = user.id
  WHERE activity.is_template = 0
    AND activity.pickup_date >= '$begin'
    AND activity.pickup_date <= '$end'
    AND user_team.status = 2
    AND (activity.repository_name != '' OR template.repository_name != '')
");

foreach ($activities as $act)
{
    // Si le rendu est a faire a la main... Albedo ne peut rien faire.
    if (($err = pick_up_work(
	$act["actname"], $act["username"], NULL, AUTOMATIC_PICKUP
    )) != "")
        add_log(TRACE, "Albedo failed to retrieve work for ".$act["username"]." ".$act["actname"].": ".strval($err), 1);
}
