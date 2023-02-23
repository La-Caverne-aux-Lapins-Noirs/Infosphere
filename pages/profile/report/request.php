<?php

$modules = explode(",", $_GET["modules"]);
$id_cycle = (int)$_GET["id_cycle"];

$Grade = ["E", "D", "C", "B", "A", "A"];
require_once (__DIR__."/open.phtml");

if (count($user->sublayer) > 1 && 0) // Sabotage
{
    require (__DIR__."/header.phtml");
    require (__DIR__."/summary.phtml");
    require (__DIR__."/footer.phtml");
}

$cycle = NULL;
foreach ($user->sublayer as $cyc)
{
    if ($cyc->id == $id_cycle)
    {
	$cycle = $cyc;
	break ;
    }
}
if ($cycle == NULL)
    return ;
require (__DIR__."/header.phtml");
foreach ($user->sublayer as $cycle)
{
    $fnd = 0;
    foreach ($cycle->sublayer as $mod)
    {
	if (in_array($mod->id, $modules))
	{
	    $fnd = $mod->id;
	    break ;
	}
    }
    if ($fnd)
	require (__DIR__."/cycle.phtml");
}
require (__DIR__."/footer.phtml");
require_once (__DIR__."/close.phtml");

