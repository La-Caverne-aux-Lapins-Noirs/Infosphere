<?php

if (!isset($lel))
    return ; // temporaire

///////////////////////////////////////
// RAFRAICHISSEMENT DES STATS ELEVES //
///////////////////////////////////////



////////////////////////////////////////////////////
/// ENVOI AUTOMATIQUE DE MAIL D'ACTIVITE ENCADREE //
////////////////////////////////////////////////////

// Dans la nuit de samedi à dimanche, pendant 20 minutes
if (datex("N", time()) != 6 || datex("N", time() + 60 * 20) != 7)
    return ;

// On récupère les utilisateurs qui vont d'activité le rappel automatique
$users = db_select_all("
  id, mail, misc_configuration
  FROM user
  WHERE misc_configuration LIKE '%managed_activity_report\":true%'
");

// On parcoure les utilisateurs pour etre sur qu'on a pas deja envoyé à certains users leur rapport
foreach ($users as $usr)
{
    $dec = json_decode($usr["misc_configuration"], true);
    if (date_to_timestamp($dec["last_report"]))
    {
    }
}

if (count($users) == 0)
    return ;

$delay = "'".db_form_date(time() + 60 * 60 * 24 * 8)."'";

// On récupère les projets qui vont commencer
$starting_projects = db_select_all("
   activity.*, activity.{$Language}_name as name
   FROM activity
   LEFT JOIN activity as parent ON activity.parent_activity = parent.id
   WHERE activity.subject_appeir_date >= NOW()
     AND activity.subject_appeir_date <= $delay
     AND activity.type >= 14 AND activity.type <= 17
     AND activity.is_template = 0
     AND activity.parent_activity != -1
");

// On récupère les projets qui vont terminer
$ending_projects = db_select_all("
   activity.*, activity.{$Language}_name as name
   FROM activity
   LEFT JOIN activity as parent ON activity.parent_activity = parent.id
   WHERE activity.pickup_date >= NOW()
     AND activity.pickup_date <= $delay
     AND activity.type >= 14 AND activity.type <= 17
     AND activity.id_template != -1
     AND activity.parent_activity != -1
");

// On récupère les interventions en salle
$next_week_activity = db_select_all("
   session.*,
   activity.{$Language}_name as name,
   activity.id_template as id_template,
   activity.parent_activity as parent_activity,
   parent.{$Language}_name as parent_name
   FROM session
   LEFT JOIN activity ON session.id_activity = activity.id
   LEFT JOIN activity as parent ON parent.id = activity.parent_activity
   WHERE session.begin_date >= NOW()
     AND session.begin_date <= $delay
     AND activity.id_template != -1
     AND activity.parent_activity != -1
   ORDER BY begin_date
");

function is_teacherx($activity, $module, $filter)
{
    return (db_select_one("
      id_user, id_laboratory
      FROM activity_teacher
      WHERE (id_activity = {$activity} OR id_activity = {$module} ) AND ( $filter )
    ") != NULL);
}

function send_mailx($tar, $tit, $bod)
{
    echo $tar."<br />";
    echo $tit."<br />";
    echo str_replace("\n", "<br />", $bod)."<br />";
}

foreach ($users as $usr)
{
    $act = "";

    // On récupère les laboratoires de l'utilisateur
    $labs = db_select_all("
       id_laboratory
       FROM user_laboratory
       WHERE id_user = {$usr["id"]} AND authority >= 1
       ", "id_laboratory");
    $idlabs = [];
    foreach ($labs as $l)
    {
	$idlabs[] = "id_laboratory = ".$l["id_laboratory"];
    }
    // On génère un filtre limitant la recuperation du lien activité-prof
    if (count($idlabs))
	$filter = " ( id_user = {$usr["id"]} OR ".implode(" OR ", $idlabs)." ) ";
    else
	$filter = " id_user = {$usr["id"]} ";

    $intro = $Dictionnary["ManagedActivityReportStartProject"]."\n";
    $content = "";
    foreach ($starting_projects as $sp)
    {
	if (!is_teacherx($sp["id"], $sp["parent_activity"], $filter))
	    continue ;
	$id = $sp["id"];
	if (@strlen($name = $sp["name"]) == 0)
	    $name = db_select_one("{$Language}_name as name FROM activity WHERE id = {$sp["id_template"]}")["name"];
	$content .= ' - <a href="http://'.$Configuration->Properties["domain"].
		    '/index.php?p=ActivityMenu&amp;a='.$id.'">'.$name."</a>\n";
    }
    if ($content != "")
	$act .= "$intro$content<br />";

    $intro = $Dictionnary["ManagedActivityReportStopProject"]."\n";
    $content = "";
    foreach ($ending_projects as $sp)
    {
	if (!is_teacherx($sp["id"], $sp["parent_activity"], $filter))
	    continue ;
	$id = $sp["id"];
	if (@strlen($name = $sp["name"]) == 0)
	    $name = db_select_one("{$Language}_name as name FROM activity WHERE id = {$sp["id_template"]}")["name"];
	$content .= ' - <a href="http://'.$Configuration->Properties["domain"].'/index.php?p=ActivityMenu&amp;a='.$id.'">'.$name."</a>\n";
    }
    if ($content != "")
	$act .= "$intro$content<br />";

    $intro = $Dictionnary["ManagedActivityReportClass"]."\n";
    $content = "";
    foreach ($next_week_activity as $sp)
    {
	if (!is_teacherx($sp["id_activity"], $sp["parent_activity"], $filter))
	    continue ;
	$ida = $sp["id_activity"];
	$idb = $sp["id"];
	if (@strlen($name = $sp["name"]) == 0)
	    $name = db_select_one("{$Language}_name as name FROM activity WHERE id = {$sp["id_template"]}")["name"];
	if (@strlen($parent_name = $sp["parent_name"]) == 0)
	    $parent_name = db_select_one("
               template.{$Language}_name as name
               FROM activity
               LEFT JOIN activity as template ON activity.id_template = template.id
               WHERE activity.id = {$sp["parent_activity"]}
	       ")["name"];
	$beg = date_to_timestamp($sp["begin_date"]);
	$end = date_to_timestamp($sp["end_date"]);
	$date = $Dictionnary[datex("l", $beg)]." ".datex("d/m H:i", $beg);
	$end = datex("H:i", $end);
	$content .= "<tr>";
	$content .= "<td>$date $end</td>";
	$content .= "<td>$parent_name</td>";
	$content .= '<td><a href="http://'.$Configuration->Properties["domain"].'/index.php?p=ActivityMenu&amp;a='.$ida.'&amp;b='.$idb.'">'.$name."</a></td>";
	$content .= "</tr>";
    }
    if ($content != "")
	$act .= "$intro<table style='text-align: center; width: 800px;'>$content</table>";

    if (!(send_mail($usr["mail"],
		    sprintf($Dictionnary["ManagedActivityReportTitle"],
			    datex("d/m", strtotime('next monday'))),
		    sprintf($Dictionnary["ManagedActivityReportContent"],
			    datex("d/m", strtotime('next monday')), $act)
    ))->is_error())
    {
	// Mettre à jour la misc_config pour dire qu'on a deja envoyé le rapport de cette semaine
    }
}
