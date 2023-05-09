<?php

function DisplayDoc($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1)
    {
	$ret = hand_request([
	    "command" => "getdoc",
	    "codename" => $id
	]);
	if ($ret == NULL || !isset($ret["content"]))
	    not_found();
	return (new ValueResponse(["content" => $ret["content"]]));
    }
    ob_start();
    require ("./pages/doc/docs.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddDoc($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1 || !isset($data["file"]))
	bad_request();
    foreach ($data["file"] as $file)
    {
	if (substr($file["name"], -4) != ".dab")
	    bad_request();
    }
    foreach ($data["file"] as $file)
    {
	$field = "configuration";
	$file["name"] = substr($file["name"], 0, -4);
	$ret = hand_request([
	    "command" => "installdoc",
	    "codename" => basename($file["name"]),
	    $field => $file["content"],
	]);
	if (!isset($ret["result"]) || $ret["result"] != "ok")
	    return (new ErrorResponse(isset($ret["message"]) ? $ret["message"] : "InternalError"));
    }
    $ret = DisplayDoc(-1, [], "GET", $output, $module);
    $ret->value["msg"] = "Added";
    return ($ret);
}

function DeleteDoc($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    $ret = hand_request([
	"command" => "deletedoc",
	"codename" => $id
    ]);
    if (!isset($ret["result"]) || $ret["result"] != "ok")
	return (new ErrorResponse(@$ret["message"]));
    $ret = DisplayDoc(-1, [], "GET", $output, $module);
    $ret->value["msg"] = "Deleted";
    return ($ret);
}

$Tab = [
    "GET" => [
	"" => [
	    "is_teacher",
	    "DisplayDoc",
	]
    ],
    "POST" => [
	"" => [
	    "is_teacher",
	    "AddDoc",
	]
    ],
    "DELETE" => [
	"" => [
	    "is_teacher",
	    "DeleteDoc",
	]
    ],
];
