<?php
//////////////////////////////////////////////
/// On effectue le ramassage des étudiants ///
//////////////////////////////////////////////

// Conserver une fenêtre assez large permet à Albedo de rattraper un passage
// manqué. Les marqueurs automatic_pickup / missing_delivery rendent le
// traitement idempotent et évitent d'évaluer deux fois la même équipe.
$begin = db_form_date(now() - 60 * 60 * 2);
$end = db_form_date(now());
$delivered_status = activity_delivery_final_status_delivered();
$missing_status = activity_delivery_final_status_missing();
$activities = db_select_all("\n  SELECT\n    activity.id as main_id,\n    activity.codename as actname,\n    user.codename as username,\n    team.id as team_id,\n    activity.type\n  FROM activity\n  LEFT JOIN activity as template ON activity.id_template = template.id\n  LEFT JOIN team ON team.id_activity = activity.id\n  LEFT JOIN user_team ON team.id = user_team.id_team\n  LEFT JOIN user ON user_team.id_user = user.id\n  WHERE activity.is_template = 0\n    AND activity.pickup_date >= '$begin'\n    AND activity.pickup_date <= '$end'\n    AND user_team.status = 2\n    AND (activity.repository_name != '' OR template.repository_name != '')\n    AND NOT EXISTS (\n      SELECT final_pickup.id\n      FROM pickedup_work as final_pickup\n      WHERE final_pickup.id_team = team.id\n        AND final_pickup.status IN ('$delivered_status', '$missing_status')\n    )\n");

foreach ($activities as $act)
{
    $team_id = (int)$act["team_id"];
    $team_leader = db_select_one("\n      user.codename,\n      user.id\n      FROM user_team LEFT JOIN user ON user_team.id_user = user.id\n      WHERE id_team = $team_id and status = 2\n    ");
    if ($team_leader == NULL)
        continue ;
    if (($activity = new FullActivity)->build($act["main_id"]) == false)
    {
        add_log(REPORT, "Albedo cannot build activity #".$act["main_id"]." for pickup.", 1);
        continue ;
    }
    global $Configuration;
    [$actConf, $allowFunc] = build_evaluator_configuration($activity, $team_leader["id"]);
    $full_activity = $activity;
    if (strlen($activity_name = $activity->repository_name) == 0)
    {
        add_log(REPORT, "Albedo pickup has no repository for {$act["actname"]}.", 1);
        continue ;
    }
    $activity_type = $act["type"];
    $ret = hand_request([
        "command" => "retrieve",
        "user" => $team_leader["codename"],
        "repo" => $activity_name,
        "alive" => true,
        "official" => true,
        "correction" => true,
        "configuration" => base64_encode($actConf),
        "allowFunc" => base64_encode($allowFunc),
        "is_exam" => ($activity_type >= 5 && $activity_type <= 9)
    ]);

    // Une panne de transport/Distrans n'est jamais imputée à l'étudiant. On ne
    // pose aucun marqueur final afin qu'un prochain passage d'Albedo réessaie.
    if (!is_array($ret))
    {
        add_log(REPORT, "Technical error while picking up {$act["actname"]} / {$team_leader["codename"]}.", 1);
        continue ;
    }

    if (!isset($ret["result"]) || $ret["result"] != "ok" || !isset($ret["content"]))
    {
        $message = activity_delivery_response_message($ret);
        if ($message == "")
            $message = "NothingTurnedIn";

        // Les seules erreurs transformées en absence de rendu sont celles qui
        // décrivent explicitement un dépôt inexistant ou vide. Toute autre
        // erreur reste technique et sera retentée.
        if (activity_delivery_is_missing_response($ret))
        {
            activity_delivery_record_final($team_id, false, $message);
            $medal_count = activity_delivery_award_no_delivery((int)$act["main_id"], $team_id);
            if (activity_delivery_no_delivery_medal_id() <= 0)
                add_log(REPORT, "The no_delivery medal does not exist; missing delivery recorded without medal.", 1);
            add_log(TRACE, "No delivery for {$act["actname"]} / {$team_leader["codename"]}; no_delivery applied to $medal_count student(s).", 1);
        }
        else
            add_log(REPORT, "Error while evaluating {$act["actname"]} / {$team_leader["codename"]}: $message", 1);
        continue ;
    }

    $content = base64_decode($ret["content"], true);
    if ($content === false || $content === "")
    {
        add_log(REPORT, "BadTarball while picking up {$act["actname"]} / {$team_leader["codename"]}.", 1);
        continue ;
    }

    // Le rapport de correction n'est pas l'archive du travail lui-même. On
    // enregistre donc un marqueur de ramassage sans repository : il suffit à
    // rendre le statut « travail livré » fiable dans les profils/ActivityMenu,
    // sans faire apparaître un faux fichier téléchargeable.
    activity_delivery_record_final($team_id, true, "Official automatic pickup");

    $corrected_students = array_keys(db_select_all("\n      user.mail\n      FROM user_team LEFT JOIN user ON user_team.id_user = user.id\n      WHERE id_team = $team_id\n        AND user.mail IS NOT NULL\n        AND user.mail != ''\n    ", "mail"));
    if (count($corrected_students))
        add_log(TRACE, print_r(send_mail(
            $corrected_students,
            $Dictionnary["EvaluationReport"]." ".$act["actname"],
            "This Evaluation has been run automatically and is official",
            NULL,
            [["report.tar.gz" => $content]],
            false
        ), true));
    if (function_exists("official_correction_mark_missing_medals_as_failed"))
        official_correction_mark_missing_medals_as_failed($full_activity, $team_id);
    $rewarded = user_money_reward_completed_activity_for_team($act["main_id"], $team_id, true);
    if ($rewarded)
        add_log(TRACE, "Activity completion money reward paid to $rewarded student(s) for activity {$act["main_id"]}, team $team_id", 1);
}
