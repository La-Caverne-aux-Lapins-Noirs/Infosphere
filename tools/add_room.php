<?php

function add_desk($desks, $room)
{
    $nd = [];
    foreach ($desks as $codename => $desk)
    {
	if (!is_array($desk))
	    continue ;
	if (@$desk["Ip"])
	    if (filter_var($desk["Ip"], FILTER_VALIDATE_IP) == false)
		return (new ErrorResponse("InvalidIp", $desk["Ip"]));
	if (@$desk["Mac"])
	    if (filter_var($desk["Mac"], FILTER_VALIDATE_MAC) == false)
		return (new ErrorResponse("InvalidMac", $desk["Mac"]));
	if (($desk["Type"] = (int)@$desk["Type"]) < 0 || $desk["Type"] > 3)
	    return (new ErrorResponse("InvalidDeskType", $desk["Type"]));
	if (!isset($desk["X"]) || !isset($desk["Y"]) ||
	    $desk["X"] < 0 || $desk["X"] > 1250 ||
	    $desk["Y"] < 0 || $desk["Y"] > 500)
	    return (new ErrorResponse("InvalidDeskPosition"));
	
	$fields = [
	    "id_room" => $room,
	    "mac" => @$desk["Mac"],
	    "ip" => @$desk["Ip"],
	    "type" => $desk["Type"],
	    "x" => $desk["X"],
	    "y" => $desk["Y"],
	    "misc" => @$desk["Misc"]
	];
	if (($ins = try_insert("room_desk",$codename, $fields))->is_error())
	    return ($ins);
	$nd[] = $ins->value;
    }
    return (new ValueResponse($nd));
}

function add_room($codename, $capacity = NULL, $map = NULL, $configuration = [], $lng = [])
{
    global $Configuration;

    if ($capacity)
	if (($capacity = (int)$capacity) < -1)
	    return (new ErrorResponse("InvalidCapacity"));
    $fields = [
	"capacity" => $capacity,
    ];
    if (($ret = @try_insert("room", $codename, $fields, $map, $Configuration->RoomsDir(), ["name"], $lng))->is_error())
	return ($ret);
    $ret = $ret->value;
    if (($nd = add_desk($configuration, $ret["id"]))->is_error())
	return ($nd);
    $ret["desk"] = $nd->value;
    return (new ValueResponse($ret));
}

