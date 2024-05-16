<?php

function fetch_teacher($id, $by_name = false, $table = "activity", $gather = false, $activity = NULL) // id_{$table}
{
    if ($activity == NULL)
    {
	if (($ret = resolve_codename($table, $id, "codename", true))->is_error())
	    return ([]);
	$activity = $ret->value;
    }
    $id = $activity["id"];
    $new = [];

    if ($gather)
    {
	// Faux, faux car le dernier vrai va aller voir les templates.
	if ($activity["reference_activity"] != -1)
	    $new = array_merge(
		$new, fetch_teacher($activity["reference_activity"], $by_name, $table, false)
	    );
	if ($activity["parent_activity"] != -1)
	    $new = array_merge(
		$new, fetch_teacher($activity["parent_activity"], $by_name, $table, false)
	    );
	if ($activity["id_template"] != -1)
	    $new = array_merge(
		$new, fetch_teacher($activity["id_template"], $by_name, $table, true)
	    );
	foreach ($new as &$n)
	    $n["ref"] = true;
	// On continue car il faut aussi regarder les profs pour l'activité demandée
    }

    $dat = db_select_all("
       user.id as id_user,
       user.codename as codename_user,
       laboratory.id as id_laboratory,
       laboratory.codename as codename_laboratory
       FROM {$table}_teacher
       LEFT JOIN user ON user.id = {$table}_teacher.id_user AND user.authority >= 0
       LEFT JOIN laboratory ON laboratory.id = {$table}_teacher.id_laboratory AND laboratory.deleted IS NULL
       WHERE {$table}_teacher.id_{$table} = $id
    ");
    foreach ($dat as &$d)
    {
	$n = [];
	$name = "";
	$n["ref"] = false;
	if ($d["codename_user"] != "")
	{
	    $n["id"] = $d["id_user"];
	    $n["id_user"] = $d["id_user"];
	    $n["id_teacher"] = $d["id_user"];
	    $n["authority"] = TEACHER;
	    $name = $n["codename"] = $d["codename_user"];
	    $n["prefix"] = "";
	}

	if ($d["codename_laboratory"] != "")
	{
	    $n = fetch_laboratory($d["id_laboratory"]);
	    $n["codename"] = "#".($name = $n["codename"]);
	    $n["prefix"] = "#";
	}

	if ($by_name)
	    $new[$name] = $n;
	else
	    $new[] = $n;
    }
    return ($new);
}

