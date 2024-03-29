<?php

function fetch_laboratory($id = -1, $by_name = false)
{
    global $Language;
    global $Configuration;

    if ($id == -1)
    {
	$out = [];
	foreach (db_select_all("id FROM laboratory WHERE deleted IS NULL") as $lab)
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
        id, id as id_laboratory, codename, {$Language}_name as name, {$Language}_description as description
        FROM laboratory
        WHERE id = $id AND deleted IS NULL
    ");
    $lab["user"] = db_select_all("
        user.id as id,
        user.codename as codename,
        user.nickname as nickname,
        user_laboratory.authority as authority
        FROM user_laboratory
        LEFT JOIN user ON user_laboratory.id_user = user.id
        WHERE id_laboratory = $id
    ", $by_name ? "codename" : "");
    foreach ($lab["user"] as &$usr)
    {
	$pic = $Configuration->UsersDir().$usr["codename"]."/avatar.png";
	if (file_exists($pic))
	    $usr["avatar"] = $pic;
	$pic = $Configuration->UsersDir().$usr["codename"]."/photo.png";
	if (file_exists($pic))
	    $usr["photo"] = $pic;
    }

    $lab["icon"] = $Configuration->GroupsDir($lab["codename"])."icon.png";
    if (!file_exists($lab["icon"]))
	$lab["icon"] = "res/no_avatar_lab.png";
    $lab["school"] = db_select_all("
	school.*, school.{$Language}_name as name
	FROM school
	LEFT JOIN school_laboratory
	ON school.id = school_laboratory.id_school
	WHERE school_laboratory.id_laboratory = $id
    ");
    return ($lab);
}

