<?php
/////////////////////////////////////
// Vitrine                       ///
///////////////////////////////////
// require_once ("showcase/index.htm"); die();

/////////////////////////////////////
// Si on est pas encore installé ///
///////////////////////////////////

if (!file_exists("version.php") && !isset($albedo))
{
?>
    <html>
	<head>
	    <meta http-equiv="refresh" content="0;url=install.php" />
	</head>
    </html>
<?php
    die();
}

////////////////////////////////
// Mode d'execution du site ///
//////////////////////////////

@define("DEBUG", true);
@define("UNIT_TEST", 0);
$PHPPerf = microtime(true);

//////////////////////////////////////////////////////////////
// Langage - Genère LanguageList, Language et Dictionnary ///
////////////////////////////////////////////////////////////

if (isset($_POST["language_select"]))
    $Language = $_POST["language_select"];
require_once ("language.php");
if($Language == "fr")
    setlocale(LC_TIME, 'fr_FR.utf8','fra');

//////////////////
// Navigation ///
////////////////

if (isset($_GET["p"]))
    $Position = $_GET["p"];
else
    $Position = "HomeMenu";
if ($Position == "RegistrationForm" || $Position == "QuizPublic")
{
    header("Cache-Control: no-store, private");
    header("Referrer-Policy: no-referrer");
    header("X-Robots-Tag: noindex, nofollow, noarchive");
}
if (isset($_GET["pp"]))
    $PreviousPosition = $_GET["pp"];
else
    $PreviousPosition = $Position;

//////////////////
// "API MODE" /// OBSOLETE
////////////////

$silent = isset($_POST["silent"]) || isset($_GET["silent"]);
$export = isset($_POST["export"]) || isset($_GET["silent"]);
if ($export)
    $silent = 1;

// Ensemble de fonction, ainsi que le symbole Database
require_once ("tools/index.php");
// Détaché de tools pour permettre aux tests un appel.
load_constants();

// Tout ce qui concerne la connexion, le log as, le mode admin
require_once ("login/index.php");

