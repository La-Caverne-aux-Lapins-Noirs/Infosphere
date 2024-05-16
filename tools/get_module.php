<?php

function get_modules($template, $id = -1)
{
    global $Database;
    global $Language;
    global $User;

    $template = $template ? 1 : 0;
    if ($id != -1)
	$id = " AND activity.id = $id ";
    else
	$id = "";

    $filter = "";
    if (!is_director())
    {
	if (!is_cycle_director())
	{
	    $filter = " AND activity_teacher.id_user = {$User["id"]}";
	    //// IL MANQUE SI ON EST PROF VIA UN LABORATOIRE
	}
	else
	{
	    $filter = " OR (1 ";
	    foreach ($User["cycle"] as $cyc)
	    {
		if ($cyc["authority"] <= 1)
		    continue ;
		$filter .= " AND activity_cycle.id_cycle = {$cyc["id_cycle"]} ";
	    }
	    $filter .= " ) ";
	}
    }

    $oldies = " AND (activity.done_date > NOW() OR activity.done_date IS NULL) ";
    if ((isset($_COOKIE["get_old_activity"]) && $_COOKIE["get_old_activity"]) || $template)
	$oldies = "";

    $filterc = "";
    $page = $template ? "template" : "module";
    if (isset($_COOKIE["filter_activity_$page"]) &&
	$_COOKIE["filter_activity_$page"] != "")
    {
	$cfilter = $_COOKIE["filter_activity_$page"];
	$cfilter = str_replace("XXXSEPARATORXXX", ";", $cfilter);
	if (!($syms = split_symbols($cfilter, ";", true, true, "", ["*"]))->is_error())
	{
	    $ins = ["0"];
	    $outs = ["1"];
	    $syms = $syms->value;
	    foreach ($syms as $sym)
	    {
		$sym = str_replace("*", "%", $sym);
		$forge = " activity.codename ";
		if ($sym[0] == '-')
		{
		    $sym = substr($sym, 1);
		    $sym = $Database->real_escape_string($sym);
		    $outs[] = " activity.codename NOT LIKE '$sym'";
		}
		else
		{
		    $sym = $Database->real_escape_string($sym);
		    $ins[] = " activity.codename LIKE '$sym'";
		}
	    }
	    $filterc = " AND (( ".implode(" OR ", $ins).
		       " ) AND ( ".
		       implode(" AND ", $outs).
		       " )) ";
	}
    }
    $request = "
	activity.id,
	activity.{$Language}_name as name,
	activity.codename,
	activity.template_link,
	activity.id_template,
        activity.is_template,
	activity.medal_template,
	activity.support_template,
	activity.done_date,
	COUNT(activity_teacher.id) as nbr_teacher,
	COUNT(activity_support.id) as nbr_class,
	COUNT(activity_cycle.id) as nbr_cycle,
	COUNT(activity_medal.id) as nbr_medal
	FROM activity
	LEFT OUTER JOIN activity_teacher ON activity.id = activity_teacher.id_activity
	LEFT OUTER JOIN activity_support ON activity.id = activity_support.id_activity
	LEFT OUTER JOIN activity_medal ON activity.id = activity_medal.id_activity
	LEFT OUTER JOIN activity_cycle ON activity.id = activity_cycle.id_activity
	WHERE activity.is_template = $template $id
	AND activity.deleted IS NULL
	AND activity.disabled IS NULL
	AND (activity.parent_activity IS NULL OR activity.parent_activity = -1)
        $oldies
        $filter
        $filterc
	GROUP BY activity.id
	ORDER BY activity.codename ASC
    ";
    return (db_select_all($request));
}
