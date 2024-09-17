<?php
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
  user.codename as username,
  team.id as team_id
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
    user.codename,
    user.id
    FROM user_team LEFT JOIN user ON user_team.id_user = user.id
    WHERE id_team = $team_id and status = 2
    ");
    if (($activity = new FullActivity)->build($act["main_id"]) == false)
        not_found();
    global $Configuration;
    [$actConf, $allowFunc] = buildEvaluatorConfiguration($activity, $team_leader["id"]);
    if (strlen($activity = $activity->repository_name) == 0)
        return (new ErrorResponse("NoRepositoryConfigured"));
    $ret = hand_request([
        "command" => "retrieve",
        "user" => $team_leader["codename"],
        "repo" => $activity,
        "alive" => true,
        "official" => true,
        "correction" => true,
        "configuration" => base64_encode($actConf),
	"allowFunc" => base64_encode($allowFunc)
    ]);

   // On élimine les erreurs
    if (!isset($ret["result"]) || $ret["result"] != "ok")
    {
	add_log(REPORT, "Error while evaluate an activty !".
			(isset($ret["message"]) ? $ret["message"] : "NothingTurnedIn"), 1);
	continue;
    }
    
    $corrected_students = array_keys(db_select_all("
    user.mail
    FROM user_team LEFT JOIN user ON user_team.id_user = user.id
    WHERE id_team = $team_id", "mail"));
    add_log(TRACE, print_r(send_mail($corrected_students, $Dictionnary["EvaluationReport"]." ".$act["actname"],
	      "This Evaluation has been run automatically and is official", NULL,
	      [["report.tar.gz" => base64_decode($ret["content"])]], false), true));
}
