<?php

require_once ("debug.php");
require_once ("error.php");

chdir(__DIR__."/../");
if (isset($_POST["language_select"]))
    $Language = $_POST["language_select"];
require_once ("language.php");
require_once ("tools/index.php");
load_constants();

$DATA = [];

// Récupération de la méthode
$METHOD = $_SERVER["REQUEST_METHOD"];
// Utile au debug, principalement
if (isset($_GET["_method"]))
    $METHOD = strtoupper($_GET["_method"]);

// Récupération du module demandé
if (!isset($_GET["_mod"]))
    bad_request();
$url = substr($_GET["_mod"], 1);
unset($_GET["_mod"]);
$url = explode("/", $url);
$MODULE = array_shift($url);
$ID = $SUBID = -1;
if (count($url) >= 1)
    $ID = $url[0];
if (count($url) >= 2)
    $_GET["action"] = $DATA["action"] = $url[1];
if (count($url) >= 3)
{
    if ($METHOD == "DELETE")
	$url[2] = "-".$url[2];
    $SUBID = $DATA[$DATA["action"]] = $url[2];
}

if (!isset($_GET["_output"]))
    $OUTPUT = "web";
else if (!in_array($_GET["_output"], ["web", "fweb", "json"], true))
    bad_request();
else
{
    $OUTPUT = $_GET["_output"];
    unset($_GET["_output"]);
}

// Récupération du corps
$input = [];
$stdin = fopen("php://input", "r");
while (($data = fread($stdin, 4096)))
    $input []= $data;
$input = implode($input);
fclose($stdin);
unset($stdin);
// Si il y a de la donnée
if (@strlen($input))
{
    $input = json_decode($input, true);
    $DATA = array_merge($DATA, $input);
}
// Si il y en a pas et qu'on a précisé une méthode (donc... pas GET, a priori)
else if (isset($_GET["_method"]) && strtoupper($_GET["_method"]) != "GET")
{
    // C'est qu'on veut debugger. On passe les paramètres POST/PUT/etc. par l'URL
    foreach ($_GET as $k => $v)
    {
	if (substr($k, 0, 1) != "_") // On ne transfère pas les meta paramètres
	    $DATA[$k] = $v;
    }
}
if (!isset($DATA["action"]))
{
    if (is_array($DATA))
	$DATA["action"] = "";
    else
	$DATA = ["action" => ""];
}

// On s'authentifie
require_once ("login/index.php");

// On charge le fichier adapté
if (!file_exists("./api/$MODULE.php"))
    not_found();
$request = NULL;
require_once ("./api/$MODULE.php");
try
{
    require_once ("calltab.php");
}
catch (Exception $e)
{
    debug_response($e->getMessage());
}

debug_packet();

if ($request == NULL)
    not_allowed();

http_response_code(200);
if ($request->is_error())
{
    // Si une erreur s'est produite, il n'y a pas eu de sortie en théorie, donc il faut en produire une.
    if ($OUTPUT == "fweb") // Principalement pour du debug
	echo strval($request);
    else
    {
	header("Content-Type: application/json");
	echo json_encode([
	    "result" => "ko",
	    "msg" => strval($request),
	    "content" => ""
	], JSON_UNESCAPED_SLASHES);
    }
}
else if ($request instanceof ValueResponse)
{
    if (isset($request->value["filename"]))
    {
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=".
	       $request->value["filename"]
	);
	echo $request->value["content"];
	return ;
    }
 
    // On complète le paquet
    if (!isset($request->value["content"]))
	$request->value["content"] = "";
    if (!isset($request->value["msg"]))
	$request->value["msg"] = "";
    $request->value["result"] = "ok";
    
    // On affiche directement les données qu'on souhaitait renvoyer
    // Principalement utile pour le debug
    if ($OUTPUT == "fweb")
    {
	if (isset($request->value["msg"]))
	    echo $request->value["msg"];
	echo "<br /><br />";
	if (isset($request->value["content"]))
	    echo $request->value["content"];
    }
    // On renvoi du JSON - soit de pures infos, soit du HTML packagé
    else
    {
	header("Content-Type: application/json");
	if (is_string($request->value["content"]))
	    $request->value["content"] = minihtml($request->value["content"]);
	echo json_encode($request->value, JSON_UNESCAPED_SLASHES);
    }
}

