<?php

require_once (__DIR__."/quiz_entrypoint.php");

/*
** Activity quiz entry points
** --------------------------
** The filename is the semantic binding. A quiz definition remains a reusable
** resource under dres/quiz; preaccess.dab says that the activity uses it as a
** prerequisite. No parallel activity_quiz table is required.
*/

function activity_quiz_entrypoint_name($kind)
{
    $kind = strtolower(trim((string)$kind));
    return (in_array($kind, ["preaccess", "satisfaction", "rubric"], true) ? $kind : NULL);
}

function activity_quiz_entrypoint_candidates($activity, $kind = "preaccess")
{
    global $Configuration;
    global $Language;

    $kind = activity_quiz_entrypoint_name($kind);
    if ($kind === NULL || !is_object($activity) || !isset($activity->codename))
        return ([]);

    $name = $kind.".dab";
    $candidates = [];
    $language = preg_match('/^[A-Za-z0-9_-]+$/D', (string)$Language) ? (string)$Language : "";

    if ($language !== "")
        $candidates[] = $Configuration->ActivitiesDir($activity->codename, $language).$name;
    $candidates[] = $Configuration->ActivitiesDir($activity->codename, "").$name;

    if (!empty($activity->template_link)
        && isset($activity->template_codename)
        && trim((string)$activity->template_codename) !== "")
    {
        if ($language !== "")
            $candidates[] = $Configuration->ActivitiesDir($activity->template_codename, $language).$name;
        $candidates[] = $Configuration->ActivitiesDir($activity->template_codename, "").$name;
    }
    return (array_values(array_unique($candidates)));
}

function activity_quiz_entrypoint_files($activity, $kind = "preaccess")
{
    $files = [];
    foreach (activity_quiz_entrypoint_candidates($activity, $kind) as $candidate)
        if (is_file($candidate))
        {
            $resolved = realpath($candidate) ?: $candidate;
            $files[$resolved] = $resolved;
        }
    return (array_values($files));
}

function activity_quiz_entrypoint_file($activity, $kind = "preaccess")
{
    $files = activity_quiz_entrypoint_files($activity, $kind);
    return (count($files) ? $files[0] : NULL);
}

function activity_quiz_all_reference_map()
{
    return (quiz_entrypoint_all_reference_map());
}

function activity_quiz_entrypoint_quizzes($activity, $kind = "preaccess")
{
    return (quiz_entrypoint_quizzes_from_files(activity_quiz_entrypoint_files($activity, $kind)));
}

function activity_quiz_managed_entrypoint_path($activity, $kind = "preaccess")
{
    global $Configuration;

    $kind = activity_quiz_entrypoint_name($kind);
    if ($kind === NULL || !is_object($activity) || !isset($activity->codename))
        return (NULL);
    return ($Configuration->ActivitiesDir($activity->codename, "").$kind.".dab");
}

function activity_quiz_ensure_entrypoint_parent($path)
{
    return (quiz_entrypoint_ensure_parent($path));
}

function activity_quiz_add($activity, $id_quiz, $kind = "preaccess")
{
    $path = activity_quiz_managed_entrypoint_path($activity, $kind);
    if ($path === NULL)
        return (["ok" => false, "error" => "QuizActivityEntrypointCannotWrite"]);
    $ret = quiz_entrypoint_add($path, (int)$id_quiz, "Infosphere activity quiz entry point");
    if (!$ret["ok"] && ($ret["error"] ?? "") === "QuizEntrypointCannotWrite")
        $ret["error"] = "QuizActivityEntrypointCannotWrite";
    return ($ret);
}

function activity_quiz_remove($activity, $id_quiz, $kind = "preaccess")
{
    $path = activity_quiz_managed_entrypoint_path($activity, $kind);
    if ($path === NULL)
        return (["ok" => true, "changed" => false]);
    $ret = quiz_entrypoint_remove($path, (int)$id_quiz);
    if (!$ret["ok"] && ($ret["error"] ?? "") === "QuizEntrypointCannotWrite")
        $ret["error"] = "QuizActivityEntrypointCannotWrite";
    return ($ret);
}

function activity_preaccess_attempt_rows($id_activity, $id_user, $id_quiz)
{
    return (quiz_preaccess_context_attempt_rows(
        (int)$id_user,
        (int)$id_quiz,
        "preaccess",
        false,
        (int)$id_activity
    ));
}

function activity_preaccess_quiz_status($activity, $id_user, array $quiz)
{
    return (quiz_preaccess_context_quiz_status(
        (int)$id_user,
        $quiz,
        "preaccess",
        false,
        (int)$activity->id
    ));
}

