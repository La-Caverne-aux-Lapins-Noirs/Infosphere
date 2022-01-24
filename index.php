<?php
if (!file_exists("version.php") && !isset($albedo))
{
    echo "<html><head>";
    echo '<meta http-equiv="refresh" content="0;url=install.php" />';
    echo "</head></html>";
    exit ;
}
@define("DEBUG", true);
@define("UNIT_TEST", 0);
//date_default_timezone_set('UTC');

$PHPPerf = microtime(true);

/// Langage - Genère LanguageList, Language et Dictionnary
if (isset($_POST["language_select"]))
    $Language = $_POST["language_select"];
require_once ("language.php");

if($Language == "fr")
    setlocale(LC_TIME, 'fr_FR.utf8','fra');

/// Navigation
if (isset($_GET["p"]))
    $Position = $_GET["p"];
else
    $Position = "HomeMenu";
if (isset($_GET["pp"]))
    $PreviousPosition = $_GET["pp"];
else
    $PreviousPosition = $Position;
// "API MODE"
$silent = isset($_POST["silent"]) || isset($_GET["silent"]);
$export = isset($_POST["export"]) || isset($_GET["silent"]);
if ($export)
    $silent = 1;

/// Ensemble de fonction, ainsi que le symbole Database
require_once ("tools/index.php");
load_constants(); // Détaché de tools pour permettre aux tests un appel.

/// Est-t-on connecté, tentons nous de nous connecter, de nous inscrire ou de partir?
if (isset($_POST["logaction"]))
{
    if ($_POST["logaction"] == "login")
	$Msg = get_login_info($_POST["login"], $_POST["password"]);
    else if ($_POST["logaction"] == "logout")
    {
	$Msg = ["User" => NULL, "Error" => ""];
	set_cookie("login", "", time() + 1);
	set_cookie("password", "", time() + 1);
    }
    else if ($_POST["logaction"] == "subscribe")
    {
	$Msg = try_subscribe($_POST["login"], $_POST["mail"], $_POST["password"], $_POST["repassword"]);
	// Si l'inscription a échoué, on retourne a la page d'inscription.
	if ($Msg["Error"] != "")
	{
	    $PreviousPosition = $Position;
	    $Position = "Subscribe";
	}
    }
}
else if (isset($_COOKIE["login"]) && isset($_COOKIE["password"]))
    $Msg = get_login_info($_COOKIE["login"], $_COOKIE["password"], false);
else
    $Msg = ["User" => NULL, "Error" => ""];
$User = $Msg["User"];
$ErrorMsg = $Msg["Error"];
$LogMsg = "";

$OriginalUser = $User;
$ParentConnexion = false;
if ($User["authority"] >= ADMINISTRATOR)
{
    $x = "";
    if (isset($_POST["log_as"]))
	$x = $_POST["log_as"];
    else if (isset($_COOKIE["log_as"]))
	$x = $_COOKIE["log_as"];
    if ($x != "")
    {
	if (($usr = resolve_codename("user", $x, "codename", true))->is_error())
	    $ErrorMsg = strval($usr);
	else
	{
	    $User = $usr->value;
	    if (($User["misc_configuration"] = json_decode($User["misc_configuration"], true)) == NULL)
		$user["misc_configuration"] = [];
	    get_user_promotions($User);
	    get_user_children($User);
	    get_user_laboratories($User);
	    set_cookie("log_as", $User["id"], time() + 60 * 60 * 24 * 7);
	}
    }
}

// Log as parents
$x = "";
if (isset($_POST["children"]))
    $x = $_POST["children"];
else if (isset($_COOKIE["children"]))
    $x = $_COOKIE["children"];
if ($x != "")
{
    if (($usr = resolve_codename("user", $x, "codename", true))->is_error())
	$ErrorMsg = strval($usr);
    else
    {
	$usr = $usr->value;
	$check = db_select_one("
               * FROM parent_child
               WHERE id_parent = ".$OriginalUser["id"]." AND id_child = ".$usr["id"]
	);
	if (!$check && $usr["id"] != $OriginalUser["id"])
	    $ErrorMsg = strval(new ErrorResponse("NotYourChildren", $usr["codename"]));
	else
	{
	    $User = $usr;
	    get_user_promotions($User);
	    get_user_children($User);
	    get_user_laboratories($User);
	    set_cookie("children", $User["id"], time() + 60 * 60 * 24 * 7);
	    if ($User["id"] != $OriginalUser["id"])
		$ParentConnexion = true;
	}
    }
}

compute_student_log();

if ($OriginalUser["authority"] >= ADMINISTRATOR && $User["authority"] >= ADMINISTRATOR)
{
    if (isset($_COOKIE["admin_mode"]))
	$User["admin_mode"] = $_COOKIE["admin_mode"];
    else
	$User["admin_mode"] = false;
    if (isset($_POST["admin_mode"]))
	set_cookie("admin_mode", $User["admin_mode"] = !$User["admin_mode"], time() + 60 * 60 * 24 * 7);
}

if (isset($User["localisation"]))
    $Localisation = $User["localisation"];
else
    $Localisation = "Etc/UTC";


