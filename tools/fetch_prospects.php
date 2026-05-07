<?php

function fetch_prospects($attr = [], $id = -1)
{
    global $Database;

    if ($attr == [])
	$attr = [
	    "id", "codename", "first_name", "family_name",
	    "registration_date", "phone", "mail", "target_entry", "target_class",
	    "current_class", "deleted"
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
	if ($id == [])
	    return ([]);
	if (!is_array($id))
	    $id = [$id];
    }

    $select = " WHERE password = '' ";
    $select .= is_admin() ? "" : " AND authority != ".BANISHED." ";
    $select .= $id == -1  ? "" : " AND id IN (".implode(", ", $id).") ";

    $forge = unroll($attr, SELECT, ["user"]);
    $students = db_select_all("
        $forge FROM user $select ORDER BY codename ASC", "codename"
    );
    return ($students);
}
