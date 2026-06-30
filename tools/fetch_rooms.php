<?php

function fetch_room_current_exam($id_room)
{
    global $Language;

    $id_room = (int)$id_room;
    $exam = db_select_one("
        activity.id,
        activity.codename,
        COALESCE(NULLIF(activity.".$Language."_name, ''), activity.codename) as name,
        session.begin_date,
        session.end_date
        FROM session_room
        LEFT JOIN session ON session.id = session_room.id_session
        LEFT JOIN activity ON activity.id = session.id_activity
        WHERE session_room.id_room = $id_room
          AND session.deleted IS NULL
          AND activity.deleted IS NULL
          AND activity.type >= 5
          AND activity.type <= 9
          AND session.begin_date <= NOW()
          AND session.end_date >= NOW()
        ORDER BY session.begin_date ASC
    ");
    return ($exam ? $exam : NULL);
}

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

    $lab["exam"] = fetch_room_current_exam($lab["id"]);
    $lab["is_exam"] = $lab["exam"] !== NULL;

    $lab["desk"] = db_select_all("
        *
        FROM room_desk
        WHERE room_desk.id_room = {$lab["id"]}
	", $by_name ? "codename" : "");
    $lab["occupied"] = 0;
    $lab["computer_available"] = 0;
    foreach ($lab["desk"] as &$d)
    {
	$desk_is_alive = !($d["last_update"] == NULL || now() - date_to_timestamp($d["last_update"]) > 60 * 5);
	if (!$desk_is_alive)
	    $d["status"] = 3; // Unavailable
	$select_system_codename = in_array("codename", db_select_rows("room_desk_user"))
	    ? "room_desk_user.codename"
	    : "NULL";
	$d["users"] = db_select_all("
	  room_desk_user.id_user as id,
	  COALESCE(user.codename, $select_system_codename) as codename,
	  $select_system_codename as system_codename,
	  room_desk_user.distant,
	  room_desk_user.locked,
	  room_desk_user.last_update
	  FROM room_desk_user
          LEFT JOIN user ON room_desk_user.id_user = user.id
          WHERE room_desk_user.id_room_desk = {$d["id"]}
	  AND TIMESTAMPDIFF(SECOND, room_desk_user.last_update, NOW()) < 60 * 5
	  ");
	$d["physical_user_count"] = 0;
	$d["ssh_user_count"] = 0;
	$d["locked_user_count"] = 0;
	$d["is_exam"] = $lab["is_exam"];
	foreach ($d["users"] as $usr)
	{
	    if ($usr["distant"])
	    {
		$d["ssh_user_count"] += 1;
		continue ;
	    }
	    $d["physical_user_count"] += 1;
	    if ($usr["locked"])
		$d["locked_user_count"] += 1;
	}
	if ($desk_is_alive)
	{
	    if ($d["locked_user_count"] > 0)
		$d["status"] = 2;
	    else if ($d["physical_user_count"] > 0)
		$d["status"] = 1;
	    else if ($d["ssh_user_count"] > 0)
		$d["status"] = 4;
	    else
		$d["status"] = 0;
	}
	if ($d["physical_user_count"] > 0)
	    $lab["occupied"] += 1;
	if ($desk_is_alive && $d["physical_user_count"] == 0)
	    $lab["computer_available"] += 1;
    }
    $lab["computer_capacity"] = count($lab["desk"]);
    return ($lab);
}

