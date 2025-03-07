<?php
/*
** Fonctionnalitées actuelles:
** - Les élèves non auto déclarés sont marqué absents
** - Calcul des logs de connexions depuis les infos prélevées sur infosphere hand
**
** Fonctionnalités sabotées (peut-être defectueuses):
** - Inscription automatique aux activités marqués inscription auto
** - Inscription automatique aux matières marquées inscription auto
** - Lancement du ramassage automatique
**
** Fonctionnalités retirées (peut etre pour de mauvaises raisons):
** - Ajout de médailles en mode "non acquis" en fin d'activité
**
**
*/

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

add_log(TRACE, "Albedo starts.", 1, true);

// Ici, on est admin, si les paramètres étaient bons
// C'est ici que commence le travail d'Albedo.

// On check si les rooms n'ont pas changé depuis le dernier appel
if (check_room_status() === true)
    add_log(TRACE, "The rooms status has been edited", 1, true);

// On check l'état de la main
$out = hand_request(["command" => "ping", "content" => "b64:".base64_encode("ping")], true);
if ($out && $out["result"] == "ok" && $out["content"] == "ping")
    add_log(TRACE, "Infosphere hand runs.", 1, true);

// Chaque fichier albedo doit commencer par vérifier les credentials
$files = glob("*/albedo.php");
if (($key = array_search("api/albedo.php", $files)) !== false)
    unset($files[$key]);
$files = array_merge($files, glob("*/*/albedo.php"));

foreach ($files as $f)
{
    require ($f);
}
add_log(TRACE, "Albedo stops.", 1, true);
