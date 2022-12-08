<?php
// Bouncer
// Le videur de l'Infosphère
// Il régule l'accès aux fichiers

require_once (__DIR__."/../api/debug.php");
require_once (__DIR__."/../api/error.php");

function render_file()
{
    http_response_code(200);
    if (pathinfo($_GET["target"], PATHINFO_EXTENSION) == "php")
	not_found(); // J'ai hésité très peu de temps à permettre l'execution d'un script PHP... c'est trop risqué.
    header("Content-Type: ".mime_content_type($_GET["target"]));
    echo file_get_contents($_GET["target"]);
    die();
}

chdir(__DIR__."/../");
if (isset($_POST["language_select"]))
    $Language = $_POST["language_select"];
require_once ("language.php");
require_once ("tools/index.php");
load_constants();

if (!isset($_GET["target"]))
    bad_request();
$_GET["target"] = basename(__DIR__).$_GET["target"];
if (!file_exists($_GET["target"]))
    not_found();

$target = explode("/", $_GET["target"]);
array_shift($target);
if (count($target) < 1)
    bad_request();
$type = $target[0];

// On s'authentifie
require_once ("login/index.php");

if ($User == NULL)
{
    if (pathinfo($type, PATHINFO_EXTENSION) != "png" || count($target) != 1)
	authentication_required();
}

if ($type == "activity")
{
    if (count($target) < 3)
	bad_request();
    if (($activity = new FullActivity)->build($target[1]) == false)
	not_found(); // Le dossier peut exister mais l'activité peut avoir été supprimée
    // On est responsable?
    if ($activity->is_director || $activity->is_teacher || $activity->is_assistant)
	render_file();
    // C'est pas la configuration?
    if ($target[2] == "configuration.dab")
	not_found(); // Trop d'informations pour les étudiants...
    // On est inscrit? On est accepté?
    if ($activity->registered == false || $activity->leader == 0)
	forbidden();
    // Le sujet est dispo?
    if ($activity->subject_appeir_date == NULL || $activity->subject_appeir_date < now())
	if ($activity->subject_disappeir_date == NULL || $activity->subject_disappeir_date > now())
	    render_file();
    forbidden();
}

if ($type == "user")
{
    // Il faut empecher l'accès aux données personnelles.
}

if ($type == "groups")
{
    // Il faut empecher l'accès aux fichiers de groupes aux non membres...
}

if ($type == "elearning")
{
    // Il faut empecher l'accès aux non inscrits à une activité référencant le support
}

// Si il n'y a pas de restrictions particulières, on peut rendre le fichier
render_file();
