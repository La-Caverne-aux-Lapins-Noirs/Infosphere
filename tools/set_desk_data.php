<?php

function set_desk_data($ddata)
{
    global $Database;

    $name = $ddata["name"];
    $mac = $ddata["mac"];
    $ip = $ddata["ip"];
    $user = $ddata["user"];
    $lock = $ddata["lock"];
    $local_user = isset($ddata["local"]) ? $ddata["local"] : "";
    $type = $ddata["type"];

    $name = explode(".", $name)[0];
    if (($desk = resolve_codename("room_desk", $name))->is_error())
    {
	if ($desk->label != "BadCodeName")
	    return ($desk);
	// resolve_codename en renvoyant "nom de code inconnu" certifie
	// que la forme de name est correcte
	$Database->query("
	    INSERT INTO room_desk (codename) VALUES ('$name')
	");
	$desk = $Database->insert_id;
    }
    else
	$desk = $desk->value;

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
	return (new ErrorResponse("InvalidParameter", "mac $mac"));
    if (filter_var($mac, FILTER_VALIDATE_MAC) === false)
	return (new ErrorResponse("InvalidParameter", "ip $ip"));

    // Status: 0: libre, 1: utilisé, 2: verrouillé, 3: en panne
    if ($lock)
	$status = 2;
    else if ($local_user != "")
	$status = 1;
    else
	$status = 0;
    
    // Type: 0 linux, 1 windows 2 mac 3 rpi
    $type = (int)$type;
    if ($type < 0 || $type > 3)
	return (new ErrorResponse("InvalidParameter", "type $type", "[0-3]"));

    $Database->query("
	UPDATE room_desk
	SET ip = '$ip', mac = '$mac', status = $status, type = $type, last_update = NOW()
	WHERE id = $desk
    ");

    // Suppression des utilisateurs indiqués
    // Puis remise des utilisateurs indiqués comme connecté
    // En une glorieuse requete

    $sql = "DELETE FROM room_desk_user WHERE id_room_desk = $desk;";
    if (count($user))
    {
	$sql .= "INSERT INTO room_desk_user (id_room_desk, id_user, distant, locked) VALUES ";
	$ins = [];
	foreach ($user as $usr)
	{
	    if (($id_user = resolve_codename("user", $usr))->is_error())
		continue ;
	    $id_user = $id_user->value;
	    if (($distant = ($usr != $local_user) ? "1" : "0") == "0")
		$lock = $lock ? "1" : "0";
	    else
		$lock = "0";
	    $ins[] = "($desk, $id_user, $distant, $lock)";
	}
	$sql .= implode(", ", $ins);
    }
    foreach (explode(";", $sql) as $s)
	$Database->query($s);
}

