<?php

function SetDesk($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id == -1)
	bad_request();
    if (!isset($data["id_room"]) || !isset($data["x"]) || !isset($data["y"]))
	bad_request();
    $id_room = (int)$data["id_room"];
    $x = (int)$data["x"];
    $y = (int)$data["y"];

    $Database->query("
	UPDATE room_desk
	SET id_room = $id_room, x = $x, y = $y
	WHERE id = $id
    ");
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
    ]));
}

$Tab = [
    "PUT" => [
	"" => [
	    "is_director",
	    "SetDesk"
	]
    ]
];

