<?php

$begin = db_form_date(now() - 3 * 60);
$end = db_form_date(now());
$now = db_form_date(now());

$ccycles = db_select_all("* FROM cycle WHERE deleted = 0 AND done = 0");

foreach ($ccycles as $cycle)
{
    $students = db_select_all("
      user.codename FROM user_cycle LEFT JOIN user ON user_cycle.id_user = user.id
      WHERE id_cycle = {$cycle["id"]}
      ");
    $studlist = [];
    foreach ($students as $stud)
    {
	$studlist[] = $stud["codename"];
    }
    $students = implode(";", $studlist);

    // On liste les modules a l'inscription automatique (subscription = 2)
    $modules = db_select_all("
	activity.id
	FROM activity_cycle
        LEFT JOIN activity ON activity_cycle.id_activity = activity.id
        LEFT JOIN activity as template ON activity.id_template = template.id
 	WHERE activity_cycle.id_cycle = {$cycle["id"]}
          AND activity.is_template = 0
          AND (activity.subscription = 2 OR template.subscription = 2)
          AND (activity.emergence_date <= '$now' OR activity.emergence_date IS NULL)
          AND (activity.done_date > '$now' OR activity.done_date IS NULL)
          AND (activity.registration_date <= '$now' OR activity.registration_date IS NULL)
          AND (activity.close_date > '$now' OR activity.close_date IS NULL)
	");
    foreach ($modules as $module)
    {
	subscribe_to_instance($module["id"], $students, -1, true, true);
    }
}
