<?php

function fetch_activity_class($id) // id_activity
{
    global $Language;

    $id = (int)$id;
    $all = db_select_all("
       activity_support.id as id,
       activity.id as activity_id,
       activity.codename as activity_codename,
       activity.is_template as is_template,
       activity.{$Language}_name as activity_name,
       clss.{$Language}_name as class_name,
       clss.codename as class_codename,
       clss.id as class_id,
       class_asset.id as asset_id,
       class_asset.{$Language}_name as asset_name,
       subactivity.id as subactivity_id,
       subactivity.codename as subactivity_codename,
       subactivity.{$Language}_name as subactivity_name
       FROM activity_support
       LEFT JOIN class_asset ON class_asset.id = activity_support.id_class_asset
       LEFT JOIN `class` as clss ON clss.id = class_asset.id_class
       LEFT JOIN activity ON activity_support.id_activity = activity.id
       LEFT JOIN activity as subactivity ON activity_support.id_subactivity = subactivity.id
       WHERE activity_support.id_activity = $id
       ORDER BY activity_support.number ASC
    ");
    $class = [];
    foreach ($all as $a)
    {
	$s = [];
	$name = "";
	$s["id"] = $a["id"];
	if ($a["asset_id"] != -1)
	{
	    $name = $a["class_codename"]."_".$a["id"];
	    $s["position"] = "TopGalleryMenu";
	    $s["ida"] = $a["class_id"];
	    $s["idb"] = $a["asset_id"];
	    $s["codename"] = $a["asset_name"];
	    $s["type"] = 1;
	}
	else if ($a["class_id"] != -1)
	{
	    $name = $a["class_codename"]."_".$a["id"];
	    $s["position"] = "TopGalleryMenu";
	    $s["ida"] = $a["class_id"];
	    $s["codename"] = $a["class_name"];
	    $s["type"] = 0;
	}
	else
	{
	    $name = $a["subactivity_codename"]."_".$a["id"];
	    if ($s["is_template"])
		$s["position"] = "ActivityMenu";
	    else
		$s["position"] = "InstancesMenu";
	    $s["id"] = $a["subactivity_id"];
	    $s["codename"] = $a["subactivity_name"];
	    $s["type"] = 2;
	}
	$class[$name] = $s;
    }
    if (($template = db_select_one("
      id_template, class_template FROM activity WHERE id = $id
    ")) != NULL)
    {
	if ($template["id_template"] != -1 && $template["class_template"])
	{
	    $all = array_merge($all, fetch_activity_class(
		$template["id_template"]
	    ));
	}
    }
    return ($class);
}

