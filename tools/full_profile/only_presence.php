<?php

function only_presence($user, $data)
{
    $p = [];
    foreach ($data as $cycle)
    {
	$modules = $cycle["module"];
	foreach ($modules as $module)
	{
	    $activities = $module["activity"];
	    foreach ($activities as $act)
	    {
		if ($act["present"] != 0 && ($act["type"] <= 13 || $act["type"] == 19))
		{
		    $x["student"] = $user["codename"];
		    $x["cycle"] = $cycle["codename"];
		    $x["matter"] = $act["parent_name"];
		    $x["activity_name"] = $act["name"];
		    $x["activity_codename"] = $act["codename"];
		    $x["date"] = $act["begin_date"];
		    $x["present"] = $act["present"];
		    $p[] = $x;
		}
	    }
	}
    }
    return ($p);
}
