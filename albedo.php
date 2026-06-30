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
$HandOk = ping_hand();

// Chaque fichier albedo doit commencer par vérifier les credentials
$files = glob("*/albedo.php");
if (($key = array_search("api/albedo.php", $files)) !== false)
    unset($files[$key]);
$files = array_merge($files, glob("*/*/albedo.php"));

foreach ($files as $f)
{
    require ($f);
}

// Last Albedo task: process at most one queued support video encoding.
// Video jobs can be long-running; the worker has its own lock, so a later
// Albedo run will not start a second ffmpeg process for the same queue.
add_log(TRACE, "Albedo starts support video job processing.", 1, true);
$VideoJobs = support_video_process_jobs(1, false);
if ($VideoJobs["locked"])
    add_log(TRACE, "Support video job processing skipped: worker already running.", 1, true);
else
    add_log(TRACE, "Support video job processing done: ".
		   $VideoJobs["processed"]." processed, ".
		   $VideoJobs["errors"]." error(s).", 1, true);
add_log(TRACE, "Albedo stops.", 1, true);
