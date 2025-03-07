<?php
if (!isset($albedo) || $albedo != 1)
    return ;

////////////////////////////////////////////
// INSCRIPTIONS AUTOMATIQUES AUX MATIERES //
////////////////////////////////////////////

// On selectionne les cycles en cours
$cycles = db_select_all("
    codename, id FROM cycle
    WHERE
    ( done IS NULL OR done = 0 )
    AND ( is_template IS NULL OR is_template = 0 )
    AND deleted IS NULL
");
foreach ($cycles as $cycle)
{
    $cycle = $cycle["id"];
    $users = db_select_all("
        user.id FROM user LEFT JOIN user_cycle ON user.id = user_cycle.id_user
        WHERE user_cycle.id_cycle = $cycle
    ");

    $modules = db_select_all("
	activity.id,
        activity.codename,
	activity_cycle.replacement_subscription as rsub,
        activity.subscription as sub
        FROM activity_cycle
	LEFT JOIN activity ON activity.id = activity_cycle.id_activity
	WHERE activity_cycle.id_cycle = $cycle
	AND ( parent_activity IS NULL OR parent_activity = -1 )
        AND ( subscription = 2 OR replacement_subscription = 2 )
        AND ( registration_date IS NULL OR registration_date <= NOW() )
        AND ( close_date IS NULL OR close_date > NOW() )
    ");
    foreach ($modules as $module)
    {
	if ($module["rsub"] != NULL)
	    $module["sub"] = $module["rsub"];
	$module = $module["id"];
	foreach ($users as $usr)
	{
	    $usr = $usr["id"];
	    if (!db_select_one("
		* FROM team LEFT JOIN user_team ON team.id = user_team.id_team
		WHERE user_team.id_user = $usr AND team.id_activity = $module
	    "))
	    {
		if (($err = subscribe_to_instance($module, $usr, -1, true, true))->is_error())
		    add_log(WARNING, (string)$err);
	    }
	}
    }
}


////////////// REPRENDRE DANS CE FICHIER
