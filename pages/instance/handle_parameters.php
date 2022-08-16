<?php
$activity = NULL;
if (!isset($User) || !isset($User["codename"]) || !isset($User["authority"]))
    return ;
if (!@is_number($_GET["a"]))
{
    $ErrorMsg = "NoInstanceRequested";
    return ;
}

if (@is_number($_GET["b"]))
    $session = $_GET["b"];
else
    $session = -1;

require ("build_activity.php");

if (try_get($_GET, "fetch") == 1)
{
    if ($activity->is_teacher == false)
    {
	echo "You don't have the right to do that.";
	exit ;
    }
    if ($activity->unique_session)
	$wrks = &$activity->unique_session->team;
    else
	$wrks = &$activity->team;
    $work = [];
    foreach ($wrks as $wrk)
    {
	foreach ($wrk["work"] as $deli)
	{
	    // En attendant de faire autrement...
	    if ($deli["status"] == "automatic")
	    {
		$work[] = $deli["repository"];
	    }
	}
    }
    if ($work == [])
	exit ;
    $dir = dirname($work[0]);
    foreach ($work as &$w)
    {
	$w = basename($w);
    }
    $work = implode(" ", $work);
    export_command("work.tar.gz", "tar cvz -C $dir $work ");
}


