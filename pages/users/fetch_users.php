<?php

function fetch_users($attr = [])
{
    global $Database;

    if ($attr == [])
	$attr = [
	    "id", "codename", "nickname", "first_name", "family_name", "authority", "avatar", "registration_date", "cycle", "photo"
	];
    if (!array_search("codename", $attr))
	$attr[] = "codename";
    if (!array_search("id", $attr))
	$attr[] = "id";

    $get_ban = is_admin() ? "" : " WHERE authority != ".BANISHED." ";

    $forge = unroll($attr, SELECT, ["cycle"]);
    $students = db_select_all("
        $forge FROM user $get_ban ORDER BY codename ASC", "codename"
    );
    if (array_search("cycle", $attr) !== false)
    {
	foreach ($students as $i => $v)
	{
	    $students[$i]["cycle"] = get_user_promotions($students[$i]);
	    $students[$i]["children"] = db_select_all("
               user.codename as codename
               FROM parent_child
               LEFT JOIN user ON parent_child.id_child = user.id
               WHERE parent_child.id_parent = ".$v["id"]."
	       ");
	}
    }
    return ($students);
}

