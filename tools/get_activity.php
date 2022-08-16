<?php

function get_activity($id_module)
{
    global $Dictionnary;
    global $Language;
    
    $module = db_select_one("
	activity.id,
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
	WHERE activity.id = $id_module
	GROUP BY activity.id
	ORDER BY activity.codename ASC
    ");
    $module["activity"] = db_select_all("
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
	WHERE activity.parent_activity = $id_module
	AND activity.enabled = 1
	AND activity.deleted IS NULL
	GROUP BY activity.id
	ORDER BY activity.codename ASC
    ");
    return ($module);
}
