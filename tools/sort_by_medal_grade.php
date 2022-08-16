<?php

function _sort_by_medal_grade($a, $b)
{
    $aa = $a["role"];
    if (isset($a["module_medal"]) && $a["module_medal"])
	$aa = 1; // Grade D.
    $bb = $b["role"];
    if (isset($b["module_medal"]) && $b["module_medal"])
	$bb = 1; // Grade D;

    if ($bb - $aa == 0 && isset($b["success"]))
    {
	if ($a["success"] > 0)
	    $aa = 2;
	else if ($a["failure"] > 0)
	    $aa = 1;
	else
	    $aa = 0;
	if ($b["success"] > 0)
	    $bb = 2;
	else if ($b["failure"] > 0)
	    $bb = 1;
	else
	    $bb = 0;
    }
    return ($bb - $aa);
}

function sort_by_medal_grade(&$meds)
{
    usort($meds, "_sort_by_medal_grade");
    return ($meds);
}

