<?php

function activity_automatic_evaluation_status()
{
    /*
     * Keep the historical DB status. Existing pages already hide this status
     * from normal student submissions, so the factorization does not need to
     * touch every pickedup_work reader.
     */
    return ("automatic_correction");
}

function activity_automatic_evaluation_frequency_column()
{
    static $column = false;

    if ($column !== false)
        return ($column);
    $columns = array_flip(db_select_rows("activity"));
    foreach ([
        "automatic_correction_frequency",
        "auto_correction_frequency",
        "correction_frequency",
        "automatic_evaluation_frequency",
        "auto_evaluation_frequency",
        "evaluation_frequency",
        "automatic_moulinette_frequency",
        "auto_moulinette_frequency",
        "moulinette_frequency",
        "moulinette_auto_frequency",
        "moulinette_automatic_frequency",
        "correction_frequency_minutes",
        "automatic_correction_frequency_minutes",
        "auto_correction_frequency_minutes",
    ] as $candidate)
    {
        if (isset($columns[$candidate]))
        {
            $column = $candidate;
            return ($column);
        }
    }
    $column = NULL;
    return ($column);
}

function activity_automatic_evaluation_frequency_sql($activity_alias = "activity", $template_alias = "template")
{
    $column = activity_automatic_evaluation_frequency_column();

    if ($column == NULL)
        return ("0");
    return ("COALESCE(NULLIF($activity_alias.`$column`, 0), NULLIF($template_alias.`$column`, 0), 0)");
}

