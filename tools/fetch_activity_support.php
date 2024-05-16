<?php

function fetch_activity_support($id, $gather = false, $by_name = false, $activity = NULL) // id_activity
{
    global $Language;

    if ($activity == NULL)
    {
	if (($ret = resolve_codename("activity", $id, "codename", true))->is_error())
	    return ([]);
	$activity = $ret->value;
    }
    $id = $activity["id"];

    $new = [];
    if ($gather)
    {
	// Faux, faux car le dernier vrai va aller voir les templates.
	if ($activity["reference_activity"] != -1)
	    $new = array_merge($new, fetch_activity_support($activity["reference_activity"], false));
	if ($activity["parent_activity"] != -1)
	    $new = array_merge($new, fetch_activity_support($activity["parent_activity"], false));
	if ($activity["id_template"] != -1 && $activity["support_template"])
	    $new = array_merge($new, fetch_activity_support($activity["id_template"], true));
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

       support.id as id_support,
       support.{$Language}_name as support_name,
       support.codename as support_codename,

       support_asset.id as id_support_asset,
       support_asset.codename as support_asset_codename,
       support_asset.{$Language}_name as support_asset_name,

       support_category.id as id_support_category,
       support_category.codename as support_category_codename,
       support_category.{$Language}_name as support_category_name,

       subactivity.id as id_activity,
       subactivity.codename as activity_codename,
       subactivity.parent_activity as activity_parent,
       subactivity.{$Language}_name as activity_name

       FROM activity_support
       LEFT JOIN support_asset
         ON activity_support.id_support_asset = support_asset.id
       LEFT JOIN support_category
         ON activity_support.id_support_category = support_category.id
       LEFT JOIN support
         ON activity_support.id_support = support.id
       -- LEFT JOIN activity
       --   ON activity_support.id_activity = activity.id
       LEFT JOIN activity as subactivity
         ON activity_support.id_subactivity = subactivity.id

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
	    $s["type"] = 0;
	    $s["prefix"] = "#";
	    $s["codename"] = $s["prefix"].($name = $a["support_asset_codename"]);
	    $s["position"] = "ClassMenu";
	    $s["id_support_asset"] = $a["id_support_asset"];
	    $s["name"] = $a["support_asset_name"];
	}
	else if ($a["id_support"] !== NULL)
	{
	    $s["type"] = 1;
	    $s["prefix"] = "";
	    $s["codename"] = $s["prefix"].($name = $a["support_codename"]);
	    $s["position"] = "ClassMenu";
	    $s["id_support"] = $a["id_support"];
	    $s["name"] = $a["support_name"];
	}
	else if ($a["id_support_category"] !== NULL)
	{
	    $s["type"] = 2;
	    $s["prefix"] = "@";
	    $s["codename"] = $s["prefix"].($name = $a["support_category_codename"]);
	    $s["position"] = "ClassMenu";
	    $s["id_support_category"] = $a["id_support_category"];
	    $s["name"] = $a["support_category_name"];

	}
	else if ($a["id_activity"] !== NULL)
	{
	    if ($a["activity_parent"] != -1 && $a["activity_parent"] != NULL)
	    {
		$s["type"] = 3; // Activité
		$s["position"] = "ActivityMenu";
	    }
	    else
	    {
		$s["type"] = 4; // Matière
		$s["position"] = "ModulesMenu";
	    }
	    $s["prefix"] = "$";
	    $s["codename"] = $s["prefix"].($name = $a["activity_codename"]);
	    $s["id_activity"] = $a["id_activity"];
	    $s["name"] = $a["activity_name"];
	}
	else
	    continue ;

	if ($by_name)
	    $new[$name] = $s;
	else
	    $new[] = $s;
    }
    return ($new);
}

