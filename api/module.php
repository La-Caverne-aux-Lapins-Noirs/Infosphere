<?php

require ("activity.php");

function DisplayModulePanel($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $ActivityType;
    global $User;
    global $Position;

    if ($id == -1)
	bad_request();

    $blist = [
	"activity_acquired_medal",
	"activity_presence",
	"activity_delivery",
	"activity_teacher",
	"activity_support",
	"activity_details"
    ];
    ($matter = new FullActivity)->buildp($id, ["blist" => $blist]);
    if (!isset($matter->id) || ($matter->parent_activity != -1 && $matter->parent_activity !== NULL))
	not_found();

    if (!is_director() && !is_cycle_director() && !$matter->is_teacher &&
	!$matter->registered && !$matter->can_subscribe)
	forbidden();

    $matter->full_activity = $matter;
    $matter->sublayer = $matter->subactivities;
    foreach ($matter->sublayer as $actt)
	$actt->full_activity = $actt;

    // La même matière peut être affichée soit depuis le cursus de l'élève,
    // soit depuis la liste des activités encadrées. L'ancien rendu PHP
    // distinguait ces deux cas avec $is_admin_module. En lazy-load, il faut
    // transmettre explicitement le contexte pour ne pas afficher les boutons
    // d'administration dans la vue élève, notamment en incarnation.
    $can_manage_module = $matter->is_teacher || is_director() || is_cycle_director();
    $managed_context = (isset($data["managed"]) && $data["managed"])
	|| (isset($_GET["managed"]) && $_GET["managed"]);
    $is_admin_module = $managed_context && $can_manage_module;
    ob_start();
    require ("./pages/modules/module.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

// Les quelques différences existantes entre activité générale et module.
$Tab["GET"][""][1] = "DisplayModule";
$Tab["GET"]["panel"] = [
    "logged_in",
    "DisplayModulePanel",
];
$Tab["POST"][""][1] = "AddModule";
// $Tab["PUT"][0] = "is_teacher";
