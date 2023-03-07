<?php

$modules = explode(",", $_GET["modules"]);
$id_cycle = explode(",", $_GET["id_cycle"]);

foreach ($modules as &$mods)
    $mods = (int)$mods;
foreach ($id_cycle as &$ic)
    $ic = (int)$ic;

$Grade = ["E", "D", "C", "B", "A", "A"];
require_once (__DIR__."/open.phtml");

if (count($user->sublayer) > 1 && 0) // Sabotage
{
    require (__DIR__."/header.phtml");
    require (__DIR__."/summary.phtml");
    require (__DIR__."/footer.phtml");
}

$cycles = [];
$cyclesid = [];
foreach ($id_cycle as $cyc)
    $cyclesid[] = $cyc;
$first_cycle = NULL;
foreach ($user->sublayer as $cyc)
{
    if (array_search($cyc->id, $id_cycle) !== false)
    {
	if ($cyc->id == $id_cycle[0])
	    $first_cycle = $cyc;
	$cycles[] = $cyc;
	$cyclesid[] = $cyc->id;
	break ;
    }
}
if (count($cycles) == 0 || $first_cycle == NULL)
    return ;
require (__DIR__."/header.phtml");
foreach ($cycles as $cycle)
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

