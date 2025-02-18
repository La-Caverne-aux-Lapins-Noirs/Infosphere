<?php

function check_room_status()
{
    // On récupère l'état actuelle des salles
    $room_status = [];
    $rooms_name = db_select_all("id, codename FROM room");
    foreach ($rooms_name as $room)
	$room_status[$room['codename']] = array_keys(db_select_all("codename FROM room_desk WHERE id_room = ".$room['id'], "codename"));
    // On hash le tout pour comparer facilement
    $room_status_key = hash("crc32", json_encode($room_status));
    
    // On récupère l'état de la base de donnée
    $db_room_status = db_select_one("value FROM cache WHERE codename = 'rooms'");

    if ($db_room_status !== null)
	$db_room_status = $db_room_status["value"];

    if ($room_status_key != $db_room_status)
    {
	$ret = hand_request([
	    "command" => "updaterooms",
	    "rooms" => $room_status
	]);
	if ($ret === null)
	    add_log(REPORT, "Total Failure when updating rooms to Infosphere Hand !", true);
	else
	{
	    // On met à jour la database
	    if ($ret["result"] == "ok")
	    {
		db_update_one("cache", "rooms", ["value" => $room_status_key]);
		return true;
	    }
	    add_log(REPORT, "Failed to update rooms with Infosphere Hand ! : ".$ret['message'], true);
	}
    }
    return false;
}
