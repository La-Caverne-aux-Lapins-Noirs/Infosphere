<?php

function DisplayRoom($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $rooms = fetch_rooms();
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($rooms, JSON_UNESCAPED_SLASHES)]));

    ob_start();
    if (count($rooms) == 0)
	echo $Dictionnary["NoRoom"];
    else
	foreach ($rooms as $room)
	    require ("./pages/room/display_room.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddRoom($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;
    
    if ($id != -1 || !isset($data["rooms"]))
	bad_request();
    get_user_school($User);
    foreach ($data["rooms"] as $room)
    {
	if (!isset($room["codename"]))
	    continue ;
	if (!isset($room["configuration"]["content"]))
	    $room["configuration"] = [];
	else
	{
	    if (($tmp = base64_decode($room["configuration"]["content"])) === false)
		bad_request();
	    if (($tmp = load_configuration($tmp, [], true))->is_error())
		return ($tmp);
	    $room["configuration"] = $tmp->value;
	}
	if (($ret = add_room($room["codename"], @$room["capacity"], @$room["map"], $room["configuration"], $room))->is_error())
	    return ($ret);
	// Si on est directeur, il faut ajouter la jonction avec les écoles
	foreach ($User["school"] as $school)
	    if (($ret = handle_links($school["id_school"], $room["codename"], "school", "room"))->is_error())
		return ($ret);
    }
    $ret = DisplayRoom($id, $data, "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Added"];
    return ($ret);
}

function DeleteRoom($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    if (($ret = mark_as_deleted("room", $id))->is_error())
	return ($ret);
    $ret = DisplayRoom($id, $data, "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Deleted"];
    return ($ret);
}

function SetRoom($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;
    global $Database;
    
    if ($id == -1)
	bad_request();
    $fields = [];
    if (isset($data["configuration"][0]["content"]))
    {
	$data["configuration"] = base64_decode($data["configuration"][0]["content"]);
	$data["configuration"] = load_configuration($data["configuration"], [], true);
	$data["configuration"] = $data["configuration"]->value;
	$Database->query("DELETE FROM room_desk WHERE id_room = $id");
	if (($ret = add_desk($data["configuration"], $id))->is_error())
	    return ($ret);
    }
    if (isset($data["capacity"]))
    {
	$fields["capacity"] = (int)$data["capacity"];
	if ($fields["capacity"] < -1)
	    bad_request();
    }
    if (count($fields) || isset($data["map"][0]["content"]))
    {
	$codename = db_select_one("codename FROM room WHERE id = $id")["codename"];
	if (($ret = try_update("room", $id, $fields, @$data["map"][0]["content"], $Configuration->RoomsDir($codename), ["name" => false], $data))->is_error())
	    return ($ret);
    }
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"]
    ]));
}

function SetSchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1 || !isset($data["school"]))
	bad_request();
    if (($ret = handle_links($data["school"], $id, "school", "room"))->is_error())
	return ($ret);
    $room = fetch_rooms($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "room",
	    "hook_id" => $id,
	    "linked_name" => "school",
	    "linked_elems" => $room["school"],
	    "admin_func" => "only_admin",
    ])]));
}

$Tab = [
    "GET" => [
	"" => [
	    "everybody",
	    "DisplayRoom",
	]
    ],
    "POST" => [
	"" => [
	    "is_director",
	    "AddRoom",
	]
    ],
    "PUT" => [
	"" => [
	    "is_director_for_room",
	    "SetRoom",
	],
	"school" => [
	    "only_admin",
	    "SetSchool",
	]
    ],
    "DELETE" => [
	"" => [
	    "only_admin",
	    "DeleteRoom",
	],
	"school" => [
	    "only_admin",
	    "SetSchool",
	],
    ],
];

