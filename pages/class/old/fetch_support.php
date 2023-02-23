<?php

function fetch_template_support($acts)
{
    foreach ($acts as $a)
    {

    }
}

function fetch_support($user = NULL)
{
    global $User;
    global $Language;

    if ($user == NULL)
	$user = $User;
    $domain = [];
    $activities = [];

    // I am admin, fetch all
    if (is_admin($user) || 1)
    {
	$matters = db_select_all("
          id, codename,
          {$Language}_name as class_name,
          {$Language}_description as class_description
          FROM class
          WHERE deleted IS NULL
          ORDER BY {$Language}_name ASC
	", "id");
	foreach ($matters as $mat)
	{
	    $domain[$mat["id"]]["data"] = $mat;
	    $domain[$mat["id"]]["content"] = db_select_all("
               id as id_class_asset,
               {$Language}_name as asset_name,
               {$Language}_link as asset_link,
               {$Language}_content as asset_content
               FROM class_asset
               WHERE deleted IS NULL
                 AND id_class = {$mat["id"]}
               ORDER BY chapter
		 ", "id_class_asset");
	}
	return ($domain);
    }

    // I am teacher, fetch what I teach
    $acts = get_all_managed_activities($user);
    foreach ($acts as $act)
    {
	$as = db_select_all("
		*
                FROM activity_support
                WHERE id_activity = $act
	", "id");
	if ($as == NULL || count($as) == 0)
	    continue ;
	$activities = array_merge($activities, $as);
    }

    // I am pupil, fetch what I study
    foreach ($user["cycle"] as $cycle)
    {
	$acts = db_select_all("
              activity_support.*
              FROM activity_cycle
              LEFT JOIN activity ON activity.id = activity_cycle.id_activity
              LEFT JOIN activity as template ON activity.id_template = template.id
              LEFT JOIN activity_support
                     ON activity.id = activity_support.id_activity
                     OR template.id = activity_support.id_activity
              WHERE id_cycle = {$cycle["id"]} AND activity.deleted IS NULL
              GROUP BY activity_support.id_activity
	      ", "id");
	AddDebugLogR($acts);
	if ($acts == NULL || count($acts) == 0)
	    continue ;
	$activities = array_merge($activities, $acts);
    }

    foreach ($activities as $act)
    {
	if ($act["id_subactivity"] != -1
	    && $act["id_subactivity"] != NULL
	    && $act["id_subactivity"] != "")
	{
	    continue ;
	}
	// On veut un seul element, on s'arrange pour tout recuperer
	if ($act["id_class_asset"] != -1)
	{
	    $m = db_select_one("
               id_class
               FROM class_asset
               WHERE id = {$act["id_class_asset"]}
                AND deleted IS NULL
	       ");
	    $act["id_class"] = $m["id_class"];
	}

	if ($act["id_class"] != -1 && $act["id_class"] != NULL)
	{
	    if (isset($domain[$act["id_class"]]))
		continue ;
	    if (($mod = db_select_one("
	      id, codename,
              {$Language}_name as class_name,
              {$Language}_description as class_description
	      FROM class
              WHERE id = ".$act["id_class"]." AND deleted IS NULL
	      ")) == NULL)
	    {
		continue ;
	    }
	    $domain[$act["id_class"]]["data"] = $mod;
	    if (!isset($domain[$act["id_class"]]["content"]))
		$domain[$act["id_class"]]["content"] = [];
	    $domain[$act["id_class"]]["content"] = db_select_all("
		id as id_class_asset,
		{$Language}_name as asset_name,
                {$Language}_link as asset_link,
                {$Language}_content as asset_content,
                chapter
		FROM class_asset
		WHERE id_class = {$mod["id"]}
                ORDER BY chapter
		", "id_class_asset");
	}
    }
    return ($domain);
}
