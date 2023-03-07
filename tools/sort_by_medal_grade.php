<?php

$_medal_grade_consider_acquisition = false;

function _sort_by_medal_grade($a, $b)
{
    global $_medal_grade_consider_acquisition;
        
    if ($_medal_grade_consider_acquisition)
    {
	$left = 0;
	$right = 0;
	if (isset($a["success"]) && $a["success"] != 0)
	    $left = 1;
	if (isset($b["success"]) && $b["success"] != 0)
	    $right = 1;
	if ($left != $right)
	    return ($right - $left);
    }
    return ($b["role"] - $a["role"]);
}

function sort_by_medal_grade(&$meds, $profile = true)
{
    global $_medal_grade_consider_acquisition;

    $_medal_grade_consider_acquisition = $profile;
    usort($meds, "_sort_by_medal_grade");
    return ($meds);
}

