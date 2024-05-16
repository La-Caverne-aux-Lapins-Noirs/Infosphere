<?php

function fetch_school($id = -1)
{
    global $Language;
    global $Configuration;

    if ($id !== -1 && $id != "")
    {
	if (($id = resolve_codenamef("school", $id))->is_error())
	    return ($id);
	$id = $id->value;
	$id = " AND id = $id ";
    }
    else
	$id = "";
    $out = db_select_all("
       *, {$Language}_name as name
       FROM school
       WHERE deleted IS NULL $id
       ORDER BY codename
    ");
    foreach ($out as &$v)
    {
	$v["icon"] = $Configuration->SchoolsDir($v["codename"]);
	$v["cycle"] = db_select_all("
           cycle.id as id, cycle.id as id_cycle,
           cycle.codename, cycle.{$Language}_name as name
           FROM school_cycle LEFT JOIN cycle ON cycle.id = school_cycle.id_cycle
           WHERE id_school = ".$v["id"]."
           AND first_day > '".db_form_date(now() - 60 * 60 * 24 * 7 * 16)."'
           AND done IS NULL
	");
	$v["user"] = db_select_all("
           user.id as id, user.id as id_user, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = 0
	   ");
	$v["director"] = db_select_all("
           user.id as id, user.id as id_director, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = 1
	   ");
	if ($id != "")
	    break ;
    }
    return ($id == "" ? $out : $out[0]);
}

