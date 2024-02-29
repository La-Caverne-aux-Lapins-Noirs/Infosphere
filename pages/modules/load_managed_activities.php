<?php

if (!isset($api_id_activity))
    $api_id_activity = 0;

$cnt = 0;
$mdatas = [];
$user->managed_activities = list_of_managed_activities
($user, true, false, false, $api_id_activity,
 now() - $one_year / 2
);
foreach ($user->managed_activities["activities"] as $man)
{
    $get_medal = $api_id_activity == $man["id_activity"];
    ($matter = new FullActivity)->buildp(
	$man["id_activity"], [
	    "get_medal" => false, //$get_medal,
	    "sub_get_medal" => $get_medal,
	    "blist" => [
		"activity_acquired_medal",
		"activity_presence",
		"activity_delivery",
		"activity_teacher",
		"activity_support",
		"activity_details",
	    ]
    ]);
    if ($get_medal)
    {
	foreach ($matter->team as &$subx)
	{
	    $subuserx = &$subx["user"][array_key_first($subx["user"])];
	    $subuserx["medal"] = [];
	    foreach ($matter->subactivities as $subactx)
	    {
		foreach ($subactx->team as $teamx)
		{
		    foreach ($teamx["user"] as $userx)
		    {
			if ($userx["id"] != $subuserx["id"])
			    continue ;
			$meds = db_select_all("
				medal.codename, medal.id FROM activity_user_medal
				LEFT JOIN user_medal ON activity_user_medal.id_user_medal = user_medal.id
                                LEFT JOIN medal ON medal.id = user_medal.id_medal
                                WHERE id_activity = $subactx->id
                                AND user_medal.id_user = {$userx["id"]}
                                AND activity_user_medal.result = 1
				");
			foreach ($meds as $medalx)
			{
			    $subuserx["medal"][$medalx["codename"]] = [
				"success" => 1,
				"codename" => $medalx["codename"],
				"id" => $medalx["id"],
			    ];
			}
		    }
		}
	    }
	}
    }

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

