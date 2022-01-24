<?php

function fetch_laboratory($id = -1, $by_name = false)
{
    global $Language;

    if ($id == -1)
    {
	$out = [];
	foreach (db_select_all("id FROM laboratory WHERE deleted = 0") as $lab)
	{
	    if ($by_name)
		$out[$lab["codename"]] = fetch_laboratory($lab["id"], $by_name);
	    else
		$out[] = fetch_laboratory($lab["id"], $by_name);
	}
	return ($out);
    }

    if (($err = resolve_codename("laboratory", $id))->is_error())
	return ([]);
    $id = $err->value;

    $lab = db_select_one("
        id, codename, icon, {$Language}_name as name, {$Language}_description as description
        FROM laboratory
        WHERE id = $id AND deleted = 0
    ");
    $lab["user"] = db_select_all("
        user.id as id,
        user.codename as codename,
        user.nickname as nickname,
        user.photo as photo,
        user.avatar as avatar,
        user_laboratory.authority as authority
        FROM user_laboratory
        LEFT JOIN user ON user_laboratory.id_user = user.id
        WHERE id_laboratory = $id
    ", $by_name ? "codename" : "");
    return ($lab);
}
