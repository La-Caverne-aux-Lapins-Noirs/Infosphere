<?php

function collect_dashboard_projects($now)
{
    global $Language;
    global $User;

    $wlist["cycle"] = [];
    foreach ($User["cycle"] as $cycle)
    {
	$wlist["cycle"][] = $cycle["id"];
    }
    $activities = [];
    $sesstmp = db_select_all("
       activity.id, pickup_date, {$Language}_name as name
       FROM activity
       LEFT JOIN team ON activity.id = team.id_activity
       LEFT JOIN user_team ON team.id = user_team.id_team
       WHERE subject_appeir_date <= '".db_form_date($now - 60 * 60 * 24)."'
         AND pickup_date >= '".db_form_date($now)."'
         AND deleted = 0
         AND user_team.id_user = {$User["id"]}
       ORDER BY pickup_date ASC
	 ");
    foreach ($sesstmp as $sess)
    {
	$s = new FullActivity;
	$s->build($sess["id"], false, false);
	if (filter_out_sessions($s, $wlist))
	    continue ;
	$sess["name"] = $s->name;
	$sess["pickup_date"] = $s->pickup_date;
	$sess["soon"] = (first_day_of_week($now) + 60 * 60 * 24 * 7) > $s->pickup_date;

	$activities[] = $sess;
    }
    return ($activities);
}

