<?php
////////////////////////////////////////////////////////
/// On place absent ceux qui ne se sont pas déclarés ///
////////////////////////////////////////////////////////

if (!isset($albedo) || $albedo != 1)
    return ;

if (!isset($sessions) || !is_array($sessions))
    return ;

$prez = 0;
foreach ($sessions as $sess)
{
    $activity = new FullActivity;

    if ($activity->build($sess["id_activity"], false, false, $sess["id_session"], NULL, NULL) == false)
    {
	add_log(REPORT, "Self signing skipped: activity {$sess["id_activity"]} cannot be loaded for session {$sess["id_session"]}", 1);
	continue ;
    }
    if (!presence_declaration_is_available_for_activity($activity))
	continue ;
    if ($activity->unique_session == NULL || !isset($activity->unique_session->team))
	continue ;

    foreach ($activity->unique_session->team as $reg)
    {
	if ((int)$reg["present"] != 0)
	    continue ;
	$prez += 1;
	if (($request = update_table("team", $reg["id"], [
	    "present" => -2,
	    "declaration_date" => db_form_date(now())
	]))->is_error())
	    $request = strval($request);
	else
	    $request = "Set missing status for team ".$reg["id"]." after session ".$sess["id_session"];
	add_log(EDITING_OPERATION, $request, 1);
    }
}

if ($prez)
    add_log(TRACE, "Albedo handled presents and missing pupils: $prez team(s) marked missing.", 1);
