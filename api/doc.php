<?php

function GenerateDoc($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    $files = [];
    unset($data["action"]);
    if (isset($data["fields"]))
    {
	if (!is_array($data["fields"]))
	    $data["fields"] = explode(" ", $data["fields"]);
	$data["fields"] = array_filter($data["fields"], "strlen");
	foreach ($data["fields"] as &$df)
	{
	    if (!preg_match('/^[a-zA-Z_0-9\.]=.*/', $df))
		bad_request();
	    $df = escapeshellarg($df);
	}
	$cmds = implode(" " , $data["fields"]);
	unset($data["fields"]);
    }
    foreach ($data as $file => $val)
    {
	if ($val == 0)
	    continue ;
	$file = str_replace("_dab", ".dab", $file);
	$file = $Configuration->DocDir().$file;
	$file = escapeshellarg($file);
	if (!file_exists($file) && 0)
	    bad_request();
	if ($val == 1)
	    array_unshift($files, $file);
	else
	    $files[] = $file;
    }
    $files = implode(" ", $files);

    $cmd = "docbuilder -i $files -m $cmds -o /std/stdout";
    $content = shell_exec($cmd);
    
    return (new ValueResponse([
	"filename" => "generated.pdf",
	"content" => $content
    ]));
}

function DisplayDoc($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    if (!isset($data["path"]))
	$data["path"] = "";

    $page = $module;
    $root = $Configuration->DocDir();
    $html = get_dir($root, $data["path"], "doc", 0, "file", "file_browser", is_teacher(), "");

    return (new ValueResponse([
	"content" => $html
    ]));
}

function AddDoc($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    if (!isset($data["file"]) || !is_array($data["file"]))
	bad_request();
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	$nam = pathinfo($files["name"], PATHINFO_FILENAME);
	$target = $Configuration->DocDir();
	$ext = pathinfo($files["name"], PATHINFO_EXTENSION);
	$content = base64_decode($files["content"]);
	if ($ext == "html")
	    $ext = "htm";
	if ($ext == "jpeg")
	    $ext = "jpg";
	if (!in_array($ext, $types = ["pdf", "htm", "dab", "json", "ini", "xml", "txt", "bin", "dat", "png", "jpg"]))
	    return (new ErrorResponse("InvalidFile", $ext, $Dictionnary["SupportedFormats"].": ".implode(", ", $types)));
	$ret = hand_request([
	    "command" => "installdoc",
	    "codename" => $files["name"],
	    "doc" => $files["content"],
	]);
	if (!isset($ret["result"]) || $ret["result"] != "ok")
	    return (new ErrorResponse(isset($ret["message"]) ? $ret["message"] : "InternalError"));
	new_directory($target);
	file_put_contents("$target$nam.$ext", $content);
    }
    $ret = DisplayDoc(0, $data, "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Added"];
    return ($ret);
}

function DeleteDoc($id, $data, $method, $output, $module)
{
    global $Configuration;

    if (!isset($data["file"]))
	bad_request();
    if (substr($data["file"], 0, 1) == "-")
	$data["file"] = substr($data["file"], 1);
    $normal_dir = $Configuration->DocDir();
    $file = $data["file"];
    $file = str_replace("@", "/", $file);
    if (strncmp($normal_dir, $file, strlen($normal_dir)) != 0)
	bad_request();

    /* Actuellement non implémenté
    $ret = hand_request([
	"command" => "deletedoc",
	"codename" => $file
    ]);
    if (!isset($ret["result"]) || $ret["result"] != "ok")
	return (new ErrorResponse(@$ret["message"]));
    */

    // Tout est bon, on envoi à la poubelle
    if (remove_ressource_file("doc", "", $file) == false)
	bad_request();

    $ret = DisplayDoc(-1, [], "GET", $output, $module);
    $ret->value["msg"] = "Deleted";
    return ($ret);
}

$Tab = [
    "PUT" => [
	"file" => [
	    "is_teacher",
	    "DisplayDoc",
	]
    ],
    "POST" => [
	"" => [
	    "is_teacher",
	    "AddDoc",
	],
	"generate" => [
	    "is_teacher",
	    "GenerateDoc",
	]
    ],
    "DELETE" => [
	"file" => [
	    "is_teacher",
	    "DeleteDoc",
	]
    ],
];
