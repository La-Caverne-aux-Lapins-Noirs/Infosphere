<?php

require_once (__DIR__."/questionnaire.php");
require_once (__DIR__."/medal.php");
require_once (__DIR__."/public_invitation.php");

function quiz_attempt_current_user_id()
{
    global $User;
    return (is_array($User) && isset($User["id"]) ? (int)$User["id"] : 0);
}

function quiz_attempt_runtime_unsupported_reasons(array $model)
{
    $unsupported = [];
    foreach ($model["advanced_reasons"] ?? [] as $reason)
    {
        $reason = (string)$reason;
        if ($reason === "TopLevel.unmanaged"
            || substr($reason, -strlen(".@directive")) === ".@directive"
            || substr($reason, -strlen(".expression")) === ".expression")
            continue ;
        $unsupported[] = $reason;
    }
    return ($unsupported);
}

function quiz_attempt_public_dabsic(array $model)
{
    $model = questionnaire_normalize_model($model, $model["codename"] ?? NULL);
    $out = "' Questionnaire Infosphere - projection publique figée\n";
    $out .= "' Les réponses correctes, scores, pénalités et médailles ne sont pas exposés.\n\n";
    $out .= "[Questionnaire\n";
    $out .= "  FormatVersion = 2\n";
    $out .= "  Codename = ".questionnaire_dabsic_string($model["codename"])."\n";
    $out .= "  Name = ".questionnaire_dabsic_string($model["name"])."\n";
    $out .= "  Description = ".questionnaire_dabsic_string($model["description"])."\n";
    $out .= "]\n\n";
    $out .= "[FormGroup\n";
    foreach ($model["groups"] as $group)
    {
        $out .= "  [".$group["key"]."\n";
        $out .= "    Label = ".questionnaire_dabsic_string($group["label"])."\n";
        $out .= "    [Fields\n";
        foreach ($group["questions"] as $question)
        {
            $out .= "      [".$question["key"]."\n";
            $out .= "        Label = ".questionnaire_dabsic_string($question["label"])."\n";
            $out .= "        Type = ".questionnaire_dabsic_string($question["type"])."\n";
            $out .= "        Required = ".($question["required"] ? "true" : "false")."\n";
            $out .= questionnaire_dabsic_list("Choices", $question["choices"], "        ");
            $out .= questionnaire_dabsic_list("ChoiceValues", $question["choice_values"], "        ");
            $out .= "      ]\n";
        }
        $out .= "    ]\n";
        $out .= "  ]\n";
    }
    $out .= "]\n";
    return ($out);
}

function quiz_snapshot_get($id)
{
    $id = (int)$id;
    if ($id <= 0)
        return (NULL);
    $row = db_select_one("quiz_snapshot.* FROM quiz_snapshot WHERE quiz_snapshot.id = $id");
    return (is_array($row) ? $row : NULL);
}

function quiz_snapshot_model(array $snapshot)
{
    $parsed = questionnaire_parse_content((string)($snapshot["private_dabsic"] ?? ""), "");
    if (!$parsed["ok"])
        return ($parsed);
    return (["ok" => true, "model" => $parsed["model"]]);
}

function quiz_snapshot_for_quiz($quiz)
{
    global $Database;

    $quiz = is_array($quiz) ? $quiz : questionnaire_get((int)$quiz);
    if (!is_array($quiz))
        return (["ok" => false, "error" => "QuestionnaireNotFound"]);
    $loaded = questionnaire_load_model($quiz);
    if (!$loaded["ok"])
        return ($loaded);
    $unsupported = quiz_attempt_runtime_unsupported_reasons($loaded["model"]);
    if (count($unsupported))
        return ([
            "ok" => false,
            "error" => "QuizRuntimeUnsupportedSource",
            "details" => implode(", ", $unsupported),
        ]);

    // Snapshot the effective questionnaire understood by the runtime, after
    // mergeconf has resolved includes/expressions.  This deliberately flattens
    // factorisation for the immutable attempt while leaving the canonical source
    // untouched.
    $private = questionnaire_serialize_model($loaded["model"]);
    $public = quiz_attempt_public_dabsic($loaded["model"]);
    $hash = hash("sha256", $private);
    $id_quiz = (int)$quiz["id"];
    $escaped_hash = $Database->real_escape_string($hash);
    $existing = db_select_one("quiz_snapshot.* FROM quiz_snapshot WHERE id_quiz = $id_quiz AND effective_hash = '$escaped_hash'");
    if (is_array($existing))
        return (["ok" => true, "snapshot" => $existing, "model" => $loaded["model"]]);

    $escaped_private = $Database->real_escape_string($private);
    $escaped_public = $Database->real_escape_string($public);
    if ($Database->query("INSERT INTO quiz_snapshot (id_quiz, effective_hash, private_dabsic, public_dabsic) VALUES ($id_quiz, '$escaped_hash', '$escaped_private', '$escaped_public')") === NULL)
    {
        // A concurrent request may have inserted the same immutable snapshot.
        $existing = db_select_one("quiz_snapshot.* FROM quiz_snapshot WHERE id_quiz = $id_quiz AND effective_hash = '$escaped_hash'");
        if (!is_array($existing))
            return (["ok" => false, "error" => "QuizSnapshotCannotCreate"]);
        return (["ok" => true, "snapshot" => $existing, "model" => $loaded["model"]]);
    }
    $snapshot = quiz_snapshot_get((int)$Database->insert_id);
    if (!is_array($snapshot))
        return (["ok" => false, "error" => "QuizSnapshotCannotCreate"]);
    return (["ok" => true, "snapshot" => $snapshot, "model" => $loaded["model"]]);
}

