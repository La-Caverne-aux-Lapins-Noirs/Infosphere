<?php
// On prépare les environnements d'exam pour les élèves concernés

$begin = db_form_date(now());
$end = db_form_date(now() + 60 * 3);

//add_log(TRACE, "Check for deploying exam : ".$begin." to ".$end, 1);

$activities = db_select_all("
  activity.id
  FROM activity
  LEFT JOIN activity as template ON activity.id_template = template.id
  WHERE activity.is_template = 0
    AND activity.subject_appeir_date >= '$begin'
    AND activity.subject_appeir_date <= '$end'
    AND (activity.repository_name != '' OR template.repository_name != '')
    AND activity.type >= 5
    AND activity.type <= 9
");

//add_log(TRACE, "Result : ".print_r($activities, true), 1); // -> Résultat correct aux attentes

global $Configuration;

foreach ($activities as $act)
{
    $id_activity = $act["id"];
    $users = db_select_all("
    user.id,
    user.codename
    FROM activity
    LEFT JOIN team ON team.id_activity = activity.id
    LEFT JOIN user_team ON team.id = user_team.id_team
    LEFT JOIN user ON user_team.id_user = user.id
    WHERE activity.id = $id_activity
    ");

    //add_log(TRACE, "User list : ".print_r($users, true), 1); // -> Résultat correct aux attentes
    
    if (($activity = new FullActivity)->build($id_activity) == false)
	not_found();

    //    add_log(TRACE, "Sessions : ".print_r($activity->session[0]->room[array_key_first($activity->session[0]->room)]["codename"], true), true);

    $room = "";
    if (count($activity->session) > 0 && count($activity->session[0]->room) > 0 && isset($activity->session[0]->room[array_key_first($activity->session[0]->room)]["codename"]))
	$room = $activity->session[0]->room[array_key_first($activity->session[0]->room)]["codename"];

    //    add_log(TRACE, "Subject path : ".print_r($activity->current_subject, true), true);

    $activity_subject = "";
    $subject_name = "";
    if (isset($activity->current_subject) && $activity->current_subject != "")
	$activity_subject = file_get_contents($activity->current_subject);

    if (isset($activity->current_subject) && $activity->current_subject != "")
    {
	$subject_name = pathinfo($activity->current_subject);
	if (isset($subject_name['basename']))
	    $subject_name = $subject_name['basename'];
	else
	    $subject_name = "SubjectWithNoName";
    }
    
    if (strlen($repo = $activity->repository_name) == 0)
	return (new ErrorResponse("NoRepositoryConfigured"));

    $ret = hand_request([
	"command" => "deployexam",
	"users" => $users,
	"repo" => $repo,
	"subject_name" => base64_encode($subject_name),
	"subject" => base64_encode($activity_subject),
	"room" => $room
    ]);

    if (!isset($ret["result"]))
	add_log(TRACE, "Failed to load exam session", true);
    else if ($ret["result"] != "ok")
	add_log(TRACE, "Failed to deploy exam : ".$ret["message"], true);
    else
	add_log(TRACE, "Exam sessions deploy with success", true);
	
}

