<?php

function collect_dashboard_activities($start, $end, $managed)
{
    global $Language;
    global $User;

    $wlist["cycle"] = [];
    foreach ($User["cycle"] as $cycle)
    {
	$wlist["cycle"][] = $cycle["id_cycle"];
    }
    $activities = [
	"managed" => [],
	"participate" => []
    ];
    $sesstmp = db_select_all("
        session.id as sid, session.begin_date, session.end_date, activity.*,
        activity.{$Language}_name as name, matter.id as id_matter
        FROM session
        LEFT JOIN activity ON session.id_activity = activity.id
        LEFT JOIN activity as matter ON activity.parent_activity = matter.id
        WHERE session.begin_date >= '".db_form_date($start)."'
          AND session.end_date <= '".db_form_date($end)."'
          AND session.deleted IS NULL
          AND activity.deleted IS NULL
          AND activity.is_template = 0
          AND activity.parent_activity != -1
        GROUP BY session.id
        ORDER BY session.begin_date ASC
	  ");
    foreach ($sesstmp as $session)
    {
	$s = new FullActivity;
	$s->build($session["id"], false, false, $session["sid"]);
	if ($managed == false)
	{
	    $m = new FullActivity;
	    $m->build($session["id_matter"], false, false);
	    if ($m->registered == false)
		continue ;
	    if (filter_out_sessions($s, $wlist))
		continue ;
	}
	$session["name"] = $s->name;
	$session["id_session"] = $session["sid"];
	$session["soon"] = datex("z", date_to_timestamp($session["begin_date"])) - datex("z", time());
	if ($managed)
	{
	    if ($s->is_assistant)
	    {
		$session["week"] =
		    first_day_of_week(time()) +
		    60 * 60 * 24 * 7 < date_to_timestamp($session["begin_date"]
		    );
		$activities["managed"][] = $session;
	    }
	}
	else
	    $activities["participate"][] = $session;
    }
    return ($activities);
}

