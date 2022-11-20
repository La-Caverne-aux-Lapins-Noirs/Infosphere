<?php
// Appelé par une crontab.
if (!isset($argv[1]) || !isset($argv[2]))
    exit ;
$albedo = 1;
$_POST["silent"] = 1;

chdir(__DIR__);
if (isset($_POST["language_select"]))
    $Language = $_POST["language_select"];
require_once ("language.php");

require ("tools/index.php");
$msg = get_login_info($argv[1], $argv[2], true);
unset($argv);

if ($msg->is_error() || $msg->value["id"] != 1)
    exit ;
$OriginalUser = $User = $msg->value;

add_log(TRACE, "Albedo starts.", 1);

// Ici, on est admin, si les paramètres étaient bons
// C'est ici que commence le travail d'Albedo.

$out = hand_request(["command" => "ping", "content" => "b64:".base64_encode("ping")]);
if ($out["result"] == "ok" && $out["content"] == "ping")
    add_log(TRACE, "Infosphere hand runs.", 1);

// Chaque fichier albedo doit commencer par vérifier les credentials
$files = glob("*/albedo.php");
$files = array_merge($files, glob("*/*/albedo.php"));

foreach ($files as $f)
{
    require ($f);
}
add_log(TRACE, "Albedo stops.", 1);
