<?php

function fetch_activities($activity = -1, $parent = -1)
{
    global $Database;
    global $Language;
    global $Dictionnary;

    if ($activity != -1)
	$where = ["is_template" => 1];
    else
	$where = ["parent_activity" => $parent, "is_template" => 1];
    if (($root = fetch_data("activity", $activity, ["name", "description"], "codename", false, true, false, $where, ["codename ASC"]))->is_error())
	return ($root); // @codeCoverageIgnore
    $root = $root->value;
    foreach ($root as $i => $v)
    {
	if ($v["parent_activity"] != -1)
	    $root[$i]["parent_codename"] = db_select_one(
		"codename FROM activity WHERE id = ".$v["parent_activity"]
	    )["codename"];
	if ($v["reference_activity"] != -1 && $v["reference_activity"] != NULL)
	    $root[$i]["reference_codename"] = db_select_one(
		"codename FROM activity WHERE id = ".$v["reference_activity"]
	    )["codename"];

	$teach = db_select_all("
            user.codename as user,
            user.id as id_user,
            laboratory.{$Language}_name as laboratory,
            laboratory.id as id_laboratory
            FROM activity_teacher
            LEFT JOIN user ON activity_teacher.id_user = user.id
            LEFT JOIN laboratory ON activity_teacher.id_laboratory = laboratory.id
            WHERE id_activity = ".$v["id"]
	);
	$root[$i]["user"] = [];
	$root[$i]["laboratory"] = [];
	foreach ($teach as $x)
	{
	    if ($x["id_user"] != -1 && $x["id_user"] != NULL)
		$root[$i]["user"][] = ["name" => $x["user"], "id" => $x["id_user"]];
	    if ($x["id_laboratory"] != -1 && $x["id_laboratory"] != NULL)
		$root[$i]["laboratory"][] = ["name" => $x["laboratory"], "id" => $x["id_laboratory"]];
	}

	if (($root[$i]["activity"] = fetch_activities(-1, $v["id"]))->is_error())
	    return ($root[$i]["activity"]); // @codeCoverageIgnore

	$root[$i]["activity"] = $root[$i]["activity"]->value;
	$root[$i]["medal"] = db_select_all("
	    medal.*,
            medal.{$Language}_name as name,
	    medal.{$Language}_description as description,
            activity_medal.local as local,
            activity_medal.mandatory as mandatory
            FROM medal
            LEFT JOIN activity_medal ON activity_medal.id_medal = medal.id
            LEFT JOIN activity ON activity_medal.id_activity = activity.id
            WHERE activity_medal.id_activity = ".$v["id"]." AND medal.deleted = 0
	    ");
	$added =
	    ($v["allow_unregistration"] ?
	     "" /*$Dictionnary["UnregistrationAllowed"]*/ :
	     $Dictionnary["UnregistrationNotAllowed"]
	    );
	if (strlen($root[$i]["description"]) && strlen($added))
	    $root[$i]["description"] .= "<br /><br />";
	$root[$i]["description"] .= $added;
    }
    return (new ValueResponse($root));
}

