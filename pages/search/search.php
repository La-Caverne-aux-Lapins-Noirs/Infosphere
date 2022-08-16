<?php

function search($codename)
{
    global $Database;
    global $Language;

    $category = [
	"user" => [
	    "fields" => ", photo as icon",
	    "join" => "",
	    "position" => "ProfileMenu",
	    "where" => "AND authority != -1"
	],
	"activity" => [
	    "fields" => ", activity.{$Language}_name as name, activity.deleted as deleted",
	    "join" => "",
	    "position" => "ActivityMenu",
	    "where" => "OR LOWER(activity.{$Language}_name) LIKE LOWER ('%$codename%')"
	]
    ];
    $out = [];
    foreach ($category as $name => $fields)
    {
	$res = db_select_all("
            $name.id, $name.codename {$fields["fields"]}
            FROM $name {$fields["join"]}
            WHERE
            LOWER($name.codename) LIKE LOWER('%$codename%')
            {$fields["where"]}
	    ");
	if (@count($res))
	{
	    foreach ($res as $r)
	    {
		if (isset($r["deleted"]) && $r["deleted"] == 1)
		    continue ;
		$new = $r;
		$new["address"] = "index.php?p=".$fields["position"]."&amp;a=".$r["id"];
		$out[$name]["name"] = ucfirst($name);
		$out[$name]["result"][] = $new;
	    }
	}
    }
    return ($out);
}

