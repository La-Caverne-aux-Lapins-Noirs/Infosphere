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

