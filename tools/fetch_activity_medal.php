<?php

$LoadedActivityMedal = [];

function fetch_activity_medal($id, $by_name = false, $type = 1) // id_activity
{
    global $Language;
    global $Configuration;

    if (isset($LoadedActivityMedal[$id]))
	return ($LoadedActivityMedal[$id]);
    
    $strong = in_array($type, [
	5, // Examen
	6, // QCM
	7, // Recode
	8, // Colle
	9, // Marathon
    ]);

    $all = db_select_all("
       medal.*,
       medal.{$Language}_name as name,
       medal.{$Language}_description as description,
       activity_medal.role as role,
       activity_medal.local as local,
       activity_medal.mark as mark,
       activity_medal.id as id_activity_medal,
       activity.parent_activity as parent_activity
       FROM activity_medal
       LEFT JOIN medal ON activity_medal.id_medal = medal.id
       LEFT JOIN activity ON activity_medal.id_activity = activity.id
       WHERE activity_medal.id_activity = $id
       AND medal.deleted IS NULL
       ORDER BY medal.codename ASC
    ", $by_name ? "codename" : "");
    foreach ($all as $k => &$v)
    {
	$v["role"] = (int)$v["role"];
	$v["local"] = (int)$v["local"];
	$v["mark"] = (int)$v["mark"];
	$v["strong"] = $strong;
    }
    
    if (($template = db_select_one("
       id_template, medal_template FROM activity WHERE id = $id
    ")) != NULL)
    {
	if ($template["id_template"] != -1 && $template["medal_template"])
	{
	    $all = array_merge($all, fetch_activity_medal(
		$template["id_template"], $by_name
	    ));
	}
    }

    foreach ($all as &$v)
    {
	$v["icon"] = $Configuration->MedalsDir($v["codename"])."/icon.png";
	$v["band"] = $Configuration->MedalsDir($v["codename"])."band.png";
	if (!file_exists($v["band"]))
	    $v["band"] = NULL;
    }

    $LoadedActivityMedal[$id] = $all;
    return ($all);
}


