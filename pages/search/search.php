<?php

function search($codename)
{
    global $Database;
    global $Language;
    global $Configuration;

    $category = [
	"user" => [
	    "fields" => "",
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
		if (isset($r["deleted"]) && $r["deleted"] != NULL)
		    continue ;
		if ($name == "user")
		{
		    $r["icon"] = $Configuration->UsersDir($r["codename"])."/photo.png";
		    if (!file_exists($r["icon"]))
			$r["icon"] = "res/no_avatar.png";
		}
		$new = $r;
		$new["address"] = "index.php?p=".$fields["position"]."&amp;a=".$r["id"];
		$out[$name]["name"] = ucfirst($name);
		$out[$name]["result"][] = $new;
	    }
	}
    }
    return ($out);
}

