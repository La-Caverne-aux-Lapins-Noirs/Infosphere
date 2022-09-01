<?php

function DisplayMedals($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $medal = fetch_medal();
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($medal, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    require ("./pages/medal/list_medal.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddMedal($id, $data, $method, $output, $module)
{
    global $Configuration;
    
    if ($id != -1 || !is_symbol(@$data["codename"]))
	bad_request();
    $codename = $data["codename"];
    $shape = isset($data["shape"]) ? (int)@$data["shape"] : 1;
    $shape = ["pins", "band", "sband"][$shape];
    if (($tags = split_symbols($data["tags"], ",", false, false, ""))->is_error())
	$tags = "";
    else
	$tags = implode(",", $tags->value);
    if (($specificator = split_symbols($data["specificator"], ",", false, false, ""))->is_error())
	$specificator = "";
    else
	$specificator = implode(",", $specificator->value);
    $type = is_between($data["type"], 0, 2) ? $data["type"] : 0;
    $target = $Configuration->MedalsDir($codename)."/icon.png";
    new_directory($target);
    
    // Est ce qu'on prend directement l'image envoyé?
    if (isset($data["icon"][0]["content"]))
    {
	// On enregistre l'image
	if (pathinfo($data["icon"][0]["name"], PATHINFO_EXTENSION) != "png")
	    bad_request("InvalidFile");
	$content = $data["icon"][0]["content"];
	$content = base64_decode($content);
	$picture = $Configuration->MedalsDir("_ressources").".$codename.png";
	if (file_put_contents($picture, $content) === false)
	    return (new ErrorResponse("CannotWritePngFile"));

	// On génère une medaille invisible dont l'icone sera l'image envoyée.
	// On pourra ainsi toujours faire appel à des spécificateurs.
	$conf = $Configuration->MedalsDir("_ressources").".invisible_style.dab";

	$command = "genicon pins $codename -p $picture -c $conf";
    }
    // Est ce qu'on va générer l'icone?
    else
    {
	// Y a t il une configuration determinée?
	if (isset($data["configuration"]))
	    $conf = resolve_path($data["configuration"]);
	else
	    $conf = ".default_style.dab";	
	$conf = $Configuration->MedalsDir("_ressources").$conf;
	if (!file_exists($conf))
	    bad_request();
	
	$command = "genicon $shape $codename -c $conf";

	// Y a t il une icone issue de la réserve de ressources?
	if (isset($data["picture"]))
	{
	    $picture = $Configuration->MedalsDir("_ressources").resolve_path($data["picture"]);
	    if (!file_exists($picture))
		bad_request();
	    $command .= " -p $picture";
	}

	// Y a t il des spécificateurs envoyés?
	if ($specificator != "")
	    $command .= " -s $specificator";
    }

    if (($content = shell_exec($command." | base64 ")) === false)
	bad_request();
    debug_response($command);

    End:
    $ins = @try_insert("medal", $codename, [
	"tags" => $tags,
	"type" => $type,
	"command" => $command
    ], $content, $target, ["name" => false, "description" => false], $data);
    if ($ins->is_error())
	return ($ins);
    $ret = DisplayMedals($id, $data, $method, $output, $module);
    $ret->value["msg"] = "MedalAdded";
    return ($ret);
}

function GetRessourceDir($id, $data, $method, $output, $module, $msg = "")
{
    global $Configuration;
    global $Dictionnary;

    if ($id != -1)
	bad_request();
    if (!isset($data["path"]))
	$data["path"] = "";

    $root = $Configuration->MedalsDir("_ressources");
    $html = get_dir($root, $data["path"], "medal", -1, "ressource", "medalres_browser", is_teacher(), "");
    $msg = $msg ? ["msg" => $msg] : [];
    return (new ValueResponse(array_merge($msg, [
	"content" => $html
    ])));
}

function AddRessource($id, $data, $method, $output, $module)
{
    global $Configuration;
    global $Dictionnary;

    if ($id != -1 || !isset($data["file"]))
	bad_request();
    if (!isset($data["path"]))
	$data["path"] = "";
    $path = resolve_path($data["path"]);
    $root = $Configuration->MedalsDir("_ressources");
    $target = resolve_path($root.$path."/");
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	if (in_array(pathinfo($files["name"], PATHINFO_EXTENSION), [
	    "dab", "ini", "json", "png", "jpg", "ttf", "woff2"
	]) == false)
	    bad_request();
	$content = base64_decode($files["content"]);
	new_directory($target);
	$files["name"] = str_replace(" ", "_", $files["name"]);
	if ($files["name"][0] == ".")
	    $files["name"] = substr($files["name"], 1);
	file_put_contents($target.$files["name"], $content);
	system("chmod 640 ".$target.$files["name"]);
    }
    return (GetRessourceDir($id, $data, "GET", $output, $module, "RessourceAdded"));
}

function DeleteMedal($id, $data, $method, $output, $module)
{
    
}

function RemoveRessource($id, $data, $method, $output, $module)
{
    global $Configuration;
    
    if ($id != -1 || !isset($data["ressource"]))
	bad_request();
    // On vérifie que le dossier est bien celui de l'activité demandé...
    $normal_dir = $Configuration->MedalsDir("_ressources");
    $file = $data["ressource"];
    if ($file[0] == "-")
	$file = substr($file, 1);
    $file = str_replace("@", "/", $file);
    if (strncmp($normal_dir, $file, strlen($normal_dir)) != 0)
	bad_request();

    // Tout est bon, on envoi à la poubelle
    if (remove_ressource_file("medal", $id, $file) == false)
	bad_request();
    return (GetRessourceDir($id, $data, "GET", $output, $module, "Deleted"));
}

