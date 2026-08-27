<?php

require_once ("tools/quiz_attempt.php");

$quiz_attempt_id = (int)($_GET["a"] ?? 0);
$quiz_attempt_row = quiz_attempt_get($quiz_attempt_id);
if (!is_array($quiz_attempt_row) || !quiz_attempt_can_view($quiz_attempt_row))
{
    http_response_code(404);
    die();
}

$quiz_attempt_notice = "";
$quiz_attempt_error = "";
if (isset($_POST["quiz_action"]))
{
    $action = (string)$_POST["quiz_action"];
    if ($action === "save" || $action === "submit")
    {
        $ret = quiz_attempt_save($quiz_attempt_id, $_POST["answer"] ?? [], $action === "submit");
        if (!$ret["ok"])
        {
            $key = $ret["error"] ?? "QuizAttemptError";
            $quiz_attempt_error = $Dictionnary[$key] ?? $key;
            if (trim((string)($ret["details"] ?? "")) !== "")
                $quiz_attempt_error .= " — ".trim((string)$ret["details"]);
        }
        else if (!empty($ret["submitted"]))
        {
            $quiz_attempt_notice = $Dictionnary["QuizAttemptSubmittedNotice"] ?? "Réponses envoyées et résultat figé.";
            add_log(EDITING_OPERATION, "Quiz attempt #".$quiz_attempt_id." submitted");
        }
        else
        {
            $quiz_attempt_notice = $Dictionnary["QuizAttemptDraftSaved"] ?? "Brouillon enregistré.";
            add_log(EDITING_OPERATION, "Quiz attempt #".$quiz_attempt_id." draft saved");
        }
        $quiz_attempt_row = quiz_attempt_get($quiz_attempt_id);
    }
}

$quiz_attempt_state = quiz_attempt_load_state($quiz_attempt_row);
if (!$quiz_attempt_state["ok"])
{
    $quiz_attempt_error = $Dictionnary[$quiz_attempt_state["error"] ?? "QuizAttemptError"] ?? ($quiz_attempt_state["error"] ?? "QuizAttemptError");
    $quiz_attempt_model = questionnaire_default_model("invalid", "Questionnaire indisponible");
    $quiz_attempt_answers = [];
    $quiz_attempt_result = NULL;
}
else
{
    $quiz_attempt_model = $quiz_attempt_state["model"];
    $quiz_attempt_answers = $quiz_attempt_state["answers"];
    $quiz_attempt_result = $quiz_attempt_state["result"];
}
$quiz_attempt_can_answer_now = quiz_attempt_can_answer($quiz_attempt_row);
$quiz_attempt_show_correction = quiz_attempt_can_view_correction($quiz_attempt_row);

function quiz_attempt_page_h($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}

function quiz_attempt_page_answer_selected(array $answers, $key, $choice)
{
    return (in_array((string)$choice, $answers[$key] ?? [], true));
}
?>
<style><?php require (__DIR__."/../questionnaire/style.css"); ?></style>
<style><?php require (__DIR__."/style.css"); ?></style>

