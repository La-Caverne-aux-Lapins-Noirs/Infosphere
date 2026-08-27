<?php
$satisfaction_status = activity_satisfaction_status($activity, (int)($User["id"] ?? 0));
$satisfaction_stats = activity_satisfaction_statistics($activity);
$satisfaction_is_manager = !empty($activity->is_assistant) || !empty($activity->is_teacher) || !empty($activity->is_director);
?>

<div class="full_box_with_title final_box">
    <h4><?=$Dictionnary["OpinionOnActivity"]; ?></h4>
    <div class="activity-satisfaction-panel">
        <?php if (!$satisfaction_status["configured"]) { ?>
            <i><?=$Dictionnary["QuizSatisfactionNotConfigured"] ?? "Aucune enquête de satisfaction n’est configurée pour cette activité."; ?></i>
        <?php } else { ?>
            <?php if ($activity->registered && (int)$activity->leader > 0) { ?>
                <p><?=$Dictionnary["QuizSatisfactionStudentHelp"] ?? "Votre avis aide l’équipe pédagogique à améliorer l’activité. Les questions de type échelle alimentent les statistiques agrégées."; ?></p>
                <div class="activity-preaccess-list">
                    <?php foreach ($satisfaction_status["quizzes"] as $sat_status) {
                        $sat_quiz = $sat_status["quiz"];
                        $sat_loaded = questionnaire_load_model($sat_quiz);
                        $sat_name = $sat_loaded["ok"] ? $sat_loaded["model"]["name"] : $sat_quiz["codename"];
                    ?>
                        <div class="activity-preaccess-item <?=$sat_status["completed"] ? "passed" : "pending"; ?>">
                            <span><?=$sat_status["completed"] ? "✓" : "○"; ?> <?=htmlspecialchars($sat_name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span>
                            <?php if (!$sat_status["completed"] && is_array($sat_status["attempt"])) { ?>
                                <a class="button_link" href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$sat_status["attempt"]["id"]; ?>"><?=$Dictionnary["QuizAttemptResume"] ?? "Reprendre"; ?></a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <?php if (!$satisfaction_status["completed"] && $satisfaction_status["next"] !== NULL && !is_array($satisfaction_status["next"]["attempt"])) { ?>
                    <form method="post" action="/api/activity/<?=(int)$activity->id; ?>/satisfaction_attempt" onsubmit="return silent_submitf(this, {after_success: function(result, msg, content) { if (content) window.location.href = content; }});">
                        <button type="submit"><?=$Dictionnary["QuizSatisfactionStart"] ?? "Donner mon avis"; ?></button>
                    </form>
                <?php } else if ($satisfaction_status["completed"]) { ?>
                    <p><strong><?=$Dictionnary["QuizSatisfactionCompleted"] ?? "Merci, vous avez répondu aux enquêtes de satisfaction de cette activité."; ?></strong></p>
                <?php } ?>
            <?php } ?>

            <?php if ($satisfaction_is_manager) { ?>
                <hr />
                <h5><?=$Dictionnary["QuizSatisfactionStatistics"] ?? "Satisfaction agrégée"; ?></h5>
                <?php if ((int)$satisfaction_stats["answers"] <= 0) { ?>
                    <i><?=$Dictionnary["QuizSatisfactionNoStatistics"] ?? "Aucune réponse sur une échelle n’est encore disponible."; ?></i>
                <?php } else { ?>
                    <p>
                        <strong><?=number_format((float)$satisfaction_stats["percent"], 1, ",", " "); ?>%</strong>
                        — <?=sprintf($Dictionnary["QuizSatisfactionRespondents"] ?? "%d répondant(s)", (int)$satisfaction_stats["respondents"]); ?>
                    </p>
                    <table class="table_split_horizontal">
                        <thead>
                            <tr>
                                <th><?=$Dictionnary["QuestionnaireBuilderQuestion"] ?? "Question"; ?></th>
                                <th><?=$Dictionnary["QuizSatisfactionAverage"] ?? "Moyenne"; ?></th>
                                <th><?=$Dictionnary["QuizSatisfactionIndex"] ?? "Indice"; ?></th>
                                <th><?=$Dictionnary["QuizSatisfactionAnswers"] ?? "Réponses"; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($satisfaction_stats["questions"] as $stat) { ?>
                            <tr>
                                <td><?=htmlspecialchars($stat["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></td>
                                <td><?=number_format((float)$stat["average"], 2, ",", " "); ?> / <?=number_format((float)$stat["max"], 2, ",", " "); ?></td>
                                <td><?=number_format((float)$stat["percent"], 1, ",", " "); ?>%</td>
                                <td><?=(int)$stat["count"]; ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            <?php } ?>
        <?php } ?>
    </div>
</div>
