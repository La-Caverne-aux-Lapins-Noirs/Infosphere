<?php

function fetch_rooms($id = -1, $by_name = false)
{
    global $Language;

    if ($id == -1)
    {
	$out = [];
	foreach (db_select_all("id FROM room WHERE deleted = 0") as $lab)
	{
	    if ($by_name)
		$out[$lab["codename"]] = fetch_rooms($lab["id"], $by_name);
	    else
		$out[] = fetch_rooms($lab["id"], $by_name);
	}
	return ($out);
    }

    if (($err = resolve_codename("room", $id))->is_error())
	return ([]);
    $id = $err->value;

    $lab = db_select_one("
	id, codename, {$Language}_name as name, capacity, map
	FROM room
	WHERE id = $id AND deleted = 0
    ");

    $lab["desk"] = db_select_all("
        room_desk.*,
        user.id as id_user,
        user.codename as codename_user
        FROM room_desk
        LEFT JOIN user ON room_desk.id_user = user.id
        WHERE room_desk.id_room = {$lab["id"]}
	", $by_name ? "codename" : "");
    $lab["occupied"] = 0;
    foreach ($lab["desk"] as $d)
    {
	if ($d["id_user"] != NULL)
	    $lab["occupied"] += 1;
    }
    return ($lab);
}

