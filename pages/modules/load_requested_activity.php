<?php
$blist = [
    "activity_acquired_medal",
    "activity_presence",
    "activity_delivery",
    "activity_teacher",
    "activity_support",
    "activity_details"
];
if (!($_GET["b"] ?? 1))
    $blist[] = "activity_team_content";

///////////// A FAIRE
///// SI CETTE ACTIVITE FAIT PARTIE DE CELLE QUI SONT GEREE PAR L'UTILISATEUR
//// IL FAUT QUE LA PAGE MARCHE, ACTUELLEMENT, LA PAGE DE GESTION EST BUGGEE
/// SI L'ACTIVITE EST TROP ANCIENNE, EN DEHORS DU MAXDATE

($requested = new FullActivity)->buildp($_GET["a"], ["blist" => $blist]);
if ($requested->parent_activity != -1)
{
    // Une activité a été demandé. Seules les matières sont gérés ici.
    unset($requested);
    unset($_GET["a"]);
    return ;
}

$requested->full_activity = $requested;
$requested->sublayer = $requested->subactivities;
foreach ($requested->sublayer as $actt)
    $actt->full_activity = $actt;

