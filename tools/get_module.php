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

    if (!is_admin())
	$filter = " AND activity_teacher.id_user = {$User["id"]}";
    else
	$filter = "";

    $oldies = " AND (activity.done_date > NOW() OR activity.done_date IS NULL) ";
    if ((isset($_COOKIE["get_old_activity"]) && $_COOKIE["get_old_activity"]) || $template)
	$oldies = "";

    $filterc = "";
    $page = $template ? "template" : "module";
    if (isset($_COOKIE["filter_activity_$page"]))
    {
	if ($_COOKIE["filter_activity_$page"] != "")
	{
	    $filterc = $_COOKIE["filter_activity_$page"];
	    $filterc = str_replace("*", "%", $filterc);
	    $filterc = $Database->real_escape_string($filterc);
	    $filterc = " AND activity.codename LIKE '$filterc' ";
	}
    }
    
    return (db_select_all("
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
    "));
}