function quiz_attempt_get($id)
{
    $id = (int)$id;
    if ($id <= 0)
        return (NULL);
    $row = db_select_one("
        quiz_attempt.*,
        quiz.id_school,
        quiz.codename AS quiz_codename,
        quiz.reference AS quiz_reference,
        quiz.deleted AS quiz_deleted,
        school.codename AS school_codename,
        respondent.codename AS respondent_codename,
        respondent.nickname AS respondent_nickname,
        subject.codename AS subject_codename,
        subject.nickname AS subject_nickname,
        quiz_snapshot.effective_hash AS snapshot_hash
        FROM quiz_attempt
        INNER JOIN quiz ON quiz.id = quiz_attempt.id_quiz
        INNER JOIN school ON school.id = quiz.id_school
        INNER JOIN quiz_snapshot ON quiz_snapshot.id = quiz_attempt.id_snapshot
        LEFT JOIN user AS respondent ON respondent.id = quiz_attempt.id_respondent
        LEFT JOIN user AS subject ON subject.id = quiz_attempt.id_subject
        WHERE quiz_attempt.id = $id
          AND school.deleted IS NULL
    ");
    return (is_array($row) ? $row : NULL);
}

function quiz_attempt_can_manage($attempt)
{
    $attempt = is_array($attempt) ? $attempt : quiz_attempt_get((int)$attempt);
    return (is_array($attempt) && questionnaire_school_can_manage((int)($attempt["id_school"] ?? 0)));
}

function quiz_attempt_can_answer($attempt)
{
    $attempt = is_array($attempt) ? $attempt : quiz_attempt_get((int)$attempt);
    return (is_array($attempt)
        && quiz_attempt_answer_window_open($attempt)
        && quiz_attempt_current_user_id() > 0
        && quiz_attempt_current_user_id() === (int)($attempt["id_respondent"] ?? 0));
}

function quiz_attempt_can_view($attempt)
{
    $attempt = is_array($attempt) ? $attempt : quiz_attempt_get((int)$attempt);
    if (!is_array($attempt))
        return (false);
    $uid = quiz_attempt_current_user_id();
    return (quiz_attempt_can_manage($attempt)
        || ($uid > 0 && $uid === (int)($attempt["id_respondent"] ?? 0)));
}

function quiz_attempt_can_view_correction($attempt)
{
    return (quiz_attempt_can_manage($attempt));
}

function quiz_attempt_create($id_quiz, $id_respondent = NULL, $id_subject = NULL, $context_type = "manual", $context_reference = NULL, $id_creator = NULL, $id_activity = NULL)
{
    global $Database;

    $quiz = questionnaire_get((int)$id_quiz);
    if (!is_array($quiz))
        return (["ok" => false, "error" => "QuestionnaireNotFound"]);
    $snapshot = quiz_snapshot_for_quiz($quiz);
    if (!$snapshot["ok"])
        return ($snapshot);

    $id_quiz = (int)$quiz["id"];
    $id_snapshot = (int)$snapshot["snapshot"]["id"];
    $respondent = $id_respondent === NULL ? "NULL" : (string)(int)$id_respondent;
    $subject = $id_subject === NULL ? "NULL" : (string)(int)$id_subject;
    $creator = $id_creator === NULL ? "NULL" : (string)(int)$id_creator;
    $activity = $id_activity === NULL || (int)$id_activity <= 0 ? "NULL" : (string)(int)$id_activity;
    $context_type = substr(trim((string)$context_type), 0, 64);
    if ($context_type === "")
        $context_type = "manual";
    $etype = $Database->real_escape_string($context_type);
    $eref = $context_reference === NULL || trim((string)$context_reference) === ""
        ? "NULL"
        : "'".$Database->real_escape_string(substr((string)$context_reference, 0, 255))."'";

    if ($Database->query("INSERT INTO quiz_attempt (id_quiz, id_snapshot, id_respondent, id_subject, id_creator, id_activity, context_type, context_reference) VALUES ($id_quiz, $id_snapshot, $respondent, $subject, $creator, $activity, '$etype', $eref)") === NULL)
        return (["ok" => false, "error" => "QuizAttemptCannotCreate"]);
    return (["ok" => true, "id" => (int)$Database->insert_id]);
}

function quiz_attempt_create_self_test($id_quiz)
{
    $uid = quiz_attempt_current_user_id();
    $quiz = questionnaire_get((int)$id_quiz);
    if ($uid <= 0 || !is_array($quiz) || !questionnaire_can_manage($quiz))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    return (quiz_attempt_create((int)$quiz["id"], $uid, $uid, "manual_test", NULL, $uid));
}

function quiz_attempt_list_for_quiz($id_quiz, $limit = 100)
{
    $id_quiz = (int)$id_quiz;
    $limit = max(1, min(500, (int)$limit));
    if ($id_quiz <= 0)
        return ([]);
    return (db_select_all("
        quiz_attempt.*,
        respondent.codename AS respondent_codename,
        respondent.nickname AS respondent_nickname,
        subject.codename AS subject_codename,
        subject.nickname AS subject_nickname,
        quiz_snapshot.effective_hash AS snapshot_hash
        FROM quiz_attempt
        INNER JOIN quiz_snapshot ON quiz_snapshot.id = quiz_attempt.id_snapshot
        LEFT JOIN user AS respondent ON respondent.id = quiz_attempt.id_respondent
        LEFT JOIN user AS subject ON subject.id = quiz_attempt.id_subject
        WHERE quiz_attempt.id_quiz = $id_quiz
        ORDER BY quiz_attempt.started_at DESC, quiz_attempt.id DESC
        LIMIT $limit
    "));
}

function quiz_attempt_list_for_respondent($id_user, $limit = 100)
{
    $id_user = (int)$id_user;
    $limit = max(1, min(500, (int)$limit));
    if ($id_user <= 0)
        return ([]);
    return (db_select_all("
        quiz_attempt.*,
        quiz.codename AS quiz_codename,
        quiz.id_school,
        school.codename AS school_codename,
        quiz_snapshot.effective_hash AS snapshot_hash
        FROM quiz_attempt
        INNER JOIN quiz ON quiz.id = quiz_attempt.id_quiz
        INNER JOIN school ON school.id = quiz.id_school
        INNER JOIN quiz_snapshot ON quiz_snapshot.id = quiz_attempt.id_snapshot
        WHERE quiz_attempt.id_respondent = $id_user
          AND school.deleted IS NULL
        ORDER BY quiz_attempt.started_at DESC, quiz_attempt.id DESC
        LIMIT $limit
    "));
}

function quiz_attempt_normalize_answers(array $model, $raw)
{
    if (!is_array($raw))
        $raw = [];
    $answers = [];
    foreach ($model["groups"] ?? [] as $group)
        foreach ($group["questions"] ?? [] as $question)
        {
            $key = (string)$question["key"];
            $values = form_field_normalize_values($question, $raw[$key] ?? NULL);
            if (count($values))
                $answers[$key] = $values;
        }
    return ($answers);
}

function quiz_attempt_answered($values)
{
    if (!is_array($values) || !count($values))
        return (false);
    foreach ($values as $value)
        if (trim((string)$value) !== "")
            return (true);
    return (false);
}

function quiz_attempt_missing_required(array $model, array $answers)
{
    $missing = [];
    foreach ($model["groups"] ?? [] as $group)
        foreach ($group["questions"] ?? [] as $question)
            if (form_field_missing_required($question, $answers[$question["key"]] ?? []))
                $missing[] = (string)$question["label"];
    return ($missing);
}

function quiz_attempt_answers_dabsic(array $answers)
{
    $out = "[QuizAnswers\n";
    $out .= "  FormatVersion = 1\n";
    $out .= "  [Answers\n";
    foreach ($answers as $key => $values)
    {
        $key = questionnaire_safe_symbol($key, "");
        if ($key === "")
            continue ;
        $out .= "    [".$key."\n";
        $out .= questionnaire_dabsic_list("Values", is_array($values) ? $values : [$values], "      ");
        $out .= "    ]\n";
    }
    $out .= "  ]\n";
    $out .= "]\n";
    return ($out);
}

function quiz_attempt_answers_from_dabsic($content)
{
    if (trim((string)$content) === "")
        return (["ok" => true, "answers" => []]);
    $parsed = questionnaire_mergeconf_scope((string)$content, "QuizAnswers", "");
    if (!$parsed["ok"])
        return ($parsed);
    $answers = [];
    $tree = $parsed["root"]["Answers"] ?? [];
    if (!is_array($tree))
        return (["ok" => false, "error" => "QuizAttemptInvalidAnswers"]);
    foreach ($tree as $key => $node)
    {
        if (!is_array($node))
            continue ;
        $answers[(string)$key] = questionnaire_string_list($node["Values"] ?? []);
    }
    return (["ok" => true, "answers" => $answers]);
}

function quiz_attempt_sorted_unique(array $values, $trim = false)
{
    $out = [];
    foreach ($values as $value)
    {
        $value = (string)$value;
        if ($trim)
            $value = trim($value);
        if (!in_array($value, $out, true))
            $out[] = $value;
    }
    sort($out, SORT_STRING);
    return ($out);
}

function quiz_attempt_question_evaluation(array $question, array $values)
{
    $correct_values = questionnaire_string_list($question["correct"] ?? []);
    $evaluated = count($correct_values) > 0;
    $answered = quiz_attempt_answered($values);
    $correct = false;
    $errors = 0;

    if ($evaluated)
    {
        if ($question["type"] === "checkbox")
        {
            $given = quiz_attempt_sorted_unique($values);
            $expected = quiz_attempt_sorted_unique($correct_values);
            $correct = ($given === $expected);
            $errors = count(array_diff($given, $expected)) + count(array_diff($expected, $given));
        }
        else if ($question["type"] === "radio")
        {
            $given = count($values) ? (string)reset($values) : "";
            $expected = count($correct_values) ? (string)reset($correct_values) : "";
            $correct = ($given !== "" && $given === $expected);
            $errors = $correct ? 0 : 1;
        }
        else
        {
            $given = count($values) ? trim((string)reset($values)) : "";
            $expected = quiz_attempt_sorted_unique($correct_values, true);
            $correct = ($given !== "" && in_array($given, $expected, true));
            $errors = $correct ? 0 : 1;
        }
    }

    $points = max(0, (float)($question["points"] ?? 0));
    $score = 0.0;
    if ($evaluated)
    {
        if (($question["policy"] ?? "exact") === "penalty")
            $score = $points - max(0, (float)($question["penalty"] ?? 0)) * $errors;
        else
            $score = $correct ? $points : 0.0;
    }
    return ([
        "answered" => $answered,
        "evaluated" => $evaluated,
        "correct" => $correct,
        "errors" => $errors,
        "score" => $score,
        "max_score" => $evaluated ? $points : 0.0,
        "medals" => ($evaluated && $correct) ? questionnaire_string_list($question["medals"] ?? []) : [],
    ]);
}

function quiz_attempt_percent($score, $max_score)
{
    if ((float)$max_score <= 0.0)
        return (NULL);
    return (max(0.0, min(100.0, ((float)$score / (float)$max_score) * 100.0)));
}

function quiz_attempt_evaluate(array $model, array $answers)
{
    $result = [
        "score" => 0.0,
        "max_score" => 0.0,
        "success_percent" => NULL,
        "passed" => false,
        "medals" => [],
        "groups" => [],
        "questions" => [],
    ];
    foreach ($model["groups"] ?? [] as $group)
    {
        $group_result = [
            "score" => 0.0,
            "max_score" => 0.0,
            "success_percent" => NULL,
            "passed" => false,
            "question_count" => count($group["questions"] ?? []),
            "evaluated_count" => 0,
            "correct_count" => 0,
            "medals" => [],
        ];
        foreach ($group["questions"] ?? [] as $question)
        {
            $qresult = quiz_attempt_question_evaluation($question, $answers[$question["key"]] ?? []);
            $result["questions"][$question["key"]] = $qresult;
            $group_result["score"] += $qresult["score"];
            $group_result["max_score"] += $qresult["max_score"];
            if ($qresult["evaluated"])
            {
                ++$group_result["evaluated_count"];
                if ($qresult["correct"])
                    ++$group_result["correct_count"];
            }
            foreach ($qresult["medals"] as $medal)
                if (!in_array($medal, $result["medals"], true))
                    $result["medals"][] = $medal;
        }
        $group_result["success_percent"] = quiz_attempt_percent($group_result["score"], $group_result["max_score"]);
        $group_result["passed"] = $group_result["success_percent"] !== NULL
            && $group_result["success_percent"] >= questionnaire_normalize_percent($group["minimum_percent"] ?? 0);
        if ($group_result["passed"])
            $group_result["medals"] = questionnaire_string_list($group["medals"] ?? []);
        foreach ($group_result["medals"] as $medal)
            if (!in_array($medal, $result["medals"], true))
                $result["medals"][] = $medal;
        $result["groups"][$group["key"]] = $group_result;
        $result["score"] += $group_result["score"];
        $result["max_score"] += $group_result["max_score"];
    }
    $result["success_percent"] = quiz_attempt_percent($result["score"], $result["max_score"]);
    $result["passed"] = $result["success_percent"] !== NULL
        && $result["success_percent"] >= questionnaire_normalize_percent($model["minimum_percent"] ?? 0);
    if ($result["passed"])
        foreach (questionnaire_string_list($model["medals"] ?? []) as $medal)
            if (!in_array($medal, $result["medals"], true))
                $result["medals"][] = $medal;
    return ($result);
}

function quiz_attempt_dabsic_nullable_number($value)
{
    return ($value === NULL ? "NULL" : questionnaire_dabsic_number($value));
}

function quiz_attempt_result_dabsic(array $result)
{
    $out = "[QuizResult\n";
    $out .= "  FormatVersion = 1\n";
    $out .= "  Score = ".questionnaire_dabsic_number($result["score"])."\n";
    $out .= "  MaxScore = ".questionnaire_dabsic_number($result["max_score"])."\n";
    $out .= "  SuccessPercent = ".quiz_attempt_dabsic_nullable_number($result["success_percent"])."\n";
    $out .= "  Passed = ".($result["passed"] ? "true" : "false")."\n";
    $out .= questionnaire_dabsic_list("Medals", $result["medals"], "  ");
    $out .= "  [Groups\n";
    foreach ($result["groups"] as $key => $group)
    {
        $out .= "    [".$key."\n";
        $out .= "      Score = ".questionnaire_dabsic_number($group["score"])."\n";
        $out .= "      MaxScore = ".questionnaire_dabsic_number($group["max_score"])."\n";
        $out .= "      SuccessPercent = ".quiz_attempt_dabsic_nullable_number($group["success_percent"])."\n";
        $out .= "      Passed = ".($group["passed"] ? "true" : "false")."\n";
        $out .= "      QuestionCount = ".(int)$group["question_count"]."\n";
        $out .= "      EvaluatedCount = ".(int)$group["evaluated_count"]."\n";
        $out .= "      CorrectCount = ".(int)$group["correct_count"]."\n";
        $out .= questionnaire_dabsic_list("Medals", $group["medals"], "      ");
        $out .= "    ]\n";
    }
    $out .= "  ]\n";
    $out .= "  [Questions\n";
    foreach ($result["questions"] as $key => $question)
    {
        $out .= "    [".$key."\n";
        $out .= "      Answered = ".($question["answered"] ? "true" : "false")."\n";
        $out .= "      Evaluated = ".($question["evaluated"] ? "true" : "false")."\n";
        $out .= "      Correct = ".($question["correct"] ? "true" : "false")."\n";
        $out .= "      Errors = ".(int)$question["errors"]."\n";
        $out .= "      Score = ".questionnaire_dabsic_number($question["score"])."\n";
        $out .= "      MaxScore = ".questionnaire_dabsic_number($question["max_score"])."\n";
        $out .= questionnaire_dabsic_list("Medals", $question["medals"], "      ");
        $out .= "    ]\n";
    }
    $out .= "  ]\n";
    $out .= "]\n";
    return ($out);
}

function quiz_attempt_result_from_dabsic($content)
{
    if (trim((string)$content) === "")
        return (["ok" => true, "result" => NULL]);
    $parsed = questionnaire_mergeconf_scope((string)$content, "QuizResult", "");
    if (!$parsed["ok"])
        return ($parsed);
    $root = $parsed["root"];
    return ([
        "ok" => true,
        "result" => [
            "score" => is_numeric($root["Score"] ?? NULL) ? (float)$root["Score"] : 0.0,
            "max_score" => is_numeric($root["MaxScore"] ?? NULL) ? (float)$root["MaxScore"] : 0.0,
            "success_percent" => is_numeric($root["SuccessPercent"] ?? NULL) ? (float)$root["SuccessPercent"] : NULL,
            "passed" => !empty($root["Passed"]),
            "medals" => questionnaire_string_list($root["Medals"] ?? []),
            "groups" => is_array($root["Groups"] ?? NULL) ? $root["Groups"] : [],
            "questions" => is_array($root["Questions"] ?? NULL) ? $root["Questions"] : [],
        ],
    ]);
}

function quiz_attempt_load_state($attempt)
{
    $attempt = is_array($attempt) ? $attempt : quiz_attempt_get((int)$attempt);
    if (!is_array($attempt))
        return (["ok" => false, "error" => "QuizAttemptNotFound"]);
    $snapshot = quiz_snapshot_get((int)$attempt["id_snapshot"]);
    if (!is_array($snapshot))
        return (["ok" => false, "error" => "QuizSnapshotNotFound"]);
    $model = quiz_snapshot_model($snapshot);
    if (!$model["ok"])
        return ($model);
    $answers = quiz_attempt_answers_from_dabsic($attempt["answers_dabsic"] ?? "");
    if (!$answers["ok"])
        return ($answers);
    $result = quiz_attempt_result_from_dabsic($attempt["result_dabsic"] ?? "");
    if (!$result["ok"])
        return ($result);
    return ([
        "ok" => true,
        "attempt" => $attempt,
        "snapshot" => $snapshot,
        "model" => $model["model"],
        "answers" => $answers["answers"],
        "result" => $result["result"],
    ]);
}


function quiz_attempt_user_team_context($id_user, $id_activity)
{
    $id_user = (int)$id_user;
    $id_activity = (int)$id_activity;
    if ($id_user <= 0 || $id_activity <= 0)
        return (["id_team" => -1, "id_user_team" => -1]);

    $row = db_select_one("
        team.id AS id_team,
        user_team.id AS id_user_team
        FROM user_team
        INNER JOIN team ON team.id = user_team.id_team
        WHERE user_team.id_user = $id_user
          AND team.id_activity = $id_activity
        ORDER BY user_team.id DESC
    ");
    return (is_array($row)
        ? ["id_team" => (int)$row["id_team"], "id_user_team" => (int)$row["id_user_team"]]
        : ["id_team" => -1, "id_user_team" => -1]);
}

function quiz_attempt_should_materialize_medals(array $attempt)
{
    return ((string)($attempt["status"] ?? "") === "submitted"
        && !in_array((string)($attempt["context_type"] ?? ""), ["manual_test", "satisfaction"], true)
        && (int)($attempt["id_subject"] ?? 0) > 0
        && (int)($attempt["id_activity"] ?? 0) > 0);
}

function quiz_attempt_materialize_medals($attempt, $result = NULL)
{
    global $Database;

    $attempt = is_array($attempt) ? $attempt : quiz_attempt_get((int)$attempt);
    if (!is_array($attempt))
        return (["ok" => false, "error" => "QuizAttemptNotFound"]);
    if (!quiz_attempt_should_materialize_medals($attempt))
        return (["ok" => true, "applied" => 0]);

    if ($result === NULL)
    {
        $parsed = quiz_attempt_result_from_dabsic($attempt["result_dabsic"] ?? "");
        if (!$parsed["ok"])
            return ($parsed);
        $result = $parsed["result"];
    }
    if (!is_array($result))
        return (["ok" => false, "error" => "QuizAttemptCannotMaterializeMedals"]);

    $id_attempt = (int)$attempt["id"];
    $id_user = (int)$attempt["id_subject"];
    $id_activity = (int)$attempt["id_activity"];
    $context = quiz_attempt_user_team_context($id_user, $id_activity);
    $applied = 0;

    foreach (questionnaire_string_list($result["medals"] ?? []) as $codename)
    {
        $resolved = medal_resolve_or_create($codename, "quiz");
        if (!$resolved["ok"])
            return ($resolved);
        $id_medal = (int)$resolved["id"];

        // The attempt is the precise provenance.  user_medal remains a journal
        // of recognition facts: never update or merge an older medal row.
        $existing = db_select_one("
            user_medal.id
            FROM user_medal
            WHERE user_medal.id_quiz_attempt = $id_attempt
              AND user_medal.id_medal = $id_medal
        ");
        if (is_array($existing))
            continue ;

        $id_team = (int)$context["id_team"];
        $id_user_team = (int)$context["id_user_team"];
        if ($Database->query("
            INSERT INTO user_medal
            (id_user, id_medal, id_activity, id_team, id_user_team, id_quiz_attempt, result, strength)
            VALUES
            ($id_user, $id_medal, $id_activity, $id_team, $id_user_team, $id_attempt, 1, 2)
        ") === NULL)
        {
            // A concurrent request may have inserted the same provenance. The
            // unique key makes that harmless if the row is now present.
            $existing = db_select_one("
                user_medal.id
                FROM user_medal
                WHERE user_medal.id_quiz_attempt = $id_attempt
                  AND user_medal.id_medal = $id_medal
            ");
            if (!is_array($existing))
                return (["ok" => false, "error" => "QuizAttemptCannotMaterializeMedals"]);
        }
        else
        {
            ++$applied;
            add_log(CREATIVE_OPERATION, "Quiz attempt #$id_attempt awarded medal '$codename' to user #$id_user from activity #$id_activity.", 1);
        }
    }
    return (["ok" => true, "applied" => $applied]);
}

function quiz_attempt_save($id_attempt, $raw_answers, $submit = false)
{
    global $Database;

    $attempt = quiz_attempt_get((int)$id_attempt);
    if (!is_array($attempt))
        return (["ok" => false, "error" => "QuizAttemptNotFound"]);
    if (!quiz_attempt_can_answer($attempt))
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    $state = quiz_attempt_load_state($attempt);
    if (!$state["ok"])
        return ($state);
    $answers = quiz_attempt_normalize_answers($state["model"], $raw_answers);
    if ($submit)
    {
        $missing = quiz_attempt_missing_required($state["model"], $answers);
        if (count($missing))
            return ([
                "ok" => false,
                "error" => "QuizAttemptMissingRequired",
                "details" => implode(", ", $missing),
            ]);
    }
    $answers_dabsic = quiz_attempt_answers_dabsic($answers);
    $escaped_answers = $Database->real_escape_string($answers_dabsic);
    $id_attempt = (int)$attempt["id"];
    if (!$submit)
    {
        if ($Database->query("UPDATE quiz_attempt SET answers_dabsic = '$escaped_answers' WHERE id = $id_attempt AND status = 'in_progress'") === NULL)
            return (["ok" => false, "error" => "QuizAttemptCannotSave"]);
        return (["ok" => true, "submitted" => false]);
    }

    $result = quiz_attempt_evaluate($state["model"], $answers);
    $result_dabsic = quiz_attempt_result_dabsic($result);
    $escaped_result = $Database->real_escape_string($result_dabsic);
    $score = questionnaire_dabsic_number($result["score"]);
    $max_score = questionnaire_dabsic_number($result["max_score"]);
    $percent = $result["success_percent"] === NULL ? "NULL" : questionnaire_dabsic_number($result["success_percent"]);
    $Database->query("START TRANSACTION");
    if ($Database->query("
        UPDATE quiz_attempt
        SET answers_dabsic = '$escaped_answers',
            result_dabsic = '$escaped_result',
            score = $score,
            max_score = $max_score,
            success_percent = $percent,
            passed = ".($result["passed"] ? "1" : "0").",
            status = 'submitted',
            submitted_at = CURRENT_TIMESTAMP,
            graded_at = CURRENT_TIMESTAMP
        WHERE id = $id_attempt AND status = 'in_progress'
    ") === NULL)
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "QuizAttemptCannotSubmit"]);
    }

    $submitted_attempt = quiz_attempt_get($id_attempt);
    if (is_array($submitted_attempt) && quiz_attempt_should_materialize_medals($submitted_attempt))
    {
        $materialized = quiz_attempt_materialize_medals($submitted_attempt, $result);
        if (!$materialized["ok"])
        {
            $Database->query("ROLLBACK");
            return ($materialized);
        }
    }
    $Database->query("COMMIT");
    return (["ok" => true, "submitted" => true, "result" => $result]);
}

function quiz_attempt_token_hash($token)
{
    return (public_invitation_token_hash($token));
}

function quiz_attempt_public_url($token)
{
    return (public_invitation_url("QuizPublic", $token));
}

function quiz_attempt_has_invitation($attempt)
{
    return (is_array($attempt) && trim((string)($attempt["access_token_hash"] ?? "")) !== "");
}

function quiz_attempt_invitation_state($attempt)
{
    if (!quiz_attempt_has_invitation($attempt))
        return ("none");
    if (public_invitation_is_revoked($attempt["revoked_at"] ?? NULL))
        return ("revoked");
    if (public_invitation_is_expired($attempt["expires_at"] ?? NULL))
        return ("expired");
    return ("active");
}

function quiz_attempt_answer_window_open($attempt)
{
    if (!is_array($attempt) || (string)($attempt["status"] ?? "") !== "in_progress")
        return (false);
    $state = quiz_attempt_invitation_state($attempt);
    return ($state === "none" || $state === "active");
}

function quiz_attempt_get_by_token($token, $allow_submitted = true)
{
    global $Database;

    if (!public_invitation_token_is_valid($token))
        return (NULL);
    $hash = $Database->real_escape_string(quiz_attempt_token_hash($token));
    $row = db_select_one("quiz_attempt.id FROM quiz_attempt WHERE access_token_hash = '$hash'");
    if (!is_array($row))
        return (NULL);
    $attempt = quiz_attempt_get((int)$row["id"]);
    if (!is_array($attempt) || quiz_attempt_invitation_state($attempt) !== "active"
        || (!$allow_submitted && (string)$attempt["status"] !== "in_progress"))
        return (NULL);
    return ($attempt);
}

function quiz_attempt_public_can_answer($attempt, $token)
{
    $resolved = quiz_attempt_get_by_token($token, false);
    return (is_array($resolved)
        && is_array($attempt)
        && (int)$resolved["id"] === (int)$attempt["id"]
        && (string)$attempt["status"] === "in_progress");
}

function quiz_attempt_invitation_title($attempt)
{
    if (!is_array($attempt))
        return ("Questionnaire");
    $snapshot = quiz_snapshot_get((int)($attempt["id_snapshot"] ?? 0));
    if (is_array($snapshot))
    {
        $model = quiz_snapshot_model($snapshot);
        if (!empty($model["ok"]) && trim((string)($model["model"]["name"] ?? "")) !== "")
            return ((string)$model["model"]["name"]);
    }
    return ((string)($attempt["quiz_codename"] ?? "Questionnaire"));
}

function quiz_attempt_send_invitation_mail($attempt, $url, $expires_days)
{
    if (!is_array($attempt))
        return (["ok" => false, "error" => "QuizAttemptNotFound"]);
    $mail = trim((string)($attempt["recipient_mail"] ?? ""));
    if ($mail === "")
        return (["ok" => false, "error" => "missing_mail"]);
    if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false)
        return (["ok" => false, "error" => "QuizInvitationInvalidMail"]);
    $name = trim((string)($attempt["recipient_name"] ?? ""));
    $title = quiz_attempt_invitation_title($attempt);
    $body = "Bonjour".($name !== "" ? " ".htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") : "").",<br /><br />";
    $body .= "Un questionnaire vous a été transmis : <strong>".htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")."</strong><br /><br />";
    $body .= "<a href=\"".htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")."\">Ouvrir le questionnaire</a><br /><br />";
    $body .= "Ce lien personnel expire dans ".(int)$expires_days." jour(s).";
    $mail_result = send_mail($mail, "Questionnaire - ".$title, $body);
    if ($mail_result->is_error())
        return (["ok" => false, "error" => strval($mail_result)]);
    return (["ok" => true]);
}

function quiz_attempt_refresh_invitation($id_attempt, $expires_days = 14, $send_mail_now = false)
{
    global $Database;

    $attempt = quiz_attempt_get((int)$id_attempt);
    if (!is_array($attempt) || !quiz_attempt_can_manage($attempt))
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if (!quiz_attempt_has_invitation($attempt))
        return (["ok" => false, "error" => "QuizInvitationNotExternal"]);
    if ((string)($attempt["status"] ?? "") !== "in_progress")
        return (["ok" => false, "error" => "QuizInvitationAlreadySubmitted"]);

    $expires_days = public_invitation_expiration_days($expires_days);
    $token = public_invitation_generate_token();
    $hash = $Database->real_escape_string(quiz_attempt_token_hash($token));
    $id = (int)$attempt["id"];
    if ($Database->query("UPDATE quiz_attempt SET
        access_token_hash = '$hash',
        expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL $expires_days DAY),
        revoked_at = NULL
        WHERE id = $id AND status = 'in_progress'") === NULL)
        return (["ok" => false, "error" => "QuizInvitationCannotRefresh"]);

    $url = quiz_attempt_public_url($token);
    $sent = false;
    $mail_error = NULL;
    if ($send_mail_now)
    {
        $attempt = quiz_attempt_get($id);
        $mail = quiz_attempt_send_invitation_mail($attempt, $url, $expires_days);
        if ($mail["ok"])
        {
            $sent = true;
            $Database->query("UPDATE quiz_attempt SET invitation_sent_at = CURRENT_TIMESTAMP WHERE id = $id");
        }
        else
            $mail_error = $mail["error"] ?? "QuizInvitationMailFailed";
    }
    return ([
        "ok" => true, "id" => $id, "token" => $token, "url" => $url,
        "sent" => $sent, "mail_error" => $mail_error,
    ]);
}

function quiz_attempt_create_external($id_quiz, $recipient_name, $recipient_mail, $expires_days = 14, $id_creator = NULL, $send_mail_now = false)
{
    global $Database;

    $quiz = questionnaire_get((int)$id_quiz);
    if (!is_array($quiz) || !questionnaire_can_manage($quiz))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    $recipient_name = trim((string)$recipient_name);
    $recipient_mail = trim((string)$recipient_mail);
    if ($recipient_mail !== "" && filter_var($recipient_mail, FILTER_VALIDATE_EMAIL) === false)
        return (["ok" => false, "error" => "QuizInvitationInvalidMail"]);
    $expires_days = public_invitation_expiration_days($expires_days);
    $created = quiz_attempt_create((int)$quiz["id"], NULL, NULL, "external", NULL, $id_creator);
    if (!$created["ok"])
        return ($created);

    $token = public_invitation_generate_token();
    $hash = $Database->real_escape_string(quiz_attempt_token_hash($token));
    $name_sql = $Database->real_escape_string(substr($recipient_name, 0, 255));
    $mail_sql = $Database->real_escape_string(substr($recipient_mail, 0, 320));
    $id = (int)$created["id"];
    if ($Database->query("UPDATE quiz_attempt
        SET access_token_hash = '$hash',
            recipient_name = '$name_sql',
            recipient_mail = '$mail_sql',
            expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL $expires_days DAY)
        WHERE id = $id") === NULL)
    {
        $Database->query("DELETE FROM quiz_attempt WHERE id = $id AND status = 'in_progress'");
        return (["ok" => false, "error" => "QuizInvitationCannotCreate"]);
    }

    $url = quiz_attempt_public_url($token);
    $sent = false;
    if ($send_mail_now && $recipient_mail === "")
        return (["ok" => true, "id" => $id, "token" => $token, "url" => $url, "sent" => false, "mail_error" => "missing_mail"]);
    if ($send_mail_now && $recipient_mail !== "")
    {
        $loaded = questionnaire_load_model($quiz);
        $title = $loaded["ok"] ? (string)$loaded["model"]["name"] : (string)$quiz["codename"];
        $body = "Bonjour".($recipient_name !== "" ? " ".$recipient_name : "").",\n\n";
        $body .= "Un questionnaire vous a été transmis : ".$title."\n\n".$url."\n\n";
        $body .= "Ce lien est personnel et expire dans ".$expires_days." jour(s).";
        $mail_result = send_mail($recipient_mail, "Questionnaire - ".$title, nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")));
        if (!$mail_result->is_error())
        {
            $sent = true;
            $Database->query("UPDATE quiz_attempt SET invitation_sent_at = CURRENT_TIMESTAMP WHERE id = $id");
        }
        else
            return (["ok" => true, "id" => $id, "token" => $token, "url" => $url, "sent" => false, "mail_error" => strval($mail_result)]);
    }
    return (["ok" => true, "id" => $id, "token" => $token, "url" => $url, "sent" => $sent]);
}

function quiz_attempt_resolve_internal_recipient($value)
{
    global $Database;
    $value = trim((string)$value);
    if ($value === "")
        return (NULL);
    $escaped = $Database->real_escape_string($value);
    return (db_select_one("id, codename, nickname, first_name, family_name, mail
        FROM user
        WHERE authority != -1
          AND (codename = '$escaped' OR mail = '$escaped')
        ORDER BY id DESC"));
}

function quiz_attempt_create_internal_assignment($id_quiz, $recipient, $id_creator = NULL, $expires_days = 14, $send_mail_now = false)
{
    global $Database;

    $quiz = questionnaire_get((int)$id_quiz);
    if (!is_array($quiz) || !questionnaire_can_manage($quiz))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    $user = quiz_attempt_resolve_internal_recipient($recipient);
    if (!is_array($user))
        return (["ok" => false, "error" => "QuizInvitationUserNotFound"]);
    $expires_days = public_invitation_expiration_days($expires_days);
    $created = quiz_attempt_create((int)$quiz["id"], (int)$user["id"], (int)$user["id"], "direct", NULL, $id_creator);
    if (!$created["ok"])
        return ($created);

    $token = public_invitation_generate_token();
    $hash = $Database->real_escape_string(quiz_attempt_token_hash($token));
    $name = trim((string)($user["nickname"] ?? ""));
    if ($name === "")
        $name = trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? ""));
    if ($name === "")
        $name = (string)$user["codename"];
    $mail = trim((string)($user["mail"] ?? ""));
    $name_sql = $Database->real_escape_string(substr($name, 0, 255));
    $mail_sql = $Database->real_escape_string(substr($mail, 0, 320));
    $id = (int)$created["id"];
    if ($Database->query("UPDATE quiz_attempt SET access_token_hash = '$hash', recipient_name = '$name_sql', recipient_mail = '$mail_sql', expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL $expires_days DAY) WHERE id = $id") === NULL)
    {
        $Database->query("DELETE FROM quiz_attempt WHERE id = $id AND status = 'in_progress'");
        return (["ok" => false, "error" => "QuizInvitationCannotCreate"]);
    }
    $url = quiz_attempt_public_url($token);
    $sent = false;
    if ($send_mail_now)
    {
        if ($mail === "")
            return (["ok" => true, "id" => $id, "user" => $user, "token" => $token, "url" => $url, "sent" => false, "mail_error" => "missing_mail"]);
        $loaded = questionnaire_load_model($quiz);
        $title = $loaded["ok"] ? (string)$loaded["model"]["name"] : (string)$quiz["codename"];
        $body = "Bonjour ".$name.",<br /><br />Un questionnaire vous a été transmis : <strong>".htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")."</strong><br /><br /><a href=\"".htmlspecialchars($url, ENT_QUOTES)."\">Ouvrir le questionnaire</a><br /><br />Ce lien personnel expire dans ".$expires_days." jour(s).";
        $mail_result = send_mail($mail, "Questionnaire - ".$title, $body);
        if ($mail_result->is_error())
            return (["ok" => true, "id" => $id, "user" => $user, "token" => $token, "url" => $url, "sent" => false, "mail_error" => strval($mail_result)]);
        $sent = true;
        $Database->query("UPDATE quiz_attempt SET invitation_sent_at = CURRENT_TIMESTAMP WHERE id = $id");
    }
    return (["ok" => true, "id" => $id, "user" => $user, "token" => $token, "url" => $url, "sent" => $sent]);
}

function quiz_attempt_revoke_invitation($id_attempt)
{
    global $Database;
    $attempt = quiz_attempt_get((int)$id_attempt);
    if (!is_array($attempt) || !quiz_attempt_can_manage($attempt))
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if (!quiz_attempt_has_invitation($attempt))
        return (["ok" => false, "error" => "QuizInvitationNotExternal"]);
    if ((string)$attempt["status"] === "submitted")
        return (["ok" => false, "error" => "QuizInvitationAlreadySubmitted"]);
    if ($Database->query("UPDATE quiz_attempt SET revoked_at = CURRENT_TIMESTAMP WHERE id = ".(int)$attempt["id"]) === NULL)
        return (["ok" => false, "error" => "QuizInvitationCannotRevoke"]);
    return (["ok" => true]);
}

function quiz_attempt_revoke_external($id_attempt)
{
    return (quiz_attempt_revoke_invitation($id_attempt));
}

function quiz_attempt_save_public($token, $raw_answers, $submit = false)
{
    global $Database;

    $attempt = quiz_attempt_get_by_token($token, false);
    if (!is_array($attempt) || !quiz_attempt_public_can_answer($attempt, $token))
        return (["ok" => false, "error" => "QuizInvitationInvalid"]);
    $state = quiz_attempt_load_state($attempt);
    if (!$state["ok"])
        return ($state);
    $answers = quiz_attempt_normalize_answers($state["model"], $raw_answers);
    if ($submit)
    {
        $missing = quiz_attempt_missing_required($state["model"], $answers);
        if (count($missing))
            return (["ok" => false, "error" => "QuizAttemptMissingRequired", "details" => implode(", ", $missing)]);
    }
    $answers_dabsic = quiz_attempt_answers_dabsic($answers);
    $escaped_answers = $Database->real_escape_string($answers_dabsic);
    $id_attempt = (int)$attempt["id"];
    if (!$submit)
    {
        if ($Database->query("UPDATE quiz_attempt SET answers_dabsic = '$escaped_answers' WHERE id = $id_attempt AND status = 'in_progress'") === NULL)
            return (["ok" => false, "error" => "QuizAttemptCannotSave"]);
        return (["ok" => true, "submitted" => false]);
    }
    $result = quiz_attempt_evaluate($state["model"], $answers);
    $result_dabsic = quiz_attempt_result_dabsic($result);
    $escaped_result = $Database->real_escape_string($result_dabsic);
    $score = questionnaire_dabsic_number($result["score"]);
    $max_score = questionnaire_dabsic_number($result["max_score"]);
    $percent = $result["success_percent"] === NULL ? "NULL" : questionnaire_dabsic_number($result["success_percent"]);
    if ($Database->query("UPDATE quiz_attempt SET
        answers_dabsic = '$escaped_answers', result_dabsic = '$escaped_result',
        score = $score, max_score = $max_score, success_percent = $percent,
        passed = ".($result["passed"] ? "1" : "0").",
        status = 'submitted', submitted_at = CURRENT_TIMESTAMP, graded_at = CURRENT_TIMESTAMP
        WHERE id = $id_attempt AND status = 'in_progress'") === NULL)
        return (["ok" => false, "error" => "QuizAttemptCannotSubmit"]);
    return (["ok" => true, "submitted" => true]);
}

function quiz_attempt_snapshot_statistics($id_quiz)
{
    $id_quiz = (int)$id_quiz;
    $groups = [];
    foreach (db_select_all("quiz_attempt.* FROM quiz_attempt
        WHERE id_quiz = $id_quiz AND status = 'submitted'
        ORDER BY submitted_at ASC, id ASC") as $attempt)
    {
        $id_snapshot = (int)$attempt["id_snapshot"];
        $context_type = (string)($attempt["context_type"] ?? "manual");
        $bucket_key = $id_snapshot."|".$context_type;
        if (!isset($groups[$bucket_key]))
        {
            $snapshot = quiz_snapshot_get($id_snapshot);
            if (!is_array($snapshot))
                continue ;
            $model = quiz_snapshot_model($snapshot);
            if (!$model["ok"])
                continue ;
            $questions = [];
            foreach ($model["model"]["groups"] ?? [] as $group)
                foreach ($group["questions"] ?? [] as $question)
                    $questions[$question["key"]] = [
                        "key" => $question["key"], "label" => $question["label"], "type" => $question["type"],
                        "choices" => $question["choices"], "choice_values" => $question["choice_values"] ?? [],
                        "answered" => 0, "counts" => [], "texts" => [],
                        "sum" => 0.0, "numeric_count" => 0,
                    ];
            $groups[$bucket_key] = [
                "snapshot" => $snapshot, "context_type" => $context_type, "model" => $model["model"], "attempts" => 0,
                "graded" => 0, "passed" => 0, "score_percent_sum" => 0.0,
                "questions" => $questions,
            ];
        }
        $bucket =& $groups[$bucket_key];
        ++$bucket["attempts"];
        if ($attempt["success_percent"] !== NULL)
        {
            ++$bucket["graded"];
            $bucket["score_percent_sum"] += (float)$attempt["success_percent"];
            if ((int)($attempt["passed"] ?? 0) === 1)
                ++$bucket["passed"];
        }
        $answers = quiz_attempt_answers_from_dabsic($attempt["answers_dabsic"] ?? "");
        if (!$answers["ok"])
            continue ;
        foreach ($bucket["questions"] as $key => &$qstat)
        {
            $values = $answers["answers"][$key] ?? [];
            if (!quiz_attempt_answered($values))
                continue ;
            ++$qstat["answered"];
            if ($qstat["type"] === "text" || $qstat["type"] === "textarea")
            {
                $text = trim((string)($values[0] ?? ""));
                if ($text !== "")
                    $qstat["texts"][] = ["attempt_id" => (int)$attempt["id"], "value" => $text];
                continue ;
            }
            foreach ($values as $value)
            {
                $value = (string)$value;
                $qstat["counts"][$value] = ($qstat["counts"][$value] ?? 0) + 1;
                if ($qstat["type"] === "scale" && is_numeric($value))
                {
                    $qstat["sum"] += (float)$value;
                    ++$qstat["numeric_count"];
                }
            }
        }
        unset($qstat, $bucket);
    }
    uasort($groups, function($a, $b) {
        return ((int)$b["snapshot"]["id"] <=> (int)$a["snapshot"]["id"]);
    });
    return (array_values($groups));
}

function quiz_attempt_csv_safe_cell($value)
{
    $value = (string)$value;
    if ($value !== "" && in_array($value[0], ["=", "+", "-", "@"], true))
        return ("'".$value);
    return ($value);
}

function quiz_attempt_csv_identity($attempt, $prefix)
{
    $nickname = trim((string)($attempt[$prefix."_nickname"] ?? ""));
    if ($nickname !== "")
        return ($nickname);
    $codename = trim((string)($attempt[$prefix."_codename"] ?? ""));
    return ($codename);
}

function quiz_attempt_export_csv($id_quiz, $id_snapshot, $context_type)
{
    global $Database;

    $id_quiz = (int)$id_quiz;
    $id_snapshot = (int)$id_snapshot;
    $quiz = questionnaire_get($id_quiz);
    if (!is_array($quiz) || !questionnaire_can_manage($quiz))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    $snapshot = quiz_snapshot_get($id_snapshot);
    if (!is_array($snapshot) || (int)$snapshot["id_quiz"] !== $id_quiz)
        return (["ok" => false, "error" => "QuizSnapshotNotFound"]);
    $parsed = quiz_snapshot_model($snapshot);
    if (!$parsed["ok"])
        return ($parsed);

    $context_type = substr(trim((string)$context_type), 0, 64);
    if ($context_type === "")
        $context_type = "manual";
    $escaped_context = $Database->real_escape_string($context_type);
    $rows = db_select_all("
        quiz_attempt.*,
        respondent.codename AS respondent_codename, respondent.nickname AS respondent_nickname,
        subject.codename AS subject_codename, subject.nickname AS subject_nickname
        FROM quiz_attempt
        LEFT JOIN user AS respondent ON respondent.id = quiz_attempt.id_respondent
        LEFT JOIN user AS subject ON subject.id = quiz_attempt.id_subject
        WHERE quiz_attempt.id_quiz = $id_quiz
          AND quiz_attempt.id_snapshot = $id_snapshot
          AND quiz_attempt.context_type = '$escaped_context'
          AND quiz_attempt.status = 'submitted'
        ORDER BY quiz_attempt.submitted_at ASC, quiz_attempt.id ASC
    ");

    $questions = [];
    foreach ($parsed["model"]["groups"] ?? [] as $group)
        foreach ($group["questions"] ?? [] as $question)
            $questions[] = $question;

    $stream = fopen("php://temp", "w+");
    if ($stream === false)
        return (["ok" => false, "error" => "QuizResultCannotExport"]);
    fwrite($stream, "\xEF\xBB\xBF");
    $header = [
        "attempt_id", "submitted_at", "respondent", "subject", "recipient_name", "recipient_mail",
        "context_type", "context_reference", "activity_id", "snapshot_hash",
        "score", "max_score", "success_percent", "passed",
    ];
    foreach ($questions as $question)
        $header[] = (string)$question["key"]." - ".(string)$question["label"];
    fputcsv($stream, $header, ";", '"', "\\");

    foreach ($rows as $attempt)
    {
        $answers = quiz_attempt_answers_from_dabsic($attempt["answers_dabsic"] ?? "");
        $answer_map = !empty($answers["ok"]) ? $answers["answers"] : [];
        $line = [
            (int)$attempt["id"], (string)$attempt["submitted_at"],
            quiz_attempt_csv_identity($attempt, "respondent"), quiz_attempt_csv_identity($attempt, "subject"),
            (string)($attempt["recipient_name"] ?? ""), (string)($attempt["recipient_mail"] ?? ""),
            (string)$attempt["context_type"], (string)($attempt["context_reference"] ?? ""),
            (string)($attempt["id_activity"] ?? ""), (string)$snapshot["effective_hash"],
            (string)($attempt["score"] ?? ""), (string)($attempt["max_score"] ?? ""),
            (string)($attempt["success_percent"] ?? ""),
            $attempt["passed"] === NULL ? "" : ((int)$attempt["passed"] ? "1" : "0"),
        ];
        foreach ($questions as $question)
        {
            $values = $answer_map[(string)$question["key"]] ?? [];
            $rendered_values = [];
            foreach (is_array($values) ? $values : [$values] as $value)
            {
                $value = (string)$value;
                $label = form_field_choice_label($question, $value);
                $rendered_values[] = ($label === $value ? $value : $label." [".$value."]");
            }
            $line[] = implode(" | ", $rendered_values);
        }
        $line = array_map("quiz_attempt_csv_safe_cell", $line);
        fputcsv($stream, $line, ";", '"', "\\");
    }
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);
    $safe_context = preg_replace('/[^A-Za-z0-9_-]+/', '-', $context_type);
    $filename = "quiz-".$quiz["codename"]."-".$safe_context."-".substr((string)$snapshot["effective_hash"], 0, 12).".csv";
    return (["ok" => true, "filename" => $filename, "content_type" => "text/csv; charset=UTF-8", "content" => $content]);
}
