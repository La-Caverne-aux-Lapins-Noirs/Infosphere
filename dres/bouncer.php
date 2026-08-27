<?php
// Bouncer
// Le videur de l'Infosphère
// Il régule l'accès aux fichiers

require_once (__DIR__."/../api/debug.php");
require_once (__DIR__."/../api/error.php");

function render_file()
{
    http_response_code(200);

    // Limitons les risques.
    $extension = strtolower(pathinfo($_GET["target"], PATHINFO_EXTENSION));
    if (in_array($extension, ["php", "pl"], true))
        not_found();

    $mime = mime_content_type($_GET["target"]);
    if ($mime === false)
        $mime = "application/octet-stream";
    header("Content-Type: ".$mime);

    if (($fd = fopen($_GET["target"], "rb")) === false)
        not_found();
    while (!feof($fd))
    {
        if (($data = fread($fd, 1024 * 1024)) === false)
            not_found();
        echo $data;
    }
    fclose($fd);
    die();
}

function bouncer_resolve_target($requested)
{
    $root = realpath(__DIR__);
    if ($root === false)
        not_found();

    if (!is_string($requested) && !is_numeric($requested))
        bad_request();
    $requested = str_replace("\\", "/", (string)$requested);
    if (strpos($requested, "\0") !== false)
        bad_request();

    $absolute = realpath($root."/".ltrim($requested, "/"));
    if ($absolute === false || !is_file($absolute))
        not_found();

    $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    if (strncmp($absolute, $prefix, strlen($prefix)) != 0)
        forbidden();

    $relative = str_replace(
        DIRECTORY_SEPARATOR,
        "/",
        substr($absolute, strlen($prefix))
    );
    if ($relative == "")
        bad_request();

    return ([
        "absolute" => $absolute,
        "parts" => explode("/", $relative)
    ]);
}

chdir(__DIR__."/../");
if (isset($_POST["language_select"]))
    $Language = $_POST["language_select"];
require_once ("language.php");
require_once ("tools/index.php");
load_constants();

if (!isset($_GET["target"]))
    bad_request();

$resolved_target = bouncer_resolve_target($_GET["target"]);
$_GET["target"] = $resolved_target["absolute"];
$target = $resolved_target["parts"];
if (count($target) < 1)
    bad_request();

// Le premier élément après dres/ est le type de ressource.
// L'ancien code prenait le DERNIER élément (le nom du fichier), ce qui
// empêchait les branches activity/users/doc/... de s'exécuter.
$type = $target[0];

// On s'authentifie.
require_once ("login/index.php");

/*
** Fichiers utilisateurs
** ---------------------
** Cette branche passe avant le bypass admin générique : même un admin doit
** entrer explicitement dans public/, admin/ ou perso/. La racine utilisateur
** n'est plus une zone privée implicite.
*/
if ($type == "user" || $type == "users")
{
    if (count($target) < 3)
        forbidden();

    $user = resolve_codename("user", $target[1]);
    if ($user->is_error())
        not_found();
    $id_user = (int)$user->value;

    $relative = implode("/", array_slice($target, 2));
    if (!user_storage_can_read_path($id_user, $relative))
    {
        if ($User == NULL)
            authentication_required();
        forbidden();
    }
    render_file();
}

/*
** Hors espace utilisateur, on conserve le comportement historique :
** certaines images sont accessibles sans session et un administrateur en
** mode admin peut accéder à l'ensemble des ressources.
*/
if ($User == NULL)
{
    $extension = strtolower(pathinfo($_GET["target"], PATHINFO_EXTENSION));
    if ($extension != "png" && count($target) != 1)
        authentication_required();
}

if (is_admin())
    render_file();

/*
** Ressources de correction
** -----------------------
** Les scénarios, bibliothèques et dépendances de correction sont des sources
** pédagogiques privées. Leur résolution par mergeconf reste interne au
** serveur ; leur lecture HTTP directe est réservée aux responsables.
*/
if ($type == "corrections")
{
    if (count($target) < 2 || !can_manage_corrections())
        forbidden();
    render_file();
}

