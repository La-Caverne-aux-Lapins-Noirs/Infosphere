<?php

function fetch_teacher($id, $by_name = false, $table = "activity") // id_{$table}
{
    if (($ret = resolve_codename($table, $id))->is_error())
	return ([]);
    $id = $ret->value;

    $dat = db_select_all("
       user.id as id_user,
       user.codename as codename_user,
       laboratory.id as id_laboratory,
       laboratory.codename as codename_laboratory
       FROM {$table}_teacher
       LEFT JOIN user ON user.id = {$table}_teacher.id_user
       LEFT JOIN laboratory ON laboratory.id = {$table}_teacher.id_laboratory
       WHERE {$table}_teacher.id_{$table} = $id
    ");
    $new = [];
    foreach ($dat as &$d)
    {
	$n = [];
	$name = "";
	if ($d["codename_user"] != "")
	{
	    $n["id"] = $d["id_user"];
	    $n["authority"] = TEACHER;
	    $name = $n["codename"] = $d["codename_user"];
	}

	if ($d["codename_laboratory"] != "")
	{
	    $n = fetch_laboratory($d["id_laboratory"]);
	    $n["codename"] = "#".($name = $n["codename"]);
	}

	if ($by_name)
	    $new[$name] = $n;
	else
	    $new[] = $n;
    }
    return ($new);
}

