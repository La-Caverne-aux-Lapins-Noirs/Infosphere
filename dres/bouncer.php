<?php
// Bouncer
// Le videur de l'Infosphère
// Il régule l'accès aux fichiers

require_once (__DIR__."/../api/debug.php");
require_once (__DIR__."/../api/error.php");

function render_file()
{
    http_response_code(200);
    // Limitons les risques
    if (in_array(pathinfo($_GET["target"], PATHINFO_EXTENSION), [
	"php", "sh", "pl"
    ]) !== false)
	not_found();
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

$target = resolve_path($_GET["target"]);
$target = explode("/", $target);
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

if (is_admin())
    render_file();

if ($type == "activity")
{
    if (count($target) < 3)
	bad_request();

    // Au cas ou l'activité soit basé sur un template
    $codename = $target[1];
    $codename = $Database->real_escape_string($codename);
    $instances = db_select_one("
	activity.id as id FROM activity as template
	LEFT JOIN activity
	ON activity.id_template = template.id AND activity.template_link = 1
        LEFT JOIN team
        ON team.id_activity = activity.id
        LEFT JOIN user_team
        ON user_team.id_team = team.id
	WHERE template.codename = '$codename'
        AND user_team.id_user = {$User["id"]}
	");
    if ($instances != NULL)
	$id = $instances["id"];
    else
	$id = $codename;
    
    if (($activity = new FullActivity)->build($id) == false)
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

if (($type == "user" || $type == "users"))
{
    // Il faut empecher l'accès aux données personnelles.
    if (count($target) < 2)
	bad_request();
    $user = $target[1];
    if (($user = resolve_codename("user", $user))->is_error())
	not_found();
    $user = $user->value;
    if (count($target) >= 3)
    {
	if ($target[2] == "public")
	    render_file();
	if ($target[2] == "admin")
	{
	    if (count($target) == 4 && $target[3] == "photo.png")
		render_file();
	    get_user_school($User, true);
	    if (!is_director_for_student($user))
		forbidden();
	    render_file();
	}
    }
    if ($user != $User["id"])
	forbidden();
    render_file();
}

if ($type == "groups")
{
    // Il faut empecher l'accès aux fichiers de groupes aux non membres...
}

if ($type == "support")
{
    // Il faut empecher l'accès aux non inscrits à une activité référencant le support
}

// Si il n'y a pas de restrictions particulières, on peut rendre le fichier
render_file();
