<?php

require_once (__DIR__."/dabsic_dependencies.php");
require_once (__DIR__."/questionnaire.php");
require_once (__DIR__."/quiz_attempt.php");

/*
** Small shared helpers for semantic quiz entry points.
**
** There is deliberately no generic prerequisite graph here. Callers decide
** what a filename means in their own context (activity subject, support, ...).
** This file only factorises the repetitive mechanics: resolve @include trees,
** edit one entrypoint file and inspect/start a sequence of pre-access attempts.
*/

function quiz_entrypoint_all_reference_map()
{
    $map = [];
    $rows = db_select_all("
        quiz.*,
        school.codename AS school_codename
        FROM quiz
        INNER JOIN school ON school.id = quiz.id_school
        WHERE quiz.deleted IS NULL
          AND school.deleted IS NULL
    ");
    foreach ($rows as $quiz)
        $map[questionnaire_source_absolute($quiz)] = $quiz;
    return ($map);
}

function quiz_entrypoint_quizzes_from_files(array $files)
{
    $targets = quiz_entrypoint_all_reference_map();
    $quizzes = [];
    foreach ($files as $file)
    {
        if (!is_string($file) || !is_file($file))
            continue ;
        foreach (dabsic_dependency_walk($file) as $edge)
        {
            $resolved = $edge["resolved_path"] ?? NULL;
            if ($resolved === NULL || !isset($targets[$resolved]))
                continue ;
            $quiz = $targets[$resolved];
            $quizzes[(int)$quiz["id"]] = $quiz;
        }
    }
    return (array_values($quizzes));
}

function quiz_entrypoint_ensure_parent($path)
{
    $directory = dirname((string)$path);
    if (is_dir($directory))
        return (true);
    return (@mkdir($directory, 0775, true) || is_dir($directory));
}

function quiz_entrypoint_add($path, $id_quiz, $comment = "Infosphere quiz entry point")
{
    $id_quiz = (int)$id_quiz;
    $quiz = questionnaire_get($id_quiz);
    if (!is_array($quiz))
        return (["ok" => false, "error" => "QuestionnaireNotFound"]);
    if (!questionnaire_can_manage($quiz))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    if (!is_string($path) || trim($path) === "" || !quiz_entrypoint_ensure_parent($path))
        return (["ok" => false, "error" => "QuizEntrypointCannotWrite"]);

    $target = questionnaire_source_absolute($quiz);
    $existing = is_file($path) ? dabsic_dependency_walk($path) : [];
    foreach ($existing as $edge)
        if (($edge["resolved_path"] ?? NULL) === $target)
            return (["ok" => true, "changed" => false, "path" => $path]);

    $relative = dabsic_dependency_relative_path($path, $target);
    $line = "@include ".json_encode($relative, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    $prefix = is_file($path) && filesize($path) > 0
        ? "\n"
        : "' ".trim((string)$comment)."\n";
    if (@file_put_contents($path, $prefix.$line, FILE_APPEND | LOCK_EX) === false)
        return (["ok" => false, "error" => "QuizEntrypointCannotWrite"]);
    return (["ok" => true, "changed" => true, "path" => $path]);
}

function quiz_entrypoint_remove($path, $id_quiz)
{
    $id_quiz = (int)$id_quiz;
    $quiz = questionnaire_get($id_quiz);
    if (!is_array($quiz))
        return (["ok" => false, "error" => "QuestionnaireNotFound"]);
    if (!is_string($path) || !is_file($path))
        return (["ok" => true, "changed" => false]);

    $content = @file_get_contents($path);
    if ($content === false)
        return (["ok" => false, "error" => "QuizEntrypointCannotWrite"]);

    $target = questionnaire_source_absolute($quiz);
    $remove = [];
    foreach (dabsic_dependency_static_references($content) as $ref)
    {
        $resolved = dabsic_dependency_resolve($path, $ref["requested_path"] ?? "");
        if ($resolved === $target)
            $remove[] = $ref;
    }
    if (!count($remove))
        return (["ok" => true, "changed" => false]);

    usort($remove, function($a, $b) { return ((int)$b["offset"] - (int)$a["offset"]); });
    foreach ($remove as $ref)
    {
        $start = (int)$ref["offset"];
        $end = $start + (int)$ref["length"];
        while ($end < strlen($content) && ($content[$end] === ' ' || $content[$end] === "\t"))
            ++$end;
        if ($end < strlen($content) && $content[$end] === "\r")
            ++$end;
        if ($end < strlen($content) && $content[$end] === "\n")
            ++$end;
        $content = substr($content, 0, $start).substr($content, $end);
    }
    if (@file_put_contents($path, $content, LOCK_EX) === false)
        return (["ok" => false, "error" => "QuizEntrypointCannotWrite"]);
    return (["ok" => true, "changed" => true]);
}

function quiz_preaccess_context_attempt_rows($id_user, $id_quiz, $context_type, $context_reference = NULL, $id_activity = NULL)
{
    global $Database;

    $id_user = (int)$id_user;
    $id_quiz = (int)$id_quiz;
    if ($id_user <= 0 || $id_quiz <= 0)
        return ([]);
    $context_type = substr(trim((string)$context_type), 0, 64);
    if ($context_type === "")
        return ([]);
    $etype = $Database->real_escape_string($context_type);
    if ($context_reference === false)
        $reference_sql = "1 = 1";
    else if ($context_reference === NULL)
        $reference_sql = "context_reference IS NULL";
    else
        $reference_sql = "context_reference = '".$Database->real_escape_string(substr((string)$context_reference, 0, 255))."'";
    $activity_sql = $id_activity === NULL || (int)$id_activity <= 0
        ? "id_activity IS NULL"
        : "id_activity = ".(int)$id_activity;

    return (db_select_all("
        quiz_attempt.*
        FROM quiz_attempt
        WHERE id_subject = $id_user
          AND id_respondent = $id_user
          AND id_quiz = $id_quiz
          AND context_type = '$etype'
          AND $reference_sql
          AND $activity_sql
        ORDER BY id DESC
        LIMIT 20
    "));
}

function quiz_preaccess_context_quiz_status($id_user, array $quiz, $context_type, $context_reference = NULL, $id_activity = NULL)
{
    $status = [
        "quiz" => $quiz,
        "passed" => false,
        "attempt" => NULL,
        "last_attempt" => NULL,
    ];
    foreach (quiz_preaccess_context_attempt_rows($id_user, (int)$quiz["id"], $context_type, $context_reference, $id_activity) as $attempt)
    {
        if ($status["last_attempt"] === NULL)
            $status["last_attempt"] = $attempt;
        if ((string)$attempt["status"] === "in_progress" && $status["attempt"] === NULL)
            $status["attempt"] = $attempt;
        if ((string)$attempt["status"] === "submitted" && (int)($attempt["passed"] ?? 0) === 1)
        {
            $status["passed"] = true;
            break ;
        }
    }
    return ($status);
}

function quiz_preaccess_context_status(array $quizzes, $id_user, $context_type, $context_reference = NULL, $id_activity = NULL)
{
    $result = [
        "configured" => count($quizzes) > 0,
        "passed" => true,
        "quizzes" => [],
        "next" => NULL,
    ];
    foreach ($quizzes as $quiz)
    {
        $qstatus = quiz_preaccess_context_quiz_status((int)$id_user, $quiz, $context_type, $context_reference, $id_activity);
        $result["quizzes"][] = $qstatus;
        if (!$qstatus["passed"])
        {
            $result["passed"] = false;
            if ($result["next"] === NULL)
                $result["next"] = $qstatus;
        }
    }
    return ($result);
}

function quiz_preaccess_context_start(array $quizzes, $id_user, $context_type, $context_reference = NULL, $id_activity = NULL)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    $status = quiz_preaccess_context_status($quizzes, $id_user, $context_type, $context_reference, $id_activity);
    if (!$status["configured"] || $status["passed"] || $status["next"] === NULL)
        return (["ok" => false, "error" => "QuizPreaccessNothingToDo"]);

    $next = $status["next"];
    if (is_array($next["attempt"]))
        return (["ok" => true, "id" => (int)$next["attempt"]["id"], "created" => false]);

    $created = quiz_attempt_create(
        (int)$next["quiz"]["id"],
        $id_user,
        $id_user,
        $context_type,
        $context_reference,
        $id_user,
        $id_activity
    );
    if (!$created["ok"])
        return ($created);
    $created["created"] = true;
    return ($created);
}
