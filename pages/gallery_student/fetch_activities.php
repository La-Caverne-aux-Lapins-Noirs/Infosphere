<?php

function fetch_activities($parent = -1, $id = -1)
{
    global $Database;
    global $Language;

    $activities = [];
    $forge = "";
    if($parent != -1)
	$forge = "parent_activity = $parent &&";
    $forges = "";
    if($id != -1)
	$forges = "&& id = $id";
    
    $activities_query = $Database->query("
      SELECT id, codename, ".$Language."_name as ".$Language."_name, ".$Language."_description as ".$Language."_description, enabled, type
      FROM activity
      WHERE $forge deleted = 0 $forges
      ORDER BY codename ASC
    ");
    while (($act = $activities_query->fetch_assoc()))
    {
	$forge = "
          SELECT medal.codename, medal.".$Language."_name as name, medal.".$Language."_description as description, icon
          FROM activity_medal LEFT JOIN medal ON activity_medal.id_medal = medal.id
          WHERE activity_medal.id_activity = ".$act["id"]."
	  ";
	if (($medals_query = $Database->query($forge)) == false)
	    return (NULL);
	// Il faudrait tester si language_name et language_description sont vide et basculer sur une autre
	// langue si ils sont vide
	$activities[$act["codename"]] = $act;
	$activities[$act["codename"]]["medals"] = [];
	while (($med = $medals_query->fetch_assoc()))
	{
	    $activities[$act["codename"]]["medals"][] = $med;
	}
    }
    return ($activities);
}
