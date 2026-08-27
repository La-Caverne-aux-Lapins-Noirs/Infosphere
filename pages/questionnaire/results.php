<?php
require_once (__DIR__."/../../tools/quiz_attempt.php");
$quiz_result_versions = quiz_attempt_snapshot_statistics((int)$questionnaire_page_row["id"]);
$quiz_result_contexts = [];
foreach ($quiz_result_versions as $version)
    $quiz_result_contexts[(string)($version["context_type"] ?? "manual")] = true;
ksort($quiz_result_contexts);
function quiz_results_h($v) { return (htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")); }
?>
<div class="questionnaire-results">
    <div class="questionnaire-notice">
        <strong><?=$Dictionnary["QuizResults"] ?? "Résultats agrégés"; ?></strong>
        <p><?=$Dictionnary["QuizResultsHelp"] ?? "Les réponses sont regroupées par snapshot et contexte afin de ne jamais mélanger silencieusement deux versions ou deux usages différents du questionnaire."; ?></p>
    </div>
    <?php if (count($quiz_result_contexts) > 1) { ?>
        <div class="questionnaire-result-filter">
            <label><span><?=$Dictionnary["QuizAttemptContext"] ?? "Contexte"; ?></span><select id="questionnaire-result-context" onchange="questionnaireFilterResultContexts();"><option value=""><?=$Dictionnary["All"] ?? "Tous"; ?></option><?php foreach (array_keys($quiz_result_contexts) as $context) { ?><option value="<?=quiz_results_h($context); ?>"><?=quiz_results_h($context); ?></option><?php } ?></select></label>
        </div>
    <?php } ?>
    <?php foreach ($quiz_result_versions as $version) { $snapshot = $version["snapshot"]; $context = (string)($version["context_type"] ?? "manual"); ?>
        <section class="questionnaire-result-version" data-result-context="<?=quiz_results_h($context); ?>">
            <header class="questionnaire-result-version-head">
                <div><strong><?=quiz_results_h($version["model"]["name"] ?? $questionnaire_page_row["codename"]); ?></strong><br /><code><?=quiz_results_h(substr((string)$snapshot["effective_hash"], 0, 16)); ?>…</code> <span class="questionnaire-result-context"><?=quiz_results_h($context); ?></span></div>
                <div><strong><?=(int)$version["attempts"]; ?></strong> <?=$Dictionnary["QuizResultResponses"] ?? "réponse(s)"; ?></div>
                <?php if ((int)$version["graded"] > 0) { ?>
                    <div><strong><?=quiz_results_h(number_format($version["score_percent_sum"] / $version["graded"], 1, ".", "")); ?>%</strong> <?=$Dictionnary["QuizResultAverageScore"] ?? "moyenne"; ?><br /><small><?=(int)$version["passed"]; ?>/<?=(int)$version["graded"]; ?> <?=$Dictionnary["QuizResultPassed"] ?? "seuil atteint"; ?></small></div>
                <?php } ?>
                <div class="questionnaire-result-export"><a class="questionnaire-secondary" href="/api/questionnaire/<?=(int)$questionnaire_page_row["id"]; ?>/export_csv?snapshot_id=<?=(int)$snapshot["id"]; ?>&amp;context_type=<?=rawurlencode($context); ?>">⇩ <?=$Dictionnary["QuizResultExportCsv"] ?? "Exporter CSV"; ?></a></div>
            </header>
            <div class="questionnaire-result-question-list">
            <?php foreach ($version["questions"] as $question) { ?>
                <article class="questionnaire-result-question-card">
                    <header><strong><?=quiz_results_h($question["label"]); ?></strong><small><?=quiz_results_h($question["type"]); ?> · <?=(int)$question["answered"]; ?> <?=$Dictionnary["QuizResultAnswers"] ?? "réponse(s)"; ?></small></header>
                    <?php if ($question["type"] === "scale" && $question["numeric_count"] > 0) { ?>
                        <p class="questionnaire-result-big-number"><?=quiz_results_h(number_format($question["sum"] / $question["numeric_count"], 2, ".", "")); ?></p>
                    <?php } ?>
                    <?php if (count($question["counts"])) { ?>
                        <div class="questionnaire-result-bars">
                            <?php arsort($question["counts"]); foreach ($question["counts"] as $value => $count) { $den = max(1, $question["answered"]); $pc = min(100, 100 * $count / $den); ?>
                                <div class="questionnaire-result-bar-row"><span><?=quiz_results_h(form_field_choice_label($question, $value)); ?><?php if (form_field_choice_label($question, $value) !== (string)$value) { ?><small> [<?=quiz_results_h($value); ?>]</small><?php } ?></span><div><i style="width:<?=quiz_results_h(number_format($pc, 2, ".", "")); ?>%"></i></div><strong><?=(int)$count; ?></strong></div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (count($question["texts"])) { ?>
                        <details><summary><?=$Dictionnary["QuizResultTextAnswers"] ?? "Voir les réponses textuelles"; ?> (<?=count($question["texts"]); ?>)</summary>
                            <div class="questionnaire-result-texts">
                            <?php foreach (array_reverse($question["texts"]) as $text) { ?><blockquote><a href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$text["attempt_id"]; ?>">#<?=(int)$text["attempt_id"]; ?></a> <?=nl2br(quiz_results_h($text["value"])); ?></blockquote><?php } ?>
                            </div>
                        </details>
                    <?php } ?>
                </article>
            <?php } ?>
            </div>
        </section>
    <?php } ?>
    <?php if (!count($quiz_result_versions)) { ?><div class="questionnaire-notice questionnaire-empty"><?=$Dictionnary["QuizResultNone"] ?? "Aucune réponse soumise pour le moment."; ?></div><?php } ?>
</div>
<script>
function questionnaireFilterResultContexts() {
    var select = document.getElementById('questionnaire-result-context');
    var context = select ? select.value : '';
    document.querySelectorAll('.questionnaire-result-version').forEach(function(section) {
        section.hidden = !!context && section.dataset.resultContext !== context;
    });
}
</script>
