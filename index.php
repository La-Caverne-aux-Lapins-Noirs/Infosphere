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
];

$TopMenu = [
    "HomeMenu" => "./pages/home/",
    "ProfileMenu" => "./pages/profile/",
    "CalendarMenu" => "./pages/calendar/",
    "ModulesMenu" => "./pages/modules/",
    "ClassMenu" => "./pages/support/",
    "FetchingMenu" => "./pages/fetching/",
    "TokenMenu" => "./pages/token/",
    "IntercomMenu" => "./pages/intercom/",
    "BookMenu" => "./pages/book/",
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
    "UsersMenu" => "./pages/user/",
    "LaboratoryMenu" => "./pages/laboratory/",
    "MedalsMenu" => "./pages/medal/",
    "FunctionMenu" => "./pages/function/",

    "CycleTemplateMenu" => "./pages/cycle/",
    "ActivityTemplatesMenu" => "./pages/activity/",
    "ScaleMenu" => "./pages/scale/",
    
    "SchoolMenu" => "./pages/school/",
    "InstancesMenu" => "./pages/activity/",
    "CycleMenu" => "./pages/cycle/",
    
    "RoomsMenu" => "./pages/room/",
    "RobotMenu" => "./pages/robot/",
    "DocMenu" => "./pages/docs/",
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

