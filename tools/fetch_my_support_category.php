<?php

function fetch_my_support_category()
{
    global $User;

    ////////////////////////////////////////////////////////////////////
    // C'est pas le meilleur algo, mais c'est le plus rapide a coder.
    // On ramasse 100% des activités et on exfiltre celles qui ne nous concerne pas
    ////////////////////////////////////////////////////////////////////
    // Il faudra probablement changer cette technique à terme

    if (is_admin())
	return (fetch_support_category(-1, true));
    if ($User == NULL)
	return (new ErrorResponse("NotMyActivity"));
    $id_user = $User["id"];
    if (($categories = fetch_support_category(-1, true))->is_error())
	return ($categories);
    $categories = $categories->value;
    foreach ($categories as &$c)
    {
	$c["selected"] = false;
	foreach ($c["support"] as &$s)
	{
	    $s["selected"] = false;
	    foreach ($s["asset"] as &$a)
		$a["selected"] = false;
	}
    }
    
    $activities = db_select_all("
       activity.id FROM activity
       LEFT JOIN team ON activity.id = team.id_activity
       LEFT JOIN user_team ON team.id = user_team.id_team
       WHERE user_team.id_user = $id_user
       AND activity.deleted IS NULL
    ");
    foreach ($activities as $act)
    {
	$supports = fetch_activity_support($act["id"], true);
	foreach ($supports as $sup)
	{
	    // C'est une activité, donc inutile d'aller la chercher
	    if ($sup["type"] == 3)
		continue ;
	    // C'est une catégorie: on charge l'intégralité de celle-ci
	    if ($sup["type"] == 2)
	    {
		foreach ($categories as &$c)
		{
		    if ($c["id"] == $sup["id_support_category"])
		    {
			$c["selected"] = true;
			foreach ($c["support"] as &$s)
			{
			    $s["selected"] = true;
			    foreach ($s["asset"] as &$a)
				$a["selected"] = true;
			}
			break ;
		    }
		}
	    }
	    // C'est une lecon, on charge l'intégralité de celle ci + le droit
	    // d'accéder à la catégorie
	    else if ($sup["type"] == 1)
	    {
		foreach ($categories as &$c)
		{
		    foreach ($c["support"] as &$s)
		    {
			if ($s["id"] == $sup["id_support"])
			{
			    $c["selected"] = true;
			    $s["selected"] = true;
			    foreach ($s["asset"] as &$a)
				$a["selected"] = true;
			    break 2;
			}
		    }
		}
	    }
	    // C'est un chapitre, on charge les droits d'accès des niveaux au
	    // dessus
	    else if ($sup["type"] == 0)
	    {
		foreach ($categories as &$c)
		{
		    foreach ($c["support"] as &$s)
		    {
			foreach ($s["asset"] as &$a)
			{
			    if ($a["id"] == $sup["id_support_asset"])
			    {
				$c["selected"] = true;
				$s["selected"] = true;
				$a["selected"] = true;
				break 3;
			    }
			}
		    }
		}
	    }
	}
    }
    return (new ValueResponse($categories));
}
