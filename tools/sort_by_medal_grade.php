<?php

function _sort_by_medal_grade($a, $b)
{
    if ($a["mandatory"])
	$aa = 4;
    else if ($a["grade_a"])
	$aa = 4;
    else if ($a["grade_b"])
	$aa = 3;
    else if ($a["grade_c"])
	$aa = 2;
    else if (isset($a["module_medal"]) && $a["module_medal"])
	$aa = 1;
    else if (isset($a["bonus"]) && $a["bonus"])
	$aa = -1;
    else
	$aa = 0;
    if ($b["mandatory"])
	$bb = 4;
    else if ($b["grade_a"])
	$bb = 4;
    else if ($b["grade_b"])
	$bb = 3;
    else if ($b["grade_c"])
	$bb = 2;
    else if (isset($b["module_medal"]) && $b["module_medal"])
	$bb = 1;
    else if (isset($b["bonus"]) && $b["bonus"])
	$bb = -1;
    else
	$bb = 0;
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

