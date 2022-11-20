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
	    $new = array_merge($new, fetch_activity_support($ret->value["reference_activity"], false));
	if ($ret->value["parent_activity"] != -1)
	    $new = array_merge($new, fetch_activity_support($ret->value["parent_activity"], false));
	if ($ret->value["id_template"] != -1 && $ret->value["support_template"])
	    $new = array_merge($new, fetch_activity_support($ret->value["id_template"], true));
	foreach ($new as &$n)
	    $n["ref"] = true;
	// On continue car il faut aussi regarder les supportes pour l'activité demandée
    }

    $all = db_select_all("
       activity_support.id as id,

       -- activity.id as id_activity,
       -- activity.codename as activity_codename,
       -- activity.is_template as is_template,
       -- activity.{$Language}_name as activity_name,

       clss.id as id_support,
       clss.{$Language}_name as support_name,
       clss.codename as support_codename,

       support_asset.id as id_support_asset,
       support_asset.codename as support_asset_codename,
       support_asset.{$Language}_name as support_asset_name,

       subactivity.id as id_activity,
       subactivity.codename as activity_codename,
       subactivity.{$Language}_name as activity_name

       FROM activity_support
       LEFT JOIN support_asset ON activity_support.id_support_asset = support_asset.id
       LEFT JOIN `support` as clss ON activity_support.id_support = clss.id
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
	if ($a["id_support_asset"] !== NULL)
	{
	    $name = $a["support_asset_codename"];
	    $s["position"] = "TopGalleryMenu";
	    $s["id_support_asset"] = $a["id_support_asset"];
	    $s["id_support"] = $a["id_support_asset"];
	    $s["name"] = $a["support_asset_name"];
	    $s["codename"] = $a["support_asset_codename"];
	    $s["type"] = 0;
	    $s["prefix"] = "";
	}
	else if ($a["id_support"] !== NULL)
	{
	    $name = $a["support_codename"];
	    $s["position"] = "TopGalleryMenu";
	    $s["id_support"] = $a["id_support"];

	    $s["name"] = $a["support_name"];
	    $s["codename"] = "#".$a["support_codename"];
	    $s["type"] = 1;
	    $s["prefix"] = "#";
	}
	else
	{
	    $name = $a["activity_codename"];
	    $s["position"] = "ActivityMenu";
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

