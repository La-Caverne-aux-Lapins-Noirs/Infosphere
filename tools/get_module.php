<?php

function get_modules($template, $id = -1)
{
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
    
    return (db_select_all("
	activity.id,
	activity.{$Language}_name as name,
	activity.codename,
	activity.template_link,
	activity.medal_template,
	activity.support_template,
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
	AND activity.enabled = 1
	AND activity.parent_activity = -1
        $filter
	GROUP BY activity.id
	ORDER BY activity.codename ASC
    "));
}
