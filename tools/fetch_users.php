<?php

function fetch_users($attr = [], $id = -1)
{
    global $Database;

    if ($attr == [])
	$attr = [
	    "id", "codename", "nickname", "first_name", "family_name",
	    "authority", "registration_date", "cycle", "school", "user", "deleted"
	];
    if (!array_search("codename", $attr))
	$attr[] = "codename";
    if (!array_search("id", $attr))
	$attr[] = "id";

    if ($id != -1)
    {
	if (($id = resolve_codename("user", $id))->is_error())
	    return ([]);
	$id = $id->value;
	if (!is_array($id))
	    $id = [$id];
    }

    $select = " WHERE 1";
    $select .= is_admin() ? "" : " AND authority != ".BANISHED." ";
    $select .= $id == -1  ? "" : " AND id IN (".implode(", ", $id).") ";

    $forge = unroll($attr, SELECT, ["cycle", "school", "user"]);
    $students = db_select_all("
        $forge FROM user $select ORDER BY codename ASC", "codename"
    );
    if (($fnd = array_dsearch(["cycle", "school", "user"], $attr)) !== false)
    {
	foreach ($students as $i => $v)
	{
	    if (isset($fnd["cycle"]))
		$students[$i]["cycle"] = get_user_promotions($students[$i]);
	    if (isset($fnd["user"]))
		$students[$i]["user"] = get_user_children($students[$i]);
	    if (isset($fnd["school"]))
		$students[$i]["school"] = get_user_school($students[$i]);
	}
    }
    return ($students);
}

