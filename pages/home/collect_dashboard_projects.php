<?php

function collect_dashboard_projects($now)
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
       activity.id,
       activity.pickup_date,
       activity.{$Language}_name as name,
       activity.close_date
       FROM activity
       LEFT JOIN activity as matter ON activity.parent_activity = matter.id
       LEFT JOIN team ON matter.id = team.id_activity
       LEFT JOIN user_team ON team.id = user_team.id_team
       WHERE activity.registration_date <= '".db_form_date($now - 60 * 60 * 24)."'
         AND activity.pickup_date >= '".db_form_date($now)."'
         AND activity.deleted IS NULL
         AND matter.deleted IS NULL
         AND user_team.id_user = {$User["id"]}
       ORDER BY pickup_date ASC
	 ");
    foreach ($sesstmp as $sess)
    {
	$s = new FullActivity;
	$s->build($sess["id"], false, false);
	if (filter_out_sessions($s, $wlist))
	    continue ;

	if ($sess["close_date"] != NULL && date_to_timestamp($sess["close_date"]) < now() && $s->registered == false)
	    continue ;

	$sess["name"] = $s->name;
	$sess["pickup_date"] = $s->pickup_date;
	if ($s->pickup_date != NULL)
	    $sess["soon"] = (first_day_of_week($now) + 60 * 60 * 24 * 7) > $s->pickup_date;
	else
	    $sess["soon"] = false;

	$sess["subscribed"] = $s->registered;
	if ($s->close_date != NULL)
	    $sess["subsoon"] = (first_day_of_week($now) + 60 * 60 * 24 * 7) > $s->close_date;
	else
	    $sess["subsoon"] = false;

	$activities[] = $sess;
    }
    return ($activities);
}

