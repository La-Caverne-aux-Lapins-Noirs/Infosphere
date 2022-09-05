<?php
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
    "ClassMenu" => "./pages/class/",
    "FetchingMenu" => "./pages/fetching/",
    "TokenMenu" => "./pages/token/",
    "IntercomMenu" => "./pages/intercom/"
    // "StudGalleryMenu" => "./pages/gallery/",
];
if (isset($Configuration->Properties["video_service"]))
    $TopMenu["VideoSphereMenu"] = $Configuration->Properties["video_service"];
if (isset($Configuration->Properties["forge_service"]))
    $TopMenu["ForgeMenu"] = $Configuration->Properties["forge_service"];
$TopMenu["LibLapinMenu"] = "http://liblapin.org";
$TopMenu["SearchMenu"] = "./pages/search/";

$BottomMenu = [
    "UsersMenu" => "./pages/user/",
    "LaboratoryMenu" => "./pages/laboratory/",
    "MedalsMenu" => "./pages/medal/",
    "FunctionMenu" => "./pages/function/",

    "CycleTemplateMenu" => "./pages/cycle/",
    "ActivityTemplatesMenu" => "./pages/activity/",
    "AdminClassMenu" => "./pages/class/admin/",
    "ScaleMenu" => "./pages/scale/",
    
    "SchoolMenu" => "./pages/school/",
    "InstancesMenu" => "./pages/activity/",
    "CycleMenu" => "./pages/cycle/",
    // "RobotMenu" => "./pages/robot/",
    // "ExercisesMenu" => "./pages/exercises/",
    
    "RoomsMenu" => "./pages/room/",
    "ConfigMenu" => "./pages/configuration/",
    "TestMenu" => "./pages/test/",
];

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