<div class="quiz-attempt-page">
    <header class="quiz-attempt-heading">
        <div>
            <h2><?=quiz_attempt_page_h($quiz_attempt_model["name"]); ?></h2>
            <?php if (trim((string)$quiz_attempt_model["description"]) !== "") { ?>
                <p><?=nl2br(quiz_attempt_page_h($quiz_attempt_model["description"])); ?></p>
            <?php } ?>
        </div>
        <div class="quiz-attempt-meta">
            <span>#<?=$quiz_attempt_id; ?></span>
            <span><?=quiz_attempt_page_h($quiz_attempt_row["status"]); ?></span>
            <code><?=quiz_attempt_page_h(substr((string)$quiz_attempt_row["snapshot_hash"], 0, 12)); ?></code>
        </div>
    </header>

    <?php if ($quiz_attempt_show_correction) { ?>
        <div class="quiz-attempt-manager-nav">
            <a class="questionnaire-secondary" href="index.php?p=QuestionnaireMenu&amp;a=<?=(int)$quiz_attempt_row["id_quiz"]; ?>">← <?=$Dictionnary["QuizAttemptBackToQuiz"] ?? "Retour au questionnaire"; ?></a>
            <span><?=$Dictionnary["QuizAttemptManagerView"] ?? "Vue responsable : la correction détaillée est visible après soumission."; ?></span>
        </div>
    <?php } ?>

    <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "matter_preaccess" && (int)($quiz_attempt_row["id_activity"] ?? 0) > 0) { ?>
        <div class="quiz-attempt-manager-nav">
            <a class="questionnaire-secondary" href="index.php?p=ModuleMenu&amp;a=<?=(int)$quiz_attempt_row["id_activity"]; ?>">← <?=$Dictionnary["QuizMatterPreaccessBack"] ?? "Retour à la matière"; ?></a>
            <span><?=$Dictionnary["QuizMatterPreaccessAttemptHelp"] ?? "Ce questionnaire conditionne l'inscription à la matière, pas sa visibilité."; ?></span>
        </div>
    <?php } ?>

    <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "preaccess" && (int)($quiz_attempt_row["id_activity"] ?? 0) > 0) { ?>
        <div class="quiz-attempt-manager-nav">
            <a class="questionnaire-secondary" href="index.php?p=ActivityMenu&amp;a=<?=(int)$quiz_attempt_row["id_activity"]; ?>">← <?=$Dictionnary["QuizPreaccessBackToActivity"] ?? "Retour à l’activité"; ?></a>
            <span><?=$Dictionnary["QuizPreaccessAttemptHelp"] ?? "Ce questionnaire conditionne l’accès au sujet de l’activité."; ?></span>
        </div>
    <?php } ?>

    <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "support_preaccess") {
        $support_preaccess_id = 0;
        if (preg_match('/^support#([0-9]+)$/D', (string)($quiz_attempt_row["context_reference"] ?? ""), $support_match))
            $support_preaccess_id = (int)$support_match[1];
    ?>
        <div class="quiz-attempt-manager-nav">
            <?php if ($support_preaccess_id > 0) { ?>
                <a class="questionnaire-secondary" href="index.php?p=SupportMenu&amp;a=<?=$support_preaccess_id; ?>">← <?=$Dictionnary["QuizSupportPreaccessBack"] ?? "Retour au support"; ?></a>
            <?php } ?>
            <span><?=$Dictionnary["QuizSupportPreaccessAttemptHelp"] ?? "Ce questionnaire conditionne l’accès au contenu du support."; ?></span>
        </div>
    <?php } ?>

    <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "support_asset_preaccess") {
        $support_asset_preaccess_id = 0;
        if (preg_match('/^support_asset#([0-9]+)$/D', (string)($quiz_attempt_row["context_reference"] ?? ""), $asset_match))
            $support_asset_preaccess_id = (int)$asset_match[1];
    ?>
        <div class="quiz-attempt-manager-nav">
            <?php if ($support_asset_preaccess_id > 0) { ?>
                <a class="questionnaire-secondary" href="index.php?p=SupportAssetMenu&amp;a=<?=$support_asset_preaccess_id; ?>">← <?=$Dictionnary["QuizSupportAssetPreaccessBack"] ?? "Retour à la ressource"; ?></a>
            <?php } ?>
            <span><?=$Dictionnary["QuizSupportAssetPreaccessAttemptHelp"] ?? "Ce questionnaire conditionne l'accès à cette ressource précise."; ?></span>
        </div>
    <?php } ?>

    <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "satisfaction" && (int)($quiz_attempt_row["id_activity"] ?? 0) > 0) { ?>
        <div class="quiz-attempt-manager-nav">
            <a class="questionnaire-secondary" href="index.php?p=ActivityMenu&amp;a=<?=(int)$quiz_attempt_row["id_activity"]; ?>">← <?=$Dictionnary["QuizSatisfactionBackToActivity"] ?? "Retour à l’activité"; ?></a>
            <span><?=$Dictionnary["QuizSatisfactionAttemptHelp"] ?? "Cette enquête recueille votre avis sur l’activité."; ?></span>
        </div>
    <?php } ?>

    <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "rubric" && (int)($quiz_attempt_row["id_activity"] ?? 0) > 0) { ?>
        <div class="quiz-attempt-manager-nav">
            <a class="questionnaire-secondary" href="index.php?p=ActivityMenu&amp;a=<?=(int)$quiz_attempt_row["id_activity"]; ?>">← <?=$Dictionnary["QuizRubricBackToActivity"] ?? "Retour à l’activité"; ?></a>
            <span><?=$Dictionnary["QuizRubricAttemptHelp"] ?? "Barème rempli par un responsable pédagogique pour l’étudiant évalué."; ?></span>
            <strong><?=$Dictionnary["QuizAttemptSubject"] ?? "Sujet"; ?> : <?=quiz_attempt_page_h(($quiz_attempt_row["subject_nickname"] ?? "") ?: ($quiz_attempt_row["subject_codename"] ?? ("#".(int)$quiz_attempt_row["id_subject"]))); ?></strong>
        </div>
    <?php } ?>

    <?php if ($quiz_attempt_notice !== "") { ?>
        <div class="questionnaire-notice questionnaire-notice-success"><?=quiz_attempt_page_h($quiz_attempt_notice); ?></div>
    <?php } ?>
    <?php if ($quiz_attempt_error !== "") { ?>
        <div class="questionnaire-notice questionnaire-notice-error"><?=nl2br(quiz_attempt_page_h($quiz_attempt_error)); ?></div>
    <?php } ?>

    <?php if ((string)$quiz_attempt_row["status"] === "in_progress") { ?>
        <?php if (!$quiz_attempt_can_answer_now) { ?>
            <div class="questionnaire-notice"><?php
                $invitation_state = quiz_attempt_invitation_state($quiz_attempt_row);
                echo ($invitation_state === "expired" || $invitation_state === "revoked")
                    ? ($Dictionnary["QuizAttemptInvitationUnavailable"] ?? "Cette invitation a expiré ou a été révoquée. Elle n’accepte plus de réponse.")
                    : ($Dictionnary["QuizAttemptReadOnlyInProgress"] ?? "Cette tentative est en cours chez son répondant. Elle est affichée ici en lecture seule.");
            ?></div>
        <?php } ?>
        <form method="post" class="quiz-attempt-form">
            <?php foreach ($quiz_attempt_model["groups"] as $group) { ?>
                <fieldset class="questionnaire-preview-group">
                    <legend><?=quiz_attempt_page_h($group["label"]); ?></legend>
                    <?php foreach ($group["questions"] as $question) {
                        $key = (string)$question["key"];
                        $values = $quiz_attempt_answers[$key] ?? [];
                    ?>
                        <div class="questionnaire-preview-question">
                            <label class="questionnaire-preview-label">
                                <?=quiz_attempt_page_h($question["label"]); ?>
                                <?php if ($question["required"]) { ?><span class="questionnaire-required">*</span><?php } ?>
                            </label>
                            <?=form_field_render($question, $values, [
                                "name" => "answer[".$key."]",
                                "disabled" => !$quiz_attempt_can_answer_now,
                                "attributes" => ["class" => "questionnaire-answer-control"],
                            ]); ?>
                        </div>
                    <?php } ?>
                </fieldset>
            <?php } ?>
            <?php if ($quiz_attempt_can_answer_now) { ?>
                <div class="quiz-attempt-actions">
                    <button type="submit" name="quiz_action" value="save" class="questionnaire-secondary"><?=$Dictionnary["QuizAttemptSaveDraft"] ?? "Enregistrer le brouillon"; ?></button>
                    <button type="submit" name="quiz_action" value="submit" class="questionnaire-primary" onclick="return confirm('<?=addslashes($Dictionnary["QuizAttemptSubmitConfirm"] ?? "Envoyer définitivement les réponses ?"); ?>');"><?=$Dictionnary["QuizAttemptSubmit"] ?? "Envoyer les réponses"; ?></button>
                </div>
            <?php } ?>
        </form>
    <?php } else { ?>
        <div class="quiz-attempt-submitted">
            <div class="questionnaire-notice questionnaire-notice-success">
                <strong><?=$Dictionnary["QuizAttemptSubmittedTitle"] ?? "Réponses envoyées"; ?></strong>
                <p><?=$Dictionnary["QuizAttemptSubmittedForRespondent"] ?? "Cette tentative est figée et ne peut plus être modifiée."; ?></p>
            </div>
            <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "preaccess") { ?>
                <div class="questionnaire-notice <?=((int)($quiz_attempt_row["passed"] ?? 0) === 1) ? "questionnaire-notice-success" : "questionnaire-notice-error"; ?>">
                    <strong><?=((int)($quiz_attempt_row["passed"] ?? 0) === 1)
                        ? ($Dictionnary["QuizPreaccessPassed"] ?? "Pré-accès réussi : le sujet est maintenant déverrouillé.")
                        : ($Dictionnary["QuizPreaccessFailed"] ?? "Le seuil de pré-accès n’est pas atteint. Une nouvelle tentative pourra être démarrée depuis l’activité."); ?></strong>
                </div>
            <?php } ?>

            <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "matter_preaccess") { ?>
                <div class="questionnaire-notice <?=((int)($quiz_attempt_row["passed"] ?? 0) === 1) ? "questionnaire-notice-success" : "questionnaire-notice-error"; ?>">
                    <strong><?=((int)($quiz_attempt_row["passed"] ?? 0) === 1)
                        ? ($Dictionnary["QuizMatterPreaccessPassed"] ?? "Pré-accès réussi : l'inscription à la matière est maintenant déverrouillée.")
                        : ($Dictionnary["QuizMatterPreaccessFailed"] ?? "Le seuil de pré-accès n'est pas atteint. Une nouvelle tentative pourra être démarrée depuis la matière."); ?></strong>
                </div>
            <?php } ?>

            <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "support_preaccess") { ?>
                <div class="questionnaire-notice <?=((int)($quiz_attempt_row["passed"] ?? 0) === 1) ? "questionnaire-notice-success" : "questionnaire-notice-error"; ?>">
                    <strong><?=((int)($quiz_attempt_row["passed"] ?? 0) === 1)
                        ? ($Dictionnary["QuizSupportPreaccessPassed"] ?? "Pré-accès réussi : le support est maintenant déverrouillé.")
                        : ($Dictionnary["QuizSupportPreaccessFailed"] ?? "Le seuil de pré-accès n’est pas atteint. Une nouvelle tentative pourra être démarrée depuis le support."); ?></strong>
                </div>
            <?php } ?>

            <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "support_asset_preaccess") { ?>
                <div class="questionnaire-notice <?=((int)($quiz_attempt_row["passed"] ?? 0) === 1) ? "questionnaire-notice-success" : "questionnaire-notice-error"; ?>">
                    <strong><?=((int)($quiz_attempt_row["passed"] ?? 0) === 1)
                        ? ($Dictionnary["QuizSupportAssetPreaccessPassed"] ?? "Pré-accès réussi : la ressource est maintenant déverrouillée.")
                        : ($Dictionnary["QuizSupportAssetPreaccessFailed"] ?? "Le seuil de pré-accès n'est pas atteint. Une nouvelle tentative pourra être démarrée depuis la ressource."); ?></strong>
                </div>
            <?php } ?>

            <?php if ((string)($quiz_attempt_row["context_type"] ?? "") === "satisfaction") { ?>
                <div class="questionnaire-notice questionnaire-notice-success">
                    <strong><?=$Dictionnary["QuizSatisfactionSubmitted"] ?? "Merci, votre avis a été enregistré."; ?></strong>
                </div>
            <?php } ?>

            <?php if ($quiz_attempt_show_correction && is_array($quiz_attempt_result)) { ?>
                <section class="quiz-attempt-result-summary">
                    <h3><?=$Dictionnary["QuizAttemptResult"] ?? "Résultat"; ?></h3>
                    <?php if ((float)$quiz_attempt_result["max_score"] > 0) { ?>
                        <div class="quiz-attempt-score">
                            <strong><?=quiz_attempt_page_h(rtrim(rtrim(number_format((float)$quiz_attempt_result["score"], 2, ".", ""), "0"), ".")); ?> / <?=quiz_attempt_page_h(rtrim(rtrim(number_format((float)$quiz_attempt_result["max_score"], 2, ".", ""), "0"), ".")); ?></strong>
                            <span><?=quiz_attempt_page_h(number_format((float)$quiz_attempt_result["success_percent"], 1, ".", "")); ?>%</span>
                            <span class="quiz-attempt-pass <?=$quiz_attempt_result["passed"] ? "yes" : "no"; ?>"><?=$quiz_attempt_result["passed"] ? ($Dictionnary["QuizAttemptPassed"] ?? "Seuil atteint") : ($Dictionnary["QuizAttemptFailed"] ?? "Seuil non atteint"); ?></span>
                        </div>
                    <?php } else { ?>
                        <p><?=$Dictionnary["QuizAttemptUngraded"] ?? "Ce questionnaire ne contient aucune question automatiquement notée."; ?></p>
                    <?php } ?>
                    <?php if (count($quiz_attempt_result["medals"])) { ?>
                        <div class="quiz-attempt-medals"><strong><?=$Dictionnary["QuestionnaireMedals"] ?? "Médailles"; ?> :</strong> <?=quiz_attempt_page_h(implode(", ", $quiz_attempt_result["medals"])); ?></div>
                    <?php } ?>
                </section>

                <?php foreach ($quiz_attempt_model["groups"] as $group) {
                    $group_result = $quiz_attempt_result["groups"][$group["key"]] ?? [];
                ?>
                    <section class="quiz-attempt-result-group">
                        <h3><?=quiz_attempt_page_h($group["label"]); ?></h3>
                        <?php if (isset($group_result["MaxScore"]) && (float)$group_result["MaxScore"] > 0) { ?>
                            <p><strong><?=quiz_attempt_page_h($group_result["Score"] ?? 0); ?> / <?=quiz_attempt_page_h($group_result["MaxScore"] ?? 0); ?></strong> — <?=quiz_attempt_page_h(number_format((float)($group_result["SuccessPercent"] ?? 0), 1, ".", "")); ?>%</p>
                        <?php } ?>
                        <div class="quiz-attempt-result-questions">
                            <?php foreach ($group["questions"] as $question) {
                                $qresult = $quiz_attempt_result["questions"][$question["key"]] ?? [];
                                $evaluated = !empty($qresult["Evaluated"]);
                                $correct = !empty($qresult["Correct"]);
                            ?>
                                <div class="quiz-attempt-result-question <?=$evaluated ? ($correct ? "correct" : "wrong") : "neutral"; ?>">
                                    <span><?=quiz_attempt_page_h($question["label"]); ?></span>
                                    <?php if ($evaluated) { ?>
                                        <strong><?=$correct ? ($Dictionnary["QuizAttemptCorrect"] ?? "Correct") : ($Dictionnary["QuizAttemptWrong"] ?? "Incorrect"); ?></strong>
                                        <small><?=quiz_attempt_page_h($qresult["Score"] ?? 0); ?> / <?=quiz_attempt_page_h($qresult["MaxScore"] ?? 0); ?></small>
                                    <?php } else { ?>
                                        <strong><?=$Dictionnary["QuizAttemptNotEvaluated"] ?? "Non évaluée"; ?></strong>
                                    <?php } ?>
                                    <?php $answer_labels = form_field_display_values($question, $quiz_attempt_answers[$question["key"]] ?? []); ?>
                                    <div class="quiz-attempt-result-answer"><small><?=$Dictionnary["QuizAttemptAnswer"] ?? "Réponse"; ?> :</small> <span><?=count($answer_labels) ? quiz_attempt_page_h(implode(", ", $answer_labels)) : "—"; ?></span></div>
                                </div>
                            <?php } ?>
                        </div>
                    </section>
                <?php } ?>
            <?php } ?>

            <?php if ($quiz_attempt_show_correction && $quiz_attempt_state["ok"]) { ?>
                <section class="quiz-attempt-artifacts">
                    <h3><?=$Dictionnary["QuizAttemptArtifacts"] ?? "Artefacts Dabsic figés"; ?></h3>
                    <details>
                        <summary><?=$Dictionnary["QuizAttemptPrivateSnapshot"] ?? "Snapshot privé effectif"; ?></summary>
                        <pre><?=quiz_attempt_page_h($quiz_attempt_state["snapshot"]["private_dabsic"] ?? ""); ?></pre>
                    </details>
                    <details>
                        <summary><?=$Dictionnary["QuizAttemptPublicSnapshot"] ?? "Projection publique"; ?></summary>
                        <pre><?=quiz_attempt_page_h($quiz_attempt_state["snapshot"]["public_dabsic"] ?? ""); ?></pre>
                    </details>
                    <details>
                        <summary><?=$Dictionnary["QuizAttemptAnswersDabsic"] ?? "Réponses Dabsic"; ?></summary>
                        <pre><?=quiz_attempt_page_h($quiz_attempt_row["answers_dabsic"] ?? ""); ?></pre>
                    </details>
                    <details>
                        <summary><?=$Dictionnary["QuizAttemptResultDabsic"] ?? "Résultat Dabsic"; ?></summary>
                        <pre><?=quiz_attempt_page_h($quiz_attempt_row["result_dabsic"] ?? ""); ?></pre>
                    </details>
                </section>
            <?php } ?>
        </div>
    <?php } ?>
</div>
