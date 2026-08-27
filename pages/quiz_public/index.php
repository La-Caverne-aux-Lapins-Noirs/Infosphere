<?php
require_once ("tools/quiz_attempt.php");

$quiz_public_token = (string)($_GET["token"] ?? "");
$quiz_public_attempt = quiz_attempt_get_by_token($quiz_public_token, true);
$quiz_public_error = "";
$quiz_public_notice = "";
if (is_array($quiz_public_attempt) && isset($_POST["quiz_action"]) && (string)$quiz_public_attempt["status"] === "in_progress")
{
    $submit = (string)$_POST["quiz_action"] === "submit";
    $ret = quiz_attempt_save_public($quiz_public_token, $_POST["answer"] ?? [], $submit);
    if (!$ret["ok"])
    {
        $key = $ret["error"] ?? "QuizAttemptError";
        $quiz_public_error = $Dictionnary[$key] ?? $key;
        if (trim((string)($ret["details"] ?? "")) !== "")
            $quiz_public_error .= " — ".trim((string)$ret["details"]);
    }
    else
        $quiz_public_notice = $submit
            ? ($Dictionnary["QuizAttemptSubmittedNotice"] ?? "Réponses envoyées.")
            : ($Dictionnary["QuizAttemptDraftSaved"] ?? "Brouillon enregistré.");
    $quiz_public_attempt = quiz_attempt_get_by_token($quiz_public_token, true);
}

function quiz_public_h($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}

if (!is_array($quiz_public_attempt))
{
    $quiz_public_state = NULL;
    $quiz_public_model = NULL;
    $quiz_public_answers = [];
}
else
{
    $quiz_public_state = quiz_attempt_load_state($quiz_public_attempt);
    if (!$quiz_public_state["ok"])
    {
        $quiz_public_error = $Dictionnary[$quiz_public_state["error"] ?? "QuizAttemptError"] ?? ($quiz_public_state["error"] ?? "QuizAttemptError");
        $quiz_public_model = NULL;
        $quiz_public_answers = [];
    }
    else
    {
        $quiz_public_model = $quiz_public_state["model"];
        $quiz_public_answers = $quiz_public_state["answers"];
    }
}
?>
<style><?php require (__DIR__."/../questionnaire/style.css"); ?></style>
<style><?php require (__DIR__."/../quiz_attempt/style.css"); ?></style>
<div class="quiz-attempt-page quiz-public-page">
    <?php if (!is_array($quiz_public_attempt)) { ?>
        <div class="questionnaire-notice questionnaire-notice-error">
            <strong><?=$Dictionnary["QuizInvitationInvalid"] ?? "Ce lien de questionnaire est invalide, expiré ou révoqué."; ?></strong>
        </div>
    <?php } else if (is_array($quiz_public_model)) { ?>
        <header class="quiz-attempt-heading">
            <div>
                <h2><?=quiz_public_h($quiz_public_model["name"]); ?></h2>
                <?php if (trim((string)$quiz_public_model["description"]) !== "") { ?><p><?=nl2br(quiz_public_h($quiz_public_model["description"])); ?></p><?php } ?>
                <?php if (trim((string)($quiz_public_attempt["recipient_name"] ?? "")) !== "") { ?><p><strong><?=quiz_public_h($quiz_public_attempt["recipient_name"]); ?></strong></p><?php } ?>
            </div>
        </header>
        <?php if ($quiz_public_notice !== "") { ?><div class="questionnaire-notice questionnaire-notice-success"><?=quiz_public_h($quiz_public_notice); ?></div><?php } ?>
        <?php if ($quiz_public_error !== "") { ?><div class="questionnaire-notice questionnaire-notice-error"><?=nl2br(quiz_public_h($quiz_public_error)); ?></div><?php } ?>

        <?php if ((string)$quiz_public_attempt["status"] === "submitted") { ?>
            <div class="questionnaire-notice questionnaire-notice-success">
                <strong><?=$Dictionnary["QuizPublicCompleted"] ?? "Merci. Vos réponses ont été enregistrées définitivement."; ?></strong>
            </div>
        <?php } else { ?>
            <form method="post" class="quiz-attempt-form">
                <?php foreach ($quiz_public_model["groups"] as $group) { ?>
                    <fieldset class="questionnaire-preview-group">
                        <legend><?=quiz_public_h($group["label"]); ?></legend>
                        <?php foreach ($group["questions"] as $question) {
                            $key = (string)$question["key"];
                            $values = $quiz_public_answers[$key] ?? [];
                        ?>
                            <div class="questionnaire-preview-question">
                                <label class="questionnaire-preview-label"><?=quiz_public_h($question["label"]); ?><?php if ($question["required"]) { ?><span class="questionnaire-required">*</span><?php } ?></label>
                                <?=form_field_render($question, $values, [
                                    "name" => "answer[".$key."]",
                                    "attributes" => ["class" => "questionnaire-answer-control"],
                                ]); ?>
                            </div>
                        <?php } ?>
                    </fieldset>
                <?php } ?>
                <div class="quiz-attempt-actions">
                    <button type="submit" name="quiz_action" value="save" class="questionnaire-secondary"><?=$Dictionnary["QuizAttemptSaveDraft"] ?? "Enregistrer le brouillon"; ?></button>
                    <button type="submit" name="quiz_action" value="submit" class="questionnaire-primary" onclick="return confirm('<?=addslashes($Dictionnary["QuizAttemptSubmitConfirm"] ?? "Envoyer définitivement les réponses ?"); ?>');"><?=$Dictionnary["QuizAttemptSubmit"] ?? "Envoyer les réponses"; ?></button>
                </div>
            </form>
        <?php } ?>
    <?php } ?>
</div>