$Pages = [
    // Il faudra changer les etiquettes de ce tableau a terme pour adopter un style plus API (et documenter l'ensemble des interfaces du site)

    ///////////////////// PAGE ELEVES
    // Page de status de l'utilisateur (Eleves, profs et administrateur)
    "HomeMenu" => ["File" => "./pages/home/index.phtml", "Menu" => "Top", "Authority" => OUTSIDE],
    // Page de profil de l'utilisateur
    "ProfileMenu" => ["File" => "./pages/profile/index.phtml", "Menu" => "Top", "Authority" => EXTERN],
    // Calendrier de l'utilisateur (Eleves, [profs et administrateur]-a_faire)
    "CalendarMenu" => ["File" => "./pages/calendar/index.phtml", "Menu" => "Top", "Authority" => STUDENT],
    // Page de liste des modules
    "ModulesMenu" => ["File" => "./pages/modules/index.phtml", "Menu" => "Top", "Authority" => STUDENT],
    // Galerie Enseignante - Coté élève
    "TopGalleryMenu" => ["File" => "./pages/class/index.php", "Menu" => "Top", "Authority" => STUDENT],
    // Liste des salles avec plans
    "RoomsMenu" => ["File" => "./pages/rooms/index.phtml", "Menu" => "Top", "Authority" => STUDENT],
    // Page de rendu
    "FetchingMenu" => ["File" => "./pages/fetching/index.phtml", "Menu" => "Top", "Authority" => EXTERN],
    // Menu de recherche général
    "TokenMenu" => ["File" => "./pages/token/index.phtml", "Menu" => "Top", "Authority" => STUDENT],
    // Intercom
    "IntercomMenu" => ["File" => "./pages/intercom/index.phtml", "Menu" => "Top", "Authority" => OUTSIDE],
    // Videosphere
    "VideoSphereMenu" => ["File" => "http://video.ecole-89.com", "Menu" => "Top", "Authority" => OUTSIDE],
    // Forge
    "ForgeMenu" => ["File" => "http://git.ecole-89.com", "Menu" => "Top", "Authority" => OUTSIDE],
    // Mettre un lien vers la LibLapin
    "LibLapinMenu" => ["File" => "http://intra.ecole-89.com/liblapin", "Menu" => "Top", "Authority" => OUTSIDE],
    // Menu de recherche général
    "SearchMenu" => ["File" => "./pages/search/index.phtml", "Menu" => "Top", "Authority" => STUDENT],


    // Page d'instance/session - NON VISIBLE DEPUIS LE MENU
    "ActivityMenu" => ["File" => "./pages/instance/index.phtml", "Menu" => "Hidden", "Authority" => STUDENT],

    ///////////////////// PAGE ADMINISTRATEUR / PROFESSEURS / RESPONSABLE
    // Liste des étudiants
    "UsersMenu" => ["File" => "./pages/users/index.phtml", "Menu" => "Bottom", "Authority" => STUDENT],
    // Liste des groupes
    "LaboratoriesMenu" => ["File" => "./pages/laboratories/index.phtml", "Menu" => "Bottom", "Authority" => STUDENT],
    // Liste des salles
    "AdminRoomsMenu" => ["File" => "./pages/rooms_admin/index.phtml", "Menu" => "Bottom", "Authority" => ADMINISTRATOR],
    // Liste des médailles
    "MedalsMenu" => ["File" => "./pages/medals/index.phtml", "Menu" => "Bottom", "Authority" => STUDENT],
    // Liste des fonctions
    "FunctionMenu" => ["File" => "./pages/functions/index.phtml", "Menu" => "Bottom", "Authority" => ADMINISTRATOR],
    // Liste des patrons d'activités
    "ActivitiesMenu" => ["File" => "./pages/activities/index.phtml", "Menu" => "Bottom", "Authority" => CONTENT_AUTHOR],
    // Liste des modules instanciés
    "InstancesMenu" => ["File" => "./pages/activities/index.phtml", "Menu" => "Bottom", "Authority" => CONTENT_AUTHOR],
    // Liste des promotions
    "CycleMenu" => ["File" => "./pages/cycle/index.phtml", "Menu" => "Bottom", "Authority" => STUDENT],

    // Galerie Enseignante
    "GalleryMenu" => ["File" => "./pages/class_admin/index.phtml", "Menu" => "Bottom", "Authority" => ADMINISTRATOR],
    // Galerie Etudiante
    "StudGalleryMenu" => ["File" => "./pages/gallery_student/index.php", "Menu" => "Bottom", "Authority" => ADMINISTRATOR],
    // Gestion des robots de correction
    "RobotMenu" => ["File" => "./pages/robot/index.php", "Menu" => "Bottom", "Authority" => ADMINISTRATOR],
    // Page de configuration
    "ConfigMenu" => ["File" => "./pages/configuration/index.php", "Menu" => "Bottom", "Authority" => ADMINISTRATOR],
    "ScaleMenu" => ["File" => "./pages/scale/index.php", "Menu" => "Bottom", "Authority" => ADMINISTRATOR],
    "TestMenu" => ["File" => "./pages/test/index.php", "Menu" => "Bottom", "Authority" => ADMINISTRATOR],

    // TOOLS
    // Page d'inscription au site
    "Subscribe" => ["File" => "subscribe.phtml", "Menu" => false, "Authority" => OUTSIDE],
];

$include_shield = hash("md5", "prevent weird stuff");

if (isset($_GET["ressource"]))
    require_once ("ressource.php");
else if ($silent)
    require_once ("dispatch.php");
else
    require_once ("index.phtml");

?>