/*
** Ressources de questionnaires
** ----------------------------
** Les définitions Dabsic peuvent contenir les bonnes réponses, les barèmes
** et les médailles délivrées. Elles ne sont donc jamais des ressources
** génériques de dres : leur lecture directe est réservée aux personnes qui
** peuvent administrer les questionnaires de l'établissement concerné.
*/
if ($type == "quiz")
{
    if (count($target) < 3)
        forbidden();

    require_once ("tools/questionnaire.php");
    $codename = $Database->real_escape_string($target[1]);
    $school = db_select_one("
        school.id FROM school
        WHERE school.codename = '$codename' AND school.deleted IS NULL
    ");
    if ($school == NULL)
        not_found();
    if (!questionnaire_school_can_manage((int)$school["id"]))
        forbidden();
    render_file();
}

if ($type == "activity")
{
    if (count($target) < 3)
        bad_request();

    // Au cas où l'activité soit basée sur un template.
    $codename = $Database->real_escape_string($target[1]);

    // Récupération de l'id de l'activité, template ou non.
    if (!($direct = db_select_one("
        activity.id, activity.is_template
        FROM activity WHERE codename = '$codename'
    ")))
        not_found();

    if (($activity = new FullActivity)->build($direct["id"]) == false)
        not_found();

    if ($activity->is_director || $activity->is_teacher || $activity->is_assistant)
        render_file();

    if ($direct["is_template"])
        $filter = " AND template.id = {$direct["id"]} ";
    else
        $filter = " AND activity.id = {$direct["id"]} ";

    $instances = db_select_one("
      activity.id FROM activity
      LEFT JOIN team ON team.id_activity = activity.id
      LEFT JOIN user_team ON team.id = user_team.id_team
      LEFT JOIN activity as template ON activity.id_template = template.id
      WHERE user_team.id_user = {$User["id"]}
      $filter
      ORDER BY activity.subject_appeir_date DESC
    ");

    if ($instances != NULL)
        $id = $instances["id"];
    else
        $id = $codename;

    if (($activity = new FullActivity)->build($id) == false)
        not_found();

    if (in_array($target[2], [
        "icon.png", "icon.jpeg", "icon.jpg",
        "wallpaper.png", "wallpaper.jpeg", "wallpaper.jpg",
        "intro.mp4", "intro.ogv",
    ], true))
        render_file();

    if (isset($target[3], $target[4])
        && $target[3] == "ressource"
        && ($target[4] == "admin" || $target[4] == "private"))
        forbidden();

    $activity_basename = basename($_GET["target"]);
    if (in_array($activity_basename, ["configuration.dab", "preaccess.dab", "satisfaction.dab", "rubric.dab"], true))
        not_found();

    // A pre-access quiz protects the resource itself, not merely the HTML
    // rendering of the activity page. Direct requests for the subject must
    // therefore satisfy the same prerequisite as pages/instance/subject.php.
    if (in_array($activity_basename, ["subject.pdf", "subject.txt", "subject.htm", "subject.html"], true)
        && $User != NULL
        && !$activity->is_director && !$activity->is_teacher && !$activity->is_assistant
        && !activity_preaccess_can_read_subject($activity, (int)$User["id"]))
        forbidden();

    if ($activity->registered == false || $activity->leader == 0)
        forbidden();

    if ($activity->subject_appeir_date == NULL
        || $activity->subject_appeir_date < now())
        if ($activity->subject_disappeir_date == NULL
            || $activity->subject_disappeir_date > now())
            render_file();

    forbidden();
}

if ($type == "doc")
{
    if (!is_teacher() && !is_director())
        forbidden();
    render_file();
}

if ($type == "groups")
{
    // Il faut empêcher l'accès aux fichiers de groupes aux non membres...
}

if ($type == "support")
{
    // Support sources are not public-by-login. A learner must both have access
    // to the precise support asset through one of their activities and have
    // passed the optional preaccess.dab attached to the support directory.
    if (can_edit_supports())
        render_file();
    if ($User == NULL || count($target) < 4)
        forbidden();
    if (basename($_GET["target"]) === "preaccess.dab")
        not_found();

    $asset = support_quiz_asset_for_resource_path(
        $_GET["target"],
        $target[1] ?? NULL,
        $target[2] ?? NULL
    );
    if (!is_array($asset))
        forbidden();
    // support_progress_can_current_user_access_asset() also applies the
    // support pre-access gate, so keep one authoritative access check here.
    if (!support_progress_can_current_user_access_asset((int)$asset["id"]))
        forbidden();
    render_file();
}

// Si il n'y a pas de restrictions particulières, on peut rendre le fichier.
render_file();