// Endpoint de detail charge uniquement lorsqu'une evaluation est deployee.
if (isset($_GET["evaluation_detail"]))
{
    header("Content-Type: text/html; charset=UTF-8");
    header("Cache-Control: private, no-store");
    $aid = filter_input(INPUT_GET, "a", FILTER_VALIDATE_INT);
    $tid = filter_input(INPUT_GET, "t", FILTER_VALIDATE_INT);
    $eid = (string)$_GET["evaluation_detail"];
    if (!$aid || !$tid || !isset($User["id"]))
    {
        http_response_code(403);
        exit;
    }
    $allowed = db_select_one("user_team.id FROM user_team
        INNER JOIN team ON team.id = user_team.id_team
        WHERE user_team.id_user = ".(int)$User["id"]."
        AND team.id = ".$tid." AND team.id_activity = ".$aid);
    if (!$allowed && !(is_admin() || is_teacher_for_activity($aid)))
    {
        http_response_code(403);
        exit;
    }
    $owner = db_select_one("team.id FROM team WHERE team.id = ".$tid.
        " AND team.id_activity = ".$aid);
    if (!$owner)
    {
        http_response_code(404);
        exit;
    }
    $report = evaluation_history_report($aid, $tid, $eid);
    if ($report === null)
    {
        http_response_code(404);
        echo "Rapport indisponible.";
    }
    else
    {
        $metadata_file = evaluation_history_root($aid, $tid).'/history/'.$eid.'.json';
        $metadata = is_file($metadata_file)
            ? json_decode(file_get_contents($metadata_file), true) : null;
        if (!is_array($metadata) || ($metadata['id'] ?? '') !== $eid) $metadata = [];
        echo evaluation_history_comparison_html($metadata['comparison'] ?? null);
        echo evaluation_history_render($report);
    }
    exit;
}


// On trace la visite pour fournir des infos en cas de problème
trace();

////////////////////
// Localisation ///
//////////////////

if (isset($User["localisation"]))
    $Localisation = $User["localisation"];
else
    $Localisation = "Etc/UTC";

/////////////////////////////////////////////////////////////////////////
// Pages accessibles via les menus supérieurs et inférieurs et autre ///
///////////////////////////////////////////////////////////////////////

$Unlisted = [
    "ActivityMenu" => "./pages/instance/",
    "Subscribe" => "./pages/subscribe/",
    "TeacherMenu" => "./pages/profile/", // Redirection vers profil pour des raisons de simplicité
    "SupportMenu" => "./pages/support/",
    "SupportAssetMenu" => "./pages/support/",
    "SupportCategoryMenu" => "./pages/support/",
    "CampaignMenu" => "./pages/prospecting/",
    "BillingTemplateMenu" => "./pages/billing/",
    "BillingInvoiceMenu" => "./pages/billing/",
    "FunctionMenu" => "./pages/function/",
    "JuryMenu" => "./pages/jury/",
    "DabsicEditorMenu" => "./pages/dabsic_editor/",
    "DabsicFormMenu" => "./pages/dabsic_form/",
    "QuizAttemptMenu" => "./pages/quiz_attempt/",
    "QuizPublic" => "./pages/quiz_public/",
    "RegistrationForm" => "./pages/registration_form/",
    "DocumentSignaturePdf" => "./pages/document_pdf/",
    "DocumentWorkflowPdf" => "./pages/document_pdf/",
    "DocumentPrintPdf" => "./pages/document_pdf/",
];

$TopMenu = [
    "HomeMenu" => "./pages/home/",
    "ProfileMenu" => "./pages/profile/",
    "CalendarMenu" => "./pages/calendar/",
    "QuizInboxMenu" => "./pages/quiz_inbox/",
    "ModulesMenu" => "./pages/modules/",
    
    "ClassMenu" => "./pages/support/",
    
    // "FetchingMenu" => "./pages/fetching/", // Inutile. Le concept a changé.
    // "TokenMenu" => "./pages/token/", // Inutile. Le concept a changé.
    "UsersMenu" => "./pages/user/",
    "LaboratoriesMenu" => "./pages/laboratory/",
    "MedalsMenu" => "./pages/medal/",

    "IntercomMenu" => "./pages/intercom/",
    "LibraryMenu" => "./pages/book/",
    "StudGalleryMenu" => "./pages/gallery/",
];
if (isset($Configuration->Properties["video_service"]))
    $TopMenu["VideoSphereMenu"] = $Configuration->Properties["video_service"];
if (isset($Configuration->Properties["forge_service"]))
    $TopMenu["ForgeMenu"] = $Configuration->Properties["forge_service"];
if (isset($Configuration->Properties["liblapin"]))
    $TopMenu["LibLapinMenu"] = "http://liblapin.org";
$TopMenu["SearchMenu"] = "./pages/search/";

$BottomMenu = [
    "CycleTemplateMenu" => "./pages/cycle/",
    "ActivityTemplatesMenu" => "./pages/activity/",
    "CycleMenu" => "./pages/cycle/",
    "InstancesMenu" => "./pages/activity/",
    "CorrectionMenu" => "./pages/correction/",
    "QuestionnaireMenu" => "./pages/questionnaire/",
    "ScaleMenu" => "./pages/scale/",
    
    "EnterpriseMenu" => "./pages/enterprise/",
    "SchoolMenu" => "./pages/school/",
    "RoomsMenu" => "./pages/room/",
    "DocMenu" => "./pages/docs/",
    "CertificationMenu" => "./pages/jury/",
    "ProspectingMenu" => "./pages/prospecting/",
    "BillingMenu" => "./pages/billing/",
    "ConfigMenu" => "./pages/configuration/",
    "TestMenu" => "./pages/test/",
];

if (!isset($TopMenu[$Position])
    && !isset($BottomMenu[$Position])
    && !isset($Unlisted[$Position]))
    $Position = "HomeMenu";

//////////////////////////////////////////////
// On poursuit la construction de la page ///
////////////////////////////////////////////

$include_shield = hash("md5", "prevent weird stuff");
if (isset($_GET["ressource"]))
    require_once ("ressource.php");
else if ($silent)
    require_once ("dispatch.php");
else
    require_once ("index.phtml");

?>

