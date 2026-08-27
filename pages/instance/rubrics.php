<?php
$rubric_quizzes = activity_quiz_entrypoint_quizzes($activity, "rubric");
$rubric_subjects = activity_rubric_subjects($activity);
$rubric_is_manager = !empty($activity->is_assistant) || !empty($activity->is_teacher) || !empty($activity->is_director);
?>
<div class="full_box_with_title final_box">
    <h4><?=$Dictionnary["QuizRubric"] ?? "Barèmes d’évaluation"; ?></h4>
    <?php if (!$rubric_is_manager) { ?>
        <i><?=$Dictionnary["QuizRubricForbidden"] ?? "Les barèmes sont réservés aux responsables pédagogiques de l’activité."; ?></i>
    <?php } else if (!count($rubric_quizzes)) { ?>
        <i><?=$Dictionnary["QuizRubricNotConfigured"] ?? "Aucun barème n’est configuré pour cette activité."; ?></i>
    <?php } else if (!count($rubric_subjects)) { ?>
        <i><?=$Dictionnary["QuizRubricNoSubjects"] ?? "Aucun étudiant inscrit n’est disponible pour cette activité."; ?></i>
    <?php } else { ?>
        <p><?=$Dictionnary["QuizRubricHelp"] ?? "Choisissez un étudiant et un barème. Une soumission crée des faits de médailles pour les compétences effectivement reconnues, avec l’activité et la tentative comme provenance."; ?></p>
        <div class="activity-rubric-grid">
        <?php foreach ($rubric_subjects as $subject) { ?>
            <section class="activity-rubric-subject">
                <h5><?=htmlspecialchars(($subject["nickname"] ?? "") ?: ($subject["codename"] ?? "#".$subject["id"]), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></h5>
                <small><code><?=htmlspecialchars($subject["codename"] ?? "", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code></small>
                <?php foreach ($rubric_quizzes as $rubric_quiz) {
                    $loaded = questionnaire_load_model($rubric_quiz);
                    $name = $loaded["ok"] ? $loaded["model"]["name"] : $rubric_quiz["codename"];
                    $status = activity_rubric_subject_status($activity, (int)$subject["id"], $rubric_quiz, (int)($User["id"] ?? 0));
                ?>
                    <div class="activity-rubric-row">
                        <div>
                            <strong><?=htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></strong>
                            <?php if (count($status["submitted"])) { ?>
                                <small><?=sprintf($Dictionnary["QuizRubricSubmittedCount"] ?? "%d évaluation(s) soumise(s)", count($status["submitted"])); ?></small>
                            <?php } else { ?>
                                <small><?=$Dictionnary["QuizRubricNeverSubmitted"] ?? "Pas encore évalué"; ?></small>
                            <?php } ?>
                        </div>
                        <div class="activity-rubric-actions">
                            <?php if (is_array($status["attempt"])) { ?>
                                <a class="button_link" href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$status["attempt"]["id"]; ?>"><?=$Dictionnary["QuizAttemptResume"] ?? "Reprendre"; ?></a>
                            <?php } else { ?>
                                <form method="post" action="/api/activity/<?=(int)$activity->id; ?>/rubric_attempt" onsubmit="return silent_submitf(this, {after_success: function(result, msg, content) { if (content) window.location.href = content; }});">
                                    <input type="hidden" name="id_subject" value="<?=(int)$subject["id"]; ?>" />
                                    <input type="hidden" name="id_quiz" value="<?=(int)$rubric_quiz["id"]; ?>" />
                                    <button type="submit"><?=count($status["submitted"]) ? ($Dictionnary["QuizRubricEvaluateAgain"] ?? "Nouvelle évaluation") : ($Dictionnary["QuizRubricStart"] ?? "Évaluer"); ?></button>
                                </form>
                            <?php } ?>
                            <?php if (count($status["submitted"])) { $last = $status["submitted"][0]; ?>
                                <a class="button_link" href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$last["id"]; ?>"><?=$Dictionnary["QuizRubricLastResult"] ?? "Dernier résultat"; ?></a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>
        </div>
    <?php } ?>
</div>
<style>
.activity-rubric-grid { display:grid; gap:12px; }
.activity-rubric-subject { padding:12px; border:1px solid rgba(255,255,255,.18); border-radius:8px; }
.activity-rubric-subject h5 { margin:0 0 2px 0; }
.activity-rubric-row { margin-top:10px; padding:8px; background:rgba(0,0,0,.16); border-radius:6px; display:flex; justify-content:space-between; align-items:center; gap:12px; }
.activity-rubric-row > div:first-child { display:grid; gap:2px; }
.activity-rubric-actions { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
.activity-rubric-actions form { margin:0; }
</style>
