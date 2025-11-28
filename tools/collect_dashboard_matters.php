<?php

function collect_dashboard_matters($now)
{
    global $Language;
    global $User;

    $wlist["cycle"] = [];
    foreach ($User["cycle"] as $cycle)
    {
	$wlist["cycle"][] = $cycle["id_cycle"];
    }
    $activities = [];
    $sesstmp = db_select_all("
       matter.id,
       matter.pickup_date,
       matter.{$Language}_name as name,
       matter.registration_date,
       matter.close_date
       FROM activity as matter
       LEFT JOIN team ON matter.id = team.id_activity
       LEFT JOIN user_team ON team.id = user_team.id_team
       WHERE matter.registration_date <= '".db_form_date($now - 60 * 60 * 24)."'
         AND matter.close_date >= '".db_form_date($now)."'
         AND matter.deleted IS NULL
         AND (matter.parent_activity = -1 OR matter.parent_activity IS NULL)
       ORDER BY close_date ASC
	 ");
    foreach ($sesstmp as $sess)
    {
	$s = new FullActivity;
	$s->build($sess["id"], false, false);
	if (filter_out_sessions($s, $wlist))
	    continue ;

	if ($sess["registration_date"] != NULL && date_to_timestamp($sess["registration_date"]) >= now())
	    continue ;
	if ($sess["close_date"] != NULL && date_to_timestamp($sess["close_date"]) < now())
	    continue ;
	if ($s->registered)
	    continue ;
	if ($s->subscription == 2)
	    continue ;

	$sess["name"] = $s->name;
	$sess["close_date"] = $s->close_date;
	if ($s->subscription != 2 && $s->registration_date != NULL && $s->close_date != NULL)
	    $sess["soon"] = (first_day_of_week($now) + 60 * 60 * 24 * 7) > $s->close_date;
	else
	    $sess["soon"] = false;
	$activities[] = $sess;
    }
    
    return ($activities);
}

