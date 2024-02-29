<?php

function fetch_rooms($id = -1, $by_name = false)
{
    global $Configuration;
    global $Language;

    if ($id == -1)
    {
	$out = [];
	foreach (db_select_all(
	    "id, codename FROM room WHERE deleted IS NULL ORDER BY {$Language}_name"
	) as $lab)
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
	id, codename, {$Language}_name as name, capacity
	FROM room
	WHERE id = $id AND deleted IS NULL
    ");

    $lab["school"] = db_select_all("
      school.*, school.id as id_school, school.{$Language}_name as name
      FROM school_room
      LEFT JOIN school
        ON school.id = school_room.id_school
      WHERE school_room.id_room = $id
    ");
    
    if (file_exists($tmp = $Configuration->RoomsDir($lab["codename"])."/icon.png"))
	$lab["map"] = $tmp;
    else
	$lab["map"] = NULL;

    $lab["desk"] = db_select_all("
        *
        FROM room_desk
        WHERE room_desk.id_room = {$lab["id"]}
	", $by_name ? "codename" : "");
    $lab["occupied"] = 0;
    foreach ($lab["desk"] as &$d)
    {
	if ($d["last_update"] == NULL || now() - date_to_timestamp($d["last_update"]) > 60 * 5)
	    $d["status"] = 3; // Unavailable
	$d["users"] = db_select_all("
	  * FROM room_desk_user
          LEFT JOIN user ON room_desk_user.id_user = user.id
          WHERE room_desk_user.id_room_desk = {$d["id"]}
	  AND TIMESTAMPDIFF(SECOND, last_update, NOW()) < 60 * 5
	  ");
	foreach ($d["users"] as $usr)
	{
	    if ($usr["distant"])
		continue ;
	    $lab["occupied"] += 1;
	}
    }
    $lab["computer_capacity"] = count($lab["desk"]);
    $lab["computer_available"] = $lab["computer_capacity"] - $lab["occupied"];
    return ($lab);
}

