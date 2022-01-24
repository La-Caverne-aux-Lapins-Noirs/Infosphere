<?php

function get_next_activities($pro)
{
    global $ActivityType;

    if ($pro == NULL)
	return ([]);
    $today = first_second_of_day(time());
    $tomorow_night = $today + 60 * 60 * 24 * 2;
    $act = [];
    foreach ($pro->sublayer as $cycle)
    {
	foreach ($cycle->sublayer as $module)
	{
	    foreach ($module->sublayer as $activity)
	    {
		if ($activity->type == -1)
		    continue ;
		if ($ActivityType[$activity->type]["type"] != 2)
		    continue ;
		if ($activity->begin_date < $today || $activity->begin_date > $tomorow_night)
		    continue ;
		if ($activity->end_date < time())
		    continue ;
		$act[$activity->id] = [
		    "begin_date" => $activity->begin_date,
		    "name" => $activity->name,
		    "id" => $activity->id,
		    "id_session" => $activity->id_session,
		    "soon" =>
			datex("d/m/Y", $activity->begin_date) == datex("d/m/Y", time())
		];
	    }
	}
    }
    return ($act);
}

