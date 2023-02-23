<?php
// Pour l'instant...
// pas sur que ce qu'il y a en dessous fonctionne
return ;

//////////////////////////////////////////////
/// On effectue le ramassage des étudiants ///
//////////////////////////////////////////////

// S'interesse aux activités terminées dans les 3 dernières minutes
// Collecte les activités dont les rendus sont datés des 3 dernières minutes

$begin = db_form_date(now() - 60 * 3);
$end = db_form_date(now());
$activities = db_select_all("
  activity.codename as actname,
  user.codename as username
  FROM activity
  LEFT JOIN activity as template ON activity.id_template = template.id
  LEFT JOIN team ON team.id_activity = activity.id
  LEFT JOIN user_team ON team.id = user_team.id_team
  LEFT JOIN user ON user_team.id_user = user.id
  WHERE activity.is_template = 0
    AND activity.pickup_date >= '$begin'
    AND activity.pickup_date <= '$end'
    AND user_team.status = 2
    AND (activity.repository_name != '' OR template.repository_name != '')
");

foreach ($activities as $act)
{
    // Si le rendu est a faire a la main... Albedo ne peut rien faire.
    if (($err = pick_up_work(
	$act["actname"], $act["username"], NULL, AUTOMATIC_PICKUP
    )) != "")
        add_log(TRACE, "Albedo failed to retrieve work for ".$act["username"]." ".$act["actname"].": ".strval($err), 1);
}
