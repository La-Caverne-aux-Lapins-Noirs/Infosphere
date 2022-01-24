<?php

$activity = new FullActivity;
// -1 Pour charger toutes les sessions.
if ($activity->build($_GET["a"], false, false, -1) == false)
{
    $activity = NULL;
    $ErrorMsg = "ActivityNotFound";
    return ;
}
if ($session != -1)
{
    // Permet de placer une "unique_session" tout en chargeant les autres
    foreach ($activity->session as &$act)
    {
	if ($act->id == $session)
	{
	    $activity->unique_session = &$act;
	}
    }
    if ($activity->unique_session == NULL) // On a un probleme là...
    {
	$activity = NULL;
	$ErrorMsg = "ActivityNotFound";
	return ;
    }
}
else
{
    if ($activity->unique_session)
	$_GET["b"] = $activity->unique_session->id;
}
