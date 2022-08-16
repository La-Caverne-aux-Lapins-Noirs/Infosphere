<?php

function fetch_session($instance)
{
    global $Language;

    $v = db_select_all("
       room.{$Language}_name as room_name,
       room.id as id_room,
       session.*
       FROM session
       LEFT JOIN room ON session.id_room = room.id
       WHERE id_instance = $instance
       ORDER BY session.begin_date
    ");
    return (new ValueResponse($v));
}
