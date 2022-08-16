<?php

function get_week_projects($pro)
{
    global $ActivityType;

    if ($pro == NULL)
	return ([]);
    $start = first_day_of_week(time());
    $stop = $start + 60 * 60 * 24 * 7;
    $act = [];
    foreach ($pro->sublayer as $cycle)
    {
	foreach ($cycle->sublayer as $module)
	{
	    foreach ($module->sublayer as $activity)
	    {
		if ($activity->type == -1)
		    continue ;
		if ($ActivityType[$activity->type]["type"] != 1)
		    continue ;
		if ($activity->pickup_date < $start || $activity->subject_appeir_date > $stop)
		    continue ;
		$act[$activity->id] = [
		    "pickup_date" => $activity->pickup_date,
		    "name" => $activity->name,
		    "id" => $activity->id,
		    "soon" => $activity->pickup_date > $start && $activity->pickup_date < $stop
		];
	    }
	}
    }
    return ($act);
}
