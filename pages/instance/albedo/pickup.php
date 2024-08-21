<?php
// Pour l'instant...
// pas sur que ce qu'il y a en dessous fonctionne

//////////////////////////////////////////////
/// On effectue le ramassage des étudiants ///
//////////////////////////////////////////////

// S'interesse aux activités terminées dans les 3 dernières minutes
// Collecte les activités dont les rendus sont datés des 3 dernières minutes

$begin = db_form_date(now() - 60 * 3);
$end = db_form_date(now());
$activities = db_select_all("
  activity.id as main_id,
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
    $team_id = $act["team_id"];
    $team_leader = db_select_one("
    user.codename 
    FROM user_team LEFT JOIN user ON user_team.id_user = user.id
    WHERE id_team = $team_id and status = 2
    ");
    if (($activity = new FullActivity)->build($act["main_id"]) == false)
        not_found();
    global $Configuration;
        $path = $Configuration->ActivitiesDir($activity->codename, NULL)."activity.dab";
        $activity_dabContent = "";
        if (!file_exists($path))
            add_log(TRACE, "Failed to retrieve activity.dab in deliveries.php");
        else 
            $activity_dabContent = file_get_contents($path);
    if (strlen($activity = $activity->repository_name) == 0)
        return (new ErrorResponse("NoRepositoryConfigured"));
    $ret = hand_request([
        "command" => "retrieve",
        "user" => $team_leader,
        "repo" => $activity,
        "alive" => true,
        "official" => true,
        "correction" => true,
        "activity.dab" => $activity_dabContent
    ]);
    $filename = $Configuration->tmpFileDIR($team_leader);
    if (!$filename)
    {
        add_log(TRACE, "Failed to save temporary file for sending mail evaluation report. The directory may does not exist !");
        return (new ErrorResponse("NotFound"));
    }
    $filename = $filename."reportOf".$act["actname"]."tar.gz";
    file_put_contents($filename, $ret);
    $corrected_students = db_select_all("
    user.mail
    FROM user_team LEFT JOIN user ON user_team.id_user = user.id
    WHERE id_team = $team_id");
    send_mail($corrected_students, $Dictionnary["EvaluationReport"]." ".$act["actname"], "", NULL, [basename($filename) => $filename], false);
}
