<?php

function sort_matters($a, $b)
{
    global $user;

    $ar = $a->registered;
    $br = $b->registered;
    if ($ar != $br)
	return ($br - $ar);
    return (strcmp($a->name, $b->name));
}

//$modules = explode(",", $_GET["modules"]);
$id_cycle = explode(",", $_GET["id_cycle"]);

/*
foreach ($modules as &$mods)
    $mods = (int)$mods;
*/
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
    }
}
if (count($cycles) == 0 || $first_cycle == NULL)
    return ;
require (__DIR__."/header.phtml");
$mods = [];
foreach ($cycles as $cycle)
{
    foreach ($cycle->sublayer as $mod)
    {
	$mods[] = $mod;
    }
}
usort($mods, "sort_matters");
require (__DIR__."/cycle.phtml");
require (__DIR__."/footer.phtml");
require_once (__DIR__."/close.phtml");

