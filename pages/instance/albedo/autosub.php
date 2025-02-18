<?php

// Inscription automatique - normalement réalisée par l'inscriveur
// de modules!

// On récupère tout ce qui est activité a inscription automatique
// Et on procède a l'inscription
$acts = db_select_all("
  activity.id as id_activity,
  matter.id as id_matter
  FROM activity
  LEFT JOIN activity as matter ON activity.parent_activity = matter.id
  WHERE ( activity.is_template = 0 OR activity.is_template IS NULL )
  AND   activity.deleted IS NULL
  AND   activity.subscription = 2
  AND   ( activity.done_date > NOW() OR activity.done_date IS NULL )
");
//add_log(TRACE, print_r($acts, true), 1);
return ;
foreach ($acts as $act)
{
    // On cherche tout les utilisateurs concerncés par l'activité
    $users = db_select_all("
      user_cycle.id_user FROM user_cycle
      LEFT JOIN activity_cycle
      ON user_cycle.id_cycle = activity_cycle.id_cycle
      WHERE id_activity = ".$act["id_activity"]."
      OR id_activity = ".$act["id_matter"]."
      ");
    foreach ($users as $usr)
    {
	// Si l'utilisateur est déja inscrit...
	$already_sub = db_select_one("
          id FROM team LEFT JOIN user_team ON team.id = user_team.id_team
          WHERE user_team.id_user = ".$usr["id_user"]."
          AND team.id_activity = ".$act["id_activity"]."
	  ");
	if ($already_sub != NULL)
	    continue ;
	
	// C'est une activité, il faut inscrire seulement
	// Si l'utilisateur est inscrit a la matière - si on ne tiens
	// pas une matière
	if ($act["id_matter"] != -1 && $act["id_matter"] != NULL)
	{
	    // On verifie l'inscription a la matière
	    $sub = db_select_one("
             * FROM team LEFT JOIN user_team ON team.id = user_team.id_team
             WHERE id_activity = ".$act["id_matter"]."
             AND id_user = {$usr["id"]}
	     ");
	    // On est pas inscrit... on passe a la suite
	    if ($sub == NULL)
		continue ;
	}
	// On s'inscrit
	if (($ret = subscribe_to_instance(
	    $act["id_activity"], $usr["id"])
	)->is_error())
	{
	    add_log(TRACE, "Albedo failed to subscribe user ".$usr["id"]." to activity ".$act["id_activity"].": ".strval($ret), 1);
	    return ;
	}
    }
}

