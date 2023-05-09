<?php

function DisplayRobot($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1)
    {
	$ret = hand_request([
	    "command" => "getrobot",
	    "codename" => $id
	]);
	if ($ret == NULL || !isset($ret["content"]))
	    not_found();
	return (new ValueResponse(["content" => $ret["content"]]));
    }
    ob_start();
    require ("./pages/robot/robots.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddRobot($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1 || !isset($data["file"]))
	bad_request();
    foreach ($data["file"] as $file)
    {
	if (substr($file["name"], -3) != ".so" &&
	    substr($file["name"], -4) != ".dab")
	    bad_request();
    }
    foreach ($data["file"] as $file)
    {
	if (substr($file["name"], -3) == ".so")
	{
	    $field = "bin";
	    $file["name"] = substr($file["name"], 0, -3);
	}
	else
	{
	    $field = "configuration";
	    $file["name"] = substr($file["name"], 0, -4);
	}
	$ret = hand_request([
	    "command" => "installrobot",
	    "codename" => basename($file["name"]),
	    $field => $file["content"],
	]);
	if (!isset($ret["result"]) || $ret["result"] != "ok")
	    return (new ErrorResponse(isset($ret["message"]) ? $ret["message"] : "InternalError"));
    }
    $ret = DisplayRobot(-1, [], "GET", $output, $module);
    $ret->value["msg"] = "Added";
    return ($ret);
}

function DeleteRobot($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    $ret = hand_request([
	"command" => "deleterobot",
	"codename" => $id
    ]);
    if (!isset($ret["result"]) || $ret["result"] != "ok")
	return (new ErrorResponse(@$ret["message"]));
    $ret = DisplayRobot(-1, [], "GET", $output, $module);
    $ret->value["msg"] = "Deleted";
    return ($ret);
}

$Tab = [
    "GET" => [
	"" => [
	    "is_teacher",
	    "DisplayRobot",
	]
    ],
    "POST" => [
	"" => [
	    "is_teacher",
	    "AddRobot",
	]
    ],
    "DELETE" => [
	"" => [
	    "is_teacher",
	    "DeleteRobot",
	]
    ],
];
