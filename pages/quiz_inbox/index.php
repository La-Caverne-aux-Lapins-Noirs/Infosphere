<?php
require_once ("tools/quiz_attempt.php");

$quiz_inbox_user_id = quiz_attempt_current_user_id();
$quiz_inbox_rows = $quiz_inbox_user_id > 0 ? quiz_attempt_list_for_respondent($quiz_inbox_user_id, 200) : [];
$quiz_inbox_pending = [];
$quiz_inbox_history = [];
foreach ($quiz_inbox_rows as $row)
{
    if ((string)($row["context_type"] ?? "") === "manual_test")
        continue ;
    if ((string)($row["status"] ?? "") === "in_progress")
        $quiz_inbox_pending[] = $row;
    else
        $quiz_inbox_history[] = $row;
}
function quiz_inbox_h($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}
function quiz_inbox_title($attempt)
{
    $snapshot = quiz_snapshot_get((int)($attempt["id_snapshot"] ?? 0));
    if (is_array($snapshot))
    {
        $model = quiz_snapshot_model($snapshot);
        if (!empty($model["ok"]) && trim((string)($model["model"]["name"] ?? "")) !== "")
            return ((string)$model["model"]["name"]);
    }
    return ((string)($attempt["quiz_codename"] ?? "Questionnaire"));
}
function quiz_inbox_context_label($context)
{
    global $Dictionnary;
    $labels = [
        "direct" => $Dictionnary["QuizInboxContextDirect"] ?? "Attribution directe",
        "preaccess" => $Dictionnary["QuizInboxContextPreaccess"] ?? "Pré-accès activité",
        "matter_preaccess" => $Dictionnary["QuizInboxContextMatterPreaccess"] ?? "Pré-accès matière",
        "support_preaccess" => $Dictionnary["QuizInboxContextSupportPreaccess"] ?? "Pré-accès chapitre",
        "support_asset_preaccess" => $Dictionnary["QuizInboxContextSupportAssetPreaccess"] ?? "Pré-accès ressource",
        "satisfaction" => $Dictionnary["QuizInboxContextSatisfaction"] ?? "Satisfaction",
        "rubric" => $Dictionnary["QuizInboxContextRubric"] ?? "Barème",
        "external" => $Dictionnary["QuizInboxContextExternal"] ?? "Invitation externe",
    ];
    return ($labels[(string)$context] ?? (string)$context);
}
?>
<style><?php require (__DIR__."/style.css"); ?></style>
<div class="quiz-inbox-page">
    <header class="quiz-inbox-heading">
        <div><h2><?=$Dictionnary["QuizInboxTitle"] ?? "Mes questionnaires"; ?></h2><p><?=$Dictionnary["QuizInboxHelp"] ?? "Questionnaires qui vous ont été attribués ou que vous avez commencés."; ?></p></div>
        <span class="quiz-inbox-count"><?=count($quiz_inbox_pending); ?> <?=$Dictionnary["QuizInboxPending"] ?? "en attente"; ?></span>
    </header>

    <section class="quiz-inbox-section">
        <h3><?=$Dictionnary["QuizInboxToDo"] ?? "À faire"; ?></h3>
        <div class="quiz-inbox-list">
        <?php foreach ($quiz_inbox_pending as $attempt) {
            $state = quiz_attempt_invitation_state($attempt);
            $available = quiz_attempt_answer_window_open($attempt);
        ?>
            <article class="quiz-inbox-row <?=$available ? "available" : "unavailable"; ?>">
                <div class="quiz-inbox-main">
                    <strong><?=quiz_inbox_h(quiz_inbox_title($attempt)); ?></strong>
                    <span><?=quiz_inbox_h(quiz_inbox_context_label($attempt["context_type"] ?? "manual")); ?></span>
                    <small>#<?=(int)$attempt["id"]; ?> · <?=quiz_inbox_h($attempt["school_codename"] ?? ""); ?> · <?=quiz_inbox_h($attempt["started_at"] ?? ""); ?></small>
                </div>
                <div class="quiz-inbox-status">
                    <?php if ($state === "revoked") { ?><span class="revoked"><?=$Dictionnary["QuizInvitationRevoked"] ?? "Révoquée"; ?></span><?php }
                    else if ($state === "expired") { ?><span class="expired"><?=$Dictionnary["QuizInvitationExpired"] ?? "Expirée"; ?></span><?php }
                    else { ?><span class="progress"><?=$Dictionnary["QuizAttemptInProgress"] ?? "En cours"; ?></span><?php } ?>
                    <?php if ($attempt["expires_at"] !== NULL) { ?><small><?=$Dictionnary["QuizInvitationExpiresAt"] ?? "Expire"; ?> <?=quiz_inbox_h($attempt["expires_at"]); ?></small><?php } ?>
                </div>
                <div class="quiz-inbox-action"><a href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$attempt["id"]; ?>" class="quiz-inbox-button"><?=$available ? ($Dictionnary["QuizAttemptResume"] ?? "Reprendre") : ($Dictionnary["Open"] ?? "Ouvrir"); ?></a></div>
            </article>
        <?php } ?>
        <?php if (!count($quiz_inbox_pending)) { ?><div class="quiz-inbox-empty"><?=$Dictionnary["QuizInboxNothingToDo"] ?? "Aucun questionnaire en attente."; ?></div><?php } ?>
        </div>
    </section>

    <section class="quiz-inbox-section">
        <h3><?=$Dictionnary["QuizInboxHistory"] ?? "Historique récent"; ?></h3>
        <div class="quiz-inbox-list">
        <?php foreach (array_slice($quiz_inbox_history, 0, 50) as $attempt) { ?>
            <article class="quiz-inbox-row submitted">
                <div class="quiz-inbox-main"><strong><?=quiz_inbox_h(quiz_inbox_title($attempt)); ?></strong><span><?=quiz_inbox_h(quiz_inbox_context_label($attempt["context_type"] ?? "manual")); ?></span><small>#<?=(int)$attempt["id"]; ?> · <?=quiz_inbox_h($attempt["submitted_at"] ?? $attempt["started_at"]); ?></small></div>
                <div class="quiz-inbox-status"><span class="submitted"><?=$Dictionnary["QuizAttemptSubmitted"] ?? "Soumise"; ?></span></div>
                <div class="quiz-inbox-action"><a href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$attempt["id"]; ?>" class="quiz-inbox-button"><?=$Dictionnary["Open"] ?? "Ouvrir"; ?></a></div>
            </article>
        <?php } ?>
        <?php if (!count($quiz_inbox_history)) { ?><div class="quiz-inbox-empty"><?=$Dictionnary["QuizInboxNoHistory"] ?? "Aucun questionnaire soumis récemment."; ?></div><?php } ?>
        </div>
    </section>
</div>