function activity_preaccess_status($activity, $id_user)
{
    return (quiz_preaccess_context_status(
        activity_quiz_entrypoint_quizzes($activity, "preaccess"),
        (int)$id_user,
        "preaccess",
        false,
        (int)$activity->id
    ));
}

function activity_preaccess_can_read_subject($activity, $id_user)
{
    if (is_object($activity) && ($activity->parent_activity == -1 || $activity->parent_activity === NULL))
        return (true); // On a root matter, preaccess.dab gates enrollment, not a subject.
    $status = activity_preaccess_status($activity, (int)$id_user);
    return (!$status["configured"] || $status["passed"]);
}

function activity_preaccess_start($activity, $id_user)
{
    $id_user = (int)$id_user;
    if (!is_object($activity) || (int)($activity->id ?? 0) <= 0 || $id_user <= 0)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if ($activity->parent_activity == -1 || $activity->parent_activity === NULL)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if (!$activity->registered || (int)$activity->leader <= 0)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);

    $quizzes = activity_quiz_entrypoint_quizzes($activity, "preaccess");
    $status = quiz_preaccess_context_status($quizzes, $id_user, "preaccess", false, (int)$activity->id);
    if (!$status["configured"] || $status["passed"] || $status["next"] === NULL)
        return (["ok" => false, "error" => "QuizPreaccessNothingToDo"]);
    if (is_array($status["next"]["attempt"]))
        return (["ok" => true, "id" => (int)$status["next"]["attempt"]["id"], "created" => false]);
    $created = quiz_attempt_create(
        (int)$status["next"]["quiz"]["id"], $id_user, $id_user, "preaccess",
        (string)$activity->codename, $id_user, (int)$activity->id
    );
    if (!$created["ok"])
        return ($created);
    $created["created"] = true;
    return ($created);
}



function matter_preaccess_status($activity, $id_user)
{
    if (!is_object($activity) || (int)($activity->id ?? 0) <= 0)
        return (["configured" => false, "passed" => true, "quizzes" => [], "next" => NULL]);
    return (quiz_preaccess_context_status(
        activity_quiz_entrypoint_quizzes($activity, "preaccess"),
        (int)$id_user,
        "matter_preaccess",
        (string)$activity->codename,
        (int)$activity->id
    ));
}

function matter_preaccess_can_register($activity, $id_user)
{
    $status = matter_preaccess_status($activity, (int)$id_user);
    return (!$status["configured"] || $status["passed"]);
}

function matter_preaccess_start($activity, $id_user)
{
    $id_user = (int)$id_user;
    if (!is_object($activity) || (int)($activity->id ?? 0) <= 0 || $id_user <= 0)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if ($activity->parent_activity != -1 && $activity->parent_activity !== NULL)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if ($activity->registered || !$activity->can_subscribe)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if (!period($activity->registration_date, $activity->close_date))
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    return (quiz_preaccess_context_start(
        activity_quiz_entrypoint_quizzes($activity, "preaccess"),
        $id_user,
        "matter_preaccess",
        (string)$activity->codename,
        (int)$activity->id
    ));
}