function activity_automatic_evaluation_configuration($id_activity)
{
    $id_activity = (int)$id_activity;
    $frequency = activity_automatic_evaluation_frequency_sql("activity", "template");

    return (db_select_one("
        $frequency as frequency,
        COALESCE(NULLIF(activity.repository_name, ''), NULLIF(template.repository_name, ''), '') as repository_name
        FROM activity
        LEFT JOIN activity as template
          ON activity.id_template = template.id
         AND activity.template_link = 1
        WHERE activity.id = $id_activity
          AND activity.is_template = 0
          AND activity.deleted IS NULL
    "));
}

function activity_automatic_evaluation_last_run($team_id)
{
    $team_id = (int)$team_id;
    $status = activity_automatic_evaluation_status();
    $last = db_select_one("
        pickedup_date
        FROM pickedup_work
        WHERE id_team = $team_id
          AND status IN ('$status', 'automatic_evaluation')
        ORDER BY pickedup_date DESC
    ");

    if ($last == NULL || $last["pickedup_date"] == NULL)
        return (0);
    return (date_to_timestamp($last["pickedup_date"]));
}

function activity_automatic_evaluation_next_run($team_id, $frequency_minutes, $not_before = NULL)
{
    $frequency_minutes = (int)$frequency_minutes;
    $now = now();
    $last = activity_automatic_evaluation_last_run($team_id);
    $next = $last <= 0 ? $now : $last + $frequency_minutes * 60;

    if ($not_before !== NULL)
        $next = max($next, date_to_timestamp($not_before));
    if ($next < $now)
        $next = $now;
    return ($next);
}

function activity_automatic_evaluation_should_run($team_id, $frequency_minutes)
{
    $frequency_minutes = (int)$frequency_minutes;

    if ($frequency_minutes <= 0)
        return (false);
    return (activity_automatic_evaluation_next_run($team_id, $frequency_minutes) <= now());
}

function activity_automatic_evaluation_active_window($activity)
{
    $now = now();

    if ($activity->subject_appeir_date != NULL
        && date_to_timestamp($activity->subject_appeir_date) > $now)
        return ([false, "Le sujet n'est pas encore ouvert.", date_to_timestamp($activity->subject_appeir_date)]);
    if ($activity->pickup_date != NULL
        && date_to_timestamp($activity->pickup_date) <= $now)
        return ([false, "La date de ramassage est passée.", NULL]);
    if ($activity->done_date != NULL
        && date_to_timestamp($activity->done_date) <= $now)
        return ([false, "L'activité est terminée.", NULL]);
    return ([true, "", NULL]);
}

function activity_automatic_evaluation_browser_timestamp($timestamp)
{
    if ($timestamp === NULL)
        return (NULL);
    return ((int)$timestamp - (now() - time()));
}

function activity_automatic_evaluation_target_teams($activity)
{
    $teams = [];

    if ($activity->is_assistant)
        $source = $activity->team;
    else if ($activity->registered && $activity->user_team != NULL)
        $source = [$activity->user_team];
    else
        $source = [];
    foreach ($source as $team)
    {
        if (!isset($team["id"]))
            continue ;
        if (!isset($team["leader"]) || !isset($team["leader"]["id"]))
            continue ;
        $teams[] = $team;
    }
    return ($teams);
}

function activity_automatic_evaluation_status_model($activity)
{
    $cfg = activity_automatic_evaluation_configuration($activity->id);
    $model = [
        "visible" => false,
        "active" => false,
        "frequency" => 0,
        "next_timestamp" => NULL,
        "browser_next_timestamp" => NULL,
        "team_count" => 0,
        "due_count" => 0,
        "reason" => "",
        "teacher_view" => $activity->is_assistant,
    ];

    if ($cfg == NULL)
        return ($model);
    $model["frequency"] = (int)$cfg["frequency"];
    if ($model["frequency"] <= 0)
        return ($model);
    $model["visible"] = true;
    if (!strlen(trim((string)$cfg["repository_name"])))
    {
        $model["reason"] = "Aucun dépôt de ramassage n'est configuré.";
        return ($model);
    }
    [$active, $reason, $not_before] = activity_automatic_evaluation_active_window($activity);
    if (!$active)
    {
        $model["reason"] = $reason;
        if ($not_before !== NULL)
        {
            $model["next_timestamp"] = $not_before;
            $model["browser_next_timestamp"] = activity_automatic_evaluation_browser_timestamp($not_before);
        }
        return ($model);
    }

    $teams = activity_automatic_evaluation_target_teams($activity);
    $model["team_count"] = count($teams);
    if (!count($teams))
    {
        $model["reason"] = $activity->is_assistant
            ? "Aucune équipe prête à corriger automatiquement."
            : "Ton équipe n'est pas encore prête à être corrigée automatiquement.";
        return ($model);
    }

    $now = now();
    $next = NULL;
    foreach ($teams as $team)
    {
        $team_next = activity_automatic_evaluation_next_run($team["id"], $model["frequency"], $not_before);
        if ($team_next <= $now)
            $model["due_count"] += 1;
        if ($next === NULL || $team_next < $next)
            $next = $team_next;
    }
    $model["active"] = true;
    $model["next_timestamp"] = $next;
    $model["browser_next_timestamp"] = activity_automatic_evaluation_browser_timestamp($next);
    return ($model);
}


function activity_automatic_evaluation_record($team_id, $ok, $message = "")
{
    global $Database;

    $team_id = (int)$team_id;
    $status = activity_automatic_evaluation_status();
    $message = $Database->real_escape_string($message);
    $observation = $ok ? "'$message'" : "NULL";
    $errors = $ok ? "NULL" : "'$message'";
    $previous = db_select_one("
        id
        FROM pickedup_work
        WHERE id_team = $team_id
          AND status = '$status'
        ORDER BY pickedup_date DESC
    ");

    if ($previous != NULL)
    {
        $id = (int)$previous["id"];
        $Database->query("
            UPDATE pickedup_work
            SET pickedup_date = NOW(),
                observation = $observation,
                errors = $errors
            WHERE id = $id
        ");
        return ;
    }
    $Database->query("
        INSERT INTO pickedup_work
        (id_team, pickedup_date, status, observation, errors)
        VALUES ($team_id, NOW(), '$status', $observation, $errors)
    ");
}

function activity_automatic_evaluation_students_mail($team_id)
{
    $team_id = (int)$team_id;
    return (array_keys(db_select_all("
        user.mail
        FROM user_team
        LEFT JOIN user ON user_team.id_user = user.id
        WHERE user_team.id_team = $team_id
          AND user.mail IS NOT NULL
          AND user.mail != ''
    ", "mail")));
}


function activity_automatic_evaluation_run_candidates()
{
    $candidate_count = 0;
    $launched_count = 0;

    foreach (activity_automatic_evaluation_candidate_rows() as $act)
    {
        $candidate_count += 1;
        if (activity_automatic_evaluation_run($act))
            $launched_count += 1;
    }
    if ($launched_count > 0)
        add_log(TRACE, "Automatic evaluation launched for $launched_count team(s) over $candidate_count candidate(s).", 1);
}

function activity_automatic_evaluation_candidate_rows()
{
    $column = activity_automatic_evaluation_frequency_column();

    if ($column == NULL)
        return ([]);
    $frequency = activity_automatic_evaluation_frequency_sql("activity", "template");
    $now = db_form_date(now());
    return (db_select_all("
        activity.id as main_id,
        activity.codename as actname,
        activity.type as type,
        team.id as team_id,
        user.id as id_user,
        user.codename as username,
        $frequency as automatic_evaluation_frequency
        FROM activity
        LEFT JOIN activity as template
          ON activity.id_template = template.id
         AND activity.template_link = 1
        LEFT JOIN team ON team.id_activity = activity.id
        LEFT JOIN user_team ON team.id = user_team.id_team
        LEFT JOIN user ON user_team.id_user = user.id
        WHERE activity.is_template = 0
          AND activity.deleted IS NULL
          AND team.id IS NOT NULL
          AND team.closed IS NULL
          AND ($frequency) > 0
          AND (activity.repository_name != '' OR template.repository_name != '')
          AND user_team.status = 2
          AND user.id IS NOT NULL
          AND (activity.subject_appeir_date IS NULL OR activity.subject_appeir_date <= '$now')
          AND (activity.pickup_date IS NULL OR activity.pickup_date > '$now')
          AND (activity.done_date IS NULL OR activity.done_date > '$now')
    "));
}

function activity_automatic_evaluation_run($act)
{
    global $Dictionnary;

    $team_id = (int)$act["team_id"];
    $activity_id = (int)$act["main_id"];
    $frequency = (int)$act["automatic_evaluation_frequency"];

    if (!activity_automatic_evaluation_should_run($team_id, $frequency))
        return (false);
    if (($activity = new FullActivity)->build($activity_id) == false)
    {
        activity_automatic_evaluation_record($team_id, false, "Cannot build activity #$activity_id");
        return (false);
    }
    if (strlen($activity_name = $activity->repository_name) == 0)
    {
        activity_automatic_evaluation_record($team_id, false, "No repository configured");
        return (false);
    }
    if (!function_exists("build_evaluator_configuration"))
    {
        activity_automatic_evaluation_record($team_id, false, "No evaluator configuration builder");
        return (false);
    }
    [$actConf, $allowFunc] = build_evaluator_configuration($activity, (int)$act["id_user"]);
    if ($actConf === NULL || $allowFunc === NULL)
    {
        activity_automatic_evaluation_record($team_id, false, "No correction available");
        return (false);
    }

    $ret = hand_request([
        "command" => "retrieve",
        "user" => $act["username"],
        "repo" => $activity_name,
        "alive" => true,
        "official" => false,
        "correction" => true,
        "configuration" => base64_encode($actConf),
        "allowFunc" => base64_encode($allowFunc),
        "is_exam" => ((int)$act["type"] >= 5 && (int)$act["type"] <= 9)
    ]);

    if (!isset($ret["result"]) || $ret["result"] != "ok" || !isset($ret["content"]))
    {
        $msg = isset($ret["message"]) ? $ret["message"] : "NothingTurnedIn";
        activity_automatic_evaluation_record($team_id, false, $msg);
        add_log(REPORT, "Automatic evaluation failed for {$act["actname"]} / {$act["username"]}: $msg", 1);
        return (false);
    }
    if (($content = base64_decode($ret["content"])) == NULL)
    {
        activity_automatic_evaluation_record($team_id, false, "BadTarball");
        add_log(REPORT, "Automatic evaluation failed for {$act["actname"]} / {$act["username"]}: BadTarball", 1);
        return (false);
    }

    activity_automatic_evaluation_record($team_id, true, "Automatic non-official evaluation");
    $students_mail = activity_automatic_evaluation_students_mail($team_id);
    if (count($students_mail))
    {
        send_mail(
            $students_mail,
            $Dictionnary["EvaluationReport"]." ".$act["actname"],
            "This evaluation has been run automatically and is not official.",
            NULL,
            [["report.tar.gz" => $content]],
            false
        );
    }
    return (true);
}
