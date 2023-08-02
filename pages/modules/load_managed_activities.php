<?php

if (!isset($api_id_activity))
    $api_id_activity = 0;

$cnt = 0;
$mdatas = [];
$user->managed_activities = list_of_managed_activities($user, true, false, false, $api_id_activity);
foreach ($user->managed_activities["activities"] as $man)
{
    $get_medal = $api_id_activity == $man["id_activity"];
    ($matter = new FullActivity)->buildp(
	$man["id_activity"], [
	    "get_medal" => $get_medal,
	    "blist" => [
		"activity_acquired_medal",
		"activity_presence",
		"activity_delivery",
		"activity_teacher",
		"activity_support",
		"activity_details",
	    ]
    ]);

    // Si on a dépassé la fin de 14 jours, on n'affiche pas la matière.
    if ($matter->emergence_date != NULL && date_to_timestamp($matter->emergence_date) < now() - $one_year)
	continue ;
    if (isset($requested) && $requested != NULL && $requested->id == $matter->id)
	$requested_listed = true;
    $year = datex("Y/m", $matter->emergence_date);

    // Lissage de l'organisation pour correspondre au modele "load my activities"
    // Que cela ne soit pas requis aurait été mieux
    $matter->full_activity = $matter;
    $matter->sublayer = $matter->subactivities;
    foreach ($matter->sublayer as $actt)
	$actt->full_activity = $actt;
    $matter->medal_listed = $get_medal = false;

    $mdatas[$year][] = $matter;
}

ksort($mdatas);
$mdatas = array_reverse($mdatas);

