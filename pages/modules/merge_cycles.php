<?php

function mstrcmp($a, $b)
{
    $t = strcmp($a, $b);
    if ($t < 0)
	return (-1);
    if ($t > 0)
	return (1);
    return ($t);
}

function by_name($a, $b)
{
    $aa = strlen($a["name"]) ? $a["name"] : $a["codename"];
    $bb = strlen($b["name"]) ? $b["name"] : $b["codename"];
    AddDebugLogR($aa." ".$bb." ".mstrcmp($aa, $bb));
    return (mstrcmp($aa, $bb));
}

function merge_cycles($cycles)
{
    global $User;
    global $FUK;
    
    $fcycles = [];
    foreach ($cycles as $cycle)
    {
	$fd = $cycle["first_day"];
	if (!isset($fcycles[$fd]))
	{
	    $fcycles[$fd] = $cycle;
	    $fcycles[$fd]["matters"] = fetch_matters($User["id"], $cycle["id_cycle"]);
	}
	else
	{
	    if ($fcycles[$fd]["cycle"] < $cycle["cycle"])
		$fcycles[$fd]["cycle"] = $cycle["cycle"];
	    $nmatt = fetch_matters($User["id"], $cycle["id_cycle"]);
	    foreach ($nmatt as $nm)
		$fcycles[$fd]["matters"][$nm["id"]] = $nm;
	}
    }
    foreach ($fcycles as $cyc)
    {
	uasort($cyc["matters"], "by_name");
    }
    return ($fcycles);
}
