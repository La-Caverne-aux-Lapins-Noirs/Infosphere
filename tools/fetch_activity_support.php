<?php

function fetch_activity_support($id, $gather = false, $by_name = false) // id_activity
{
    global $Language;

    if (($ret = resolve_codename("activity", $id, "codename", true))->is_error())
	return ([]);
    $id = $ret->value["id"];

    $new = [];

    if ($gather)
    {
	// Faux, faux car le dernier vrai va aller voir les templates.
	if ($ret->value["reference_activity"] != -1)
	    $new = array_merge($new, fetch_activity_class($ret->value["reference_activity"], false));
	if ($ret->value["parent_activity"] != -1)
	    $new = array_merge($new, fetch_activity_class($ret->value["parent_activity"], false));
	if ($ret->value["id_template"] != -1 && $ret->value["class_template"])
	    $new = array_merge($new, fetch_activity_class($ret->value["id_template"], true));
	foreach ($new as &$n)
	    $n["ref"] = true;
	// On continue car il faut aussi regarder les classes pour l'activité demandée
    }

    $all = db_select_all("
       activity_support.id as id,

       -- activity.id as id_activity,
       -- activity.codename as activity_codename,
       -- activity.is_template as is_template,
       -- activity.{$Language}_name as activity_name,

       clss.id as id_class,
       clss.{$Language}_name as class_name,
       clss.codename as class_codename,

       class_asset.id as id_class_asset,
       class_asset.codename as class_asset_codename,
       class_asset.{$Language}_name as class_asset_name,

       subactivity.id as id_activity,
       subactivity.codename as activity_codename,
       subactivity.{$Language}_name as activity_name

       FROM activity_support
       LEFT JOIN class_asset ON activity_support.id_class_asset = class_asset.id
       LEFT JOIN `class` as clss ON activity_support.id_class = clss.id
       LEFT JOIN activity ON activity_support.id_activity = activity.id
       LEFT JOIN activity as subactivity ON activity_support.id_subactivity = subactivity.id
       WHERE activity_support.id_activity = $id
       ORDER BY activity_support.chapter ASC
    ");

    foreach ($all as $a)
    {
	$s = [
	    "id" => $a["id"],
	    "ref" => false
	];
	if ($a["id_class_asset"] !== NULL)
	{
	    $name = $a["class_asset_codename"];
	    $s["position"] = "TopGalleryMenu";
	    $s["id_class_asset"] = $a["id_class_asset"];
	    $s["id_support"] = $a["id_class_asset"];
	    $s["name"] = $a["class_asset_name"];
	    $s["codename"] = $a["class_asset_codename"];
	    $s["type"] = 0;
	    $s["prefix"] = "";
	}
	else if ($a["id_class"] !== NULL)
	{
	    $name = $a["class_codename"];
	    $s["position"] = "TopGalleryMenu";
	    $s["id_class"] = $a["id_class"];

	    $s["name"] = $a["class_name"];
	    $s["codename"] = "#".$a["class_codename"];
	    $s["type"] = 1;
	    $s["prefix"] = "#";
	}
	else
	{
	    $name = $a["activity_codename"];
	    $s["position"] = "ActivityTemplatesMenu";
	    $s["id_activity"] = $a["id_activity"];

	    $s["name"] = $a["activity_name"];
	    $s["codename"] = "$".$a["activity_codename"];
	    $s["type"] = 2;
	    $s["prefix"] = "$";
	}

	if ($by_name)
	    $new[$name] = $s;
	else
	    $new[] = $s;
    }
    return ($new);
}

