<?php
////////////////////////////////////////////////////////
/// On place absent ceux qui ne se sont pas déclarés ///
////////////////////////////////////////////////////////

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
