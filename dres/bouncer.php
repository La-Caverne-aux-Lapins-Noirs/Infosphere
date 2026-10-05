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

function bouncer_build_activity_for_current_user($id)
{
    global $User;

    $activity = new FullActivity;
    $options = [
        "recursive" => false,
        "only_user" => true,
        "blist" => [
            "activity_acquired_medal",
            "activity_team_content",
            "activity_medal",
            "activity_support",
            "activity_details",
            "activity_texts",
        ],
    ];
    if (is_array($User))
        $options["user"] = $User;
    if (!$activity->buildp((int)$id, $options))
        return (NULL);
    return ($activity);
}

function bouncer_activity_staff_access($activity)
{
    return (is_object($activity)
        && ($activity->is_director || $activity->is_teacher || $activity->is_assistant));
}

function bouncer_activity_template_instances($id_template)
{
    $id_template = (int)$id_template;
    if ($id_template <= 0)
        return ([]);

    return (db_select_all("
        activity.id
        FROM activity
        WHERE activity.id_template = $id_template
          AND activity.is_template = 0
          AND activity.template_link = 1
          AND activity.deleted IS NULL
        ORDER BY activity.subject_appeir_date DESC, activity.id DESC
    "));
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
** entrer explicitement dans public/, admin/, perso/ ou subjects/. La racine utilisateur
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
** Pièces comptables des organisations
** ------------------------------------
** Les logos et autres ressources d'une organisation conservent leur politique
** historique. Le sous-dossier accounting/, en revanche, contient des factures
** et justificatifs financiers : il est réservé aux responsables comptables de
** l'école portée dans le chemin.
*/
if ($type == "organization" && isset($target[2]) && $target[2] == "accounting")
{
    if ($User == NULL)
        authentication_required();
    if (count($target) != 5 || !is_number($target[3]))
        forbidden();

    $id_school = (int)$target[3];
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
        forbidden();

    if (preg_match('/^(\d+)\.(pdf|png|jpg)$/i', $target[4], $match))
    {
        $id_entry = (int)$match[1];
        $entry = db_select_one("
            id, id_school
            FROM organization_account_entry
            WHERE id = $id_entry
              AND id_school = $id_school
              AND deleted IS NULL
        ");
        if ($entry == NULL)
            not_found();
        render_file();
    }

    if (preg_match('/^electronic-(\d+)\.(xml|pdf)$/i', $target[4], $match))
    {
        $organization = resolve_codename("organization", $target[1]);
        if ($organization->is_error())
            not_found();
        $id_document = (int)$match[1];
        $document = db_select_one("
            id, id_school
            FROM billing_electronic_document
            WHERE id = $id_document
              AND id_school = $id_school
              AND direction = 'incoming'
              AND buyer_organization_id = ".(int)$organization->value."
              AND deleted IS NULL
        ");
        if ($document == NULL)
            not_found();
        render_file();
    }
    forbidden();
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

// Les feuilles de session contiennent la liste nominative des apprenants.
// Le chemin numérique seul ne confère aucun droit de lecture.
if ($type == "session")
{
    if (count($target) != 3 || !ctype_digit($target[1]) || $target[2] != "emargement.pdf")
        forbidden();
    if (!is_teacher_or_director_for_session((int)$target[1]))
        forbidden();
    render_file();
}

if ($type == "activity")
{
    if (count($target) < 3)
        bad_request();

    $codename = $Database->real_escape_string($target[1]);
    $direct = db_select_one("
        activity.id, activity.is_template
        FROM activity
        WHERE activity.codename = '$codename'
          AND activity.deleted IS NULL
    ");
    if ($direct == NULL)
        not_found();

    $activity = bouncer_build_activity_for_current_user((int)$direct["id"]);
    if ($activity == NULL)
        not_found();

    // Une affectation directe sur la ressource demandee garde la priorite.
    // FullActivity tient compte des utilisateurs, des laboratoires et de leurs
    // niveaux d'autorite ; is_director couvre le responsable du cycle.
    if (bouncer_activity_staff_access($activity))
        render_file();

    $activity_basename = basename($_GET["target"]);
    if (in_array($activity_basename, [
        "icon.png", "icon.jpeg", "icon.jpg",
        "wallpaper.png", "wallpaper.jpeg", "wallpaper.jpg",
        "intro.mp4", "intro.ogv",
    ], true))
        render_file();

    /*
    ** Un fichier herite conserve physiquement le chemin du template. Le droit
    ** d'acces, lui, appartient a l'instance. Pour une ressource de template,
    ** on cherche donc une instance liee dans laquelle l'utilisateur est soit
    ** encadrant, soit effectivement inscrit. Cette resolution remplace l'ancien
    ** JOIN sur user_team, trop strict notamment pour les activites utilisant un
    ** pool d'equipe de reference.
    */
    if (!empty($direct["is_template"]))
    {
        $registered_instance = NULL;
        foreach (bouncer_activity_template_instances((int)$direct["id"]) as $instance)
        {
            $candidate = bouncer_build_activity_for_current_user((int)$instance["id"]);
            if ($candidate == NULL)
                continue ;
            if (bouncer_activity_staff_access($candidate))
                render_file();
            if ($registered_instance == NULL
                && $candidate->registered
                && (int)$candidate->leader > 0)
                $registered_instance = $candidate;
        }
        if ($registered_instance != NULL)
            $activity = $registered_instance;
    }

    if (isset($target[3], $target[4])
        && $target[3] == "ressource"
        && ($target[4] == "admin" || $target[4] == "private"))
        forbidden();

    if (in_array($activity_basename, [
        "configuration.dab", "preaccess.dab", "satisfaction.dab", "rubric.dab",
        "subject.meta.json"
    ], true))
        not_found();

    // A pre-access quiz protects the resource itself, not merely the HTML
    // rendering of the activity page. Direct requests for the subject must
    // therefore satisfy the same prerequisite as pages/instance/subject.php.
    if (in_array($activity_basename, ["subject.pdf", "subject.txt", "subject.htm", "subject.html"], true)
        && $User != NULL
        && !activity_preaccess_can_read_subject($activity, (int)$User["id"]))
        forbidden();

    // leader contient le statut user_team : 0 = invitation non acceptee,
    // 1 = membre inscrit, 2 = responsable d'equipe. Les deux derniers ont
    // donc acces au sujet ; une simple invitation ne suffit pas.
    if ($activity->registered == false || (int)$activity->leader <= 0)
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
    if (!am_i_teacher() && !am_i_director())
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