function activity_satisfaction_attempt_rows($id_activity, $id_user, $id_quiz)
{
    $id_activity = (int)$id_activity;
    $id_user = (int)$id_user;
    $id_quiz = (int)$id_quiz;
    return (db_select_all("
        quiz_attempt.*
        FROM quiz_attempt
        WHERE id_activity = $id_activity
          AND id_subject = $id_user
          AND id_respondent = $id_user
          AND id_quiz = $id_quiz
          AND context_type = 'satisfaction'
        ORDER BY id DESC
        LIMIT 20
    "));
}

function activity_satisfaction_quiz_status($activity, $id_user, array $quiz)
{
    $status = [
        "quiz" => $quiz,
        "completed" => false,
        "attempt" => NULL,
        "last_attempt" => NULL,
    ];
    foreach (activity_satisfaction_attempt_rows((int)$activity->id, (int)$id_user, (int)$quiz["id"]) as $attempt)
    {
        if ($status["last_attempt"] === NULL)
            $status["last_attempt"] = $attempt;
        if ((string)$attempt["status"] === "submitted")
        {
            $status["completed"] = true;
            break ;
        }
        if ((string)$attempt["status"] === "in_progress" && $status["attempt"] === NULL)
            $status["attempt"] = $attempt;
    }
    return ($status);
}

function activity_satisfaction_status($activity, $id_user)
{
    $id_user = (int)$id_user;
    $quizzes = activity_quiz_entrypoint_quizzes($activity, "satisfaction");
    $result = [
        "configured" => count($quizzes) > 0,
        "completed" => true,
        "quizzes" => [],
        "next" => NULL,
    ];
    foreach ($quizzes as $quiz)
    {
        $qstatus = activity_satisfaction_quiz_status($activity, $id_user, $quiz);
        $result["quizzes"][] = $qstatus;
        if (!$qstatus["completed"])
        {
            $result["completed"] = false;
            if ($result["next"] === NULL)
                $result["next"] = $qstatus;
        }
    }
    return ($result);
}

function activity_satisfaction_start($activity, $id_user)
{
    $id_user = (int)$id_user;
    if (!is_object($activity) || (int)($activity->id ?? 0) <= 0 || $id_user <= 0)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if (!$activity->registered || (int)$activity->leader <= 0)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);

    $status = activity_satisfaction_status($activity, $id_user);
    if (!$status["configured"] || $status["completed"] || $status["next"] === NULL)
        return (["ok" => false, "error" => "QuizSatisfactionNothingToDo"]);

    $next = $status["next"];
    if (is_array($next["attempt"]))
        return (["ok" => true, "id" => (int)$next["attempt"]["id"], "created" => false]);

    $quiz = $next["quiz"];
    $created = quiz_attempt_create(
        (int)$quiz["id"],
        $id_user,
        $id_user,
        "satisfaction",
        (string)$activity->codename,
        $id_user,
        (int)$activity->id
    );
    if (!$created["ok"])
        return ($created);
    $created["created"] = true;
    return ($created);
}

function activity_satisfaction_scale_value(array $question, array $values)
{
    if (($question["type"] ?? "") !== "scale" || !count($values))
        return (NULL);
    $numeric = [];
    foreach ($question["choices"] ?? [] as $choice)
        if (is_numeric($choice))
            $numeric[] = (float)$choice;
    if (count($numeric) < 2)
        return (NULL);
    $value = reset($values);
    if (!is_numeric($value))
        return (NULL);
    $min = min($numeric);
    $max = max($numeric);
    $value = (float)$value;
    if ($max <= $min || $value < $min || $value > $max)
        return (NULL);
    return ([
        "value" => $value,
        "min" => $min,
        "max" => $max,
        "percent" => (($value - $min) / ($max - $min)) * 100.0,
    ]);
}

function activity_satisfaction_statistics($activity)
{
    if (!is_object($activity) || (int)($activity->id ?? 0) <= 0)
        return (["respondents" => 0, "answers" => 0, "average" => NULL, "percent" => NULL, "questions" => []]);

    $id_activity = (int)$activity->id;
    $rows = db_select_all("
        quiz_attempt.*
        FROM quiz_attempt
        WHERE id_activity = $id_activity
          AND context_type = 'satisfaction'
          AND status = 'submitted'
        ORDER BY id DESC
    ");
    $seen = [];
    $respondents = [];
    $values = [];
    $questions = [];

    foreach ($rows as $attempt)
    {
        $identity = (int)($attempt["id_subject"] ?? 0).":".(int)$attempt["id_quiz"];
        if (isset($seen[$identity]))
            continue ;
        $seen[$identity] = true;
        if ((int)($attempt["id_subject"] ?? 0) > 0)
            $respondents[(int)$attempt["id_subject"]] = true;

        $state = quiz_attempt_load_state($attempt);
        if (!$state["ok"])
            continue ;
        foreach ($state["model"]["groups"] ?? [] as $group)
            foreach ($group["questions"] ?? [] as $question)
            {
                $metric = activity_satisfaction_scale_value(
                    $question,
                    $state["answers"][$question["key"]] ?? []
                );
                if ($metric === NULL)
                    continue ;
                $values[] = $metric;
                $key = (int)$attempt["id_quiz"].":".$question["key"];
                if (!isset($questions[$key]))
                    $questions[$key] = [
                        "label" => (string)$question["label"],
                        "count" => 0,
                        "sum" => 0.0,
                        "percent_sum" => 0.0,
                        "min" => $metric["min"],
                        "max" => $metric["max"],
                    ];
                ++$questions[$key]["count"];
                $questions[$key]["sum"] += $metric["value"];
                $questions[$key]["percent_sum"] += $metric["percent"];
            }
    }

    foreach ($questions as &$question)
    {
        $count = max(1, (int)$question["count"]);
        $question["average"] = $question["sum"] / $count;
        $question["percent"] = $question["percent_sum"] / $count;
        unset($question["sum"], $question["percent_sum"]);
    }
    unset($question);

    $average = NULL;
    $percent = NULL;
    if (count($values))
    {
        $sum = 0.0;
        $psum = 0.0;
        foreach ($values as $metric)
        {
            $sum += $metric["value"];
            $psum += $metric["percent"];
        }
        $average = $sum / count($values);
        $percent = $psum / count($values);
    }

    return ([
        "respondents" => count($respondents),
        "answers" => count($values),
        "average" => $average,
        "percent" => $percent,
        "questions" => array_values($questions),
    ]);
}

function activity_quiz_managed_quizzes($activity, $kind = "preaccess")
{
    $path = activity_quiz_managed_entrypoint_path($activity, $kind);
    if ($path === NULL || !is_file($path))
        return ([]);
    $targets = activity_quiz_all_reference_map();
    $quizzes = [];
    foreach (dabsic_dependency_walk($path) as $edge)
    {
        $resolved = $edge["resolved_path"] ?? NULL;
        if ($resolved !== NULL && isset($targets[$resolved]))
            $quizzes[(int)$targets[$resolved]["id"]] = $targets[$resolved];
    }
    return (array_values($quizzes));
}


function activity_rubric_subjects($activity)
{
    if (!is_object($activity) || (int)($activity->id ?? 0) <= 0)
        return ([]);
    $id_activity = (int)$activity->id;
    return (db_select_all("\n        user.id,\n        user.codename,\n        user.nickname,\n        team.id AS id_team,\n        user_team.id AS id_user_team\n        FROM team\n        INNER JOIN user_team ON user_team.id_team = team.id\n        INNER JOIN user ON user.id = user_team.id_user\n        WHERE team.id_activity = $id_activity\n        GROUP BY user.id\n        ORDER BY user.codename ASC\n    "));
}

function activity_rubric_attempt_rows($id_activity, $id_subject, $id_quiz)
{
    $id_activity = (int)$id_activity;
    $id_subject = (int)$id_subject;
    $id_quiz = (int)$id_quiz;
    return (db_select_all("\n        quiz_attempt.*\n        FROM quiz_attempt\n        WHERE id_activity = $id_activity\n          AND id_subject = $id_subject\n          AND id_quiz = $id_quiz\n          AND context_type = 'rubric'\n        ORDER BY id DESC\n        LIMIT 50\n    "));
}

function activity_rubric_subject_status($activity, $id_subject, array $quiz, $id_respondent = NULL)
{
    $status = [
        "quiz" => $quiz,
        "attempt" => NULL,
        "last_attempt" => NULL,
        "submitted" => [],
    ];
    foreach (activity_rubric_attempt_rows((int)$activity->id, (int)$id_subject, (int)$quiz["id"]) as $attempt)
    {
        if ($status["last_attempt"] === NULL)
            $status["last_attempt"] = $attempt;
        if ((string)$attempt["status"] === "submitted")
            $status["submitted"][] = $attempt;
        else if ((string)$attempt["status"] === "in_progress"
            && ($id_respondent === NULL || (int)$attempt["id_respondent"] === (int)$id_respondent)
            && $status["attempt"] === NULL)
            $status["attempt"] = $attempt;
    }
    return ($status);
}

function activity_rubric_start($activity, $id_respondent, $id_subject, $id_quiz)
{
    $id_respondent = (int)$id_respondent;
    $id_subject = (int)$id_subject;
    $id_quiz = (int)$id_quiz;
    if (!is_object($activity) || (int)($activity->id ?? 0) <= 0
        || $id_respondent <= 0 || $id_subject <= 0 || $id_quiz <= 0)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if (empty($activity->is_assistant) && empty($activity->is_teacher) && empty($activity->is_director))
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);

    $allowed_quiz = false;
    foreach (activity_quiz_entrypoint_quizzes($activity, "rubric") as $quiz)
        if ((int)$quiz["id"] === $id_quiz)
        {
            $allowed_quiz = $quiz;
            break ;
        }
    if ($allowed_quiz === false)
        return (["ok" => false, "error" => "QuizRubricNotConfigured"]);

    $subject = db_select_one("\n        user.id\n        FROM user\n        INNER JOIN user_team ON user_team.id_user = user.id\n        INNER JOIN team ON team.id = user_team.id_team\n        WHERE user.id = $id_subject\n          AND team.id_activity = ".(int)$activity->id."\n    ");
    if (!is_array($subject))
        return (["ok" => false, "error" => "QuizRubricInvalidSubject"]);

    $status = activity_rubric_subject_status($activity, $id_subject, $allowed_quiz, $id_respondent);
    if (is_array($status["attempt"]))
        return (["ok" => true, "id" => (int)$status["attempt"]["id"], "created" => false]);

    $created = quiz_attempt_create(
        $id_quiz,
        $id_respondent,
        $id_subject,
        "rubric",
        (string)$activity->codename,
        $id_respondent,
        (int)$activity->id
    );
    if (!$created["ok"])
        return ($created);
    $created["created"] = true;
    return ($created);
}
