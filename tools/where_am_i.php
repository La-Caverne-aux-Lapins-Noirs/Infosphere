<?php

function where_am_i($user = NULL)
{
    global $User;

    if ($user == NULL)
	$user = $User;
    return (db_select_one("
	room.* FROM room_desk_user
	LEFT JOIN room_desk ON room_desk_user.id_room_desk = room_desk.id
	LEFT JOIN room ON room_desk.id_room = room.id
	WHERE id_user = {$user["id"]}
	AND room_desk_user.distant = 0
	AND room_desk_user.locked = 0
        AND TIMESTAMPDIFF(SECOND, room_desk_user.last_update, NOW()) < 60 * 5
	"));
}

