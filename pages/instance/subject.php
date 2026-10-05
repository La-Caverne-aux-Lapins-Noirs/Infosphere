<?php
if (($dir = @scandir("./dres/activity/".$instance["codename"]."/ressources/")) != false)
    $res = true;
else
    $res = false;
?>

<div
    class="subject_box final_box rightbackground background"
    style="height: 100%;"
>

    <?php
    $activity->min_team_size = 1;
    $not_too_soon = $activity->subject_appeir_date == NULL || $activity->subject_appeir_date < now();
    $not_too_late = $activity->subject_disappeir_date == NULL || $activity->subject_disappeir_date > now();
    $display_subject = true;
    $preaccess_status = ["configured" => false, "passed" => true, "quizzes" => [], "next" => NULL];
    $subject_staff_access = $activity->is_director || $activity->is_teacher || $activity->is_assistant;
    if ($activity->current_subject == "")
	$display_subject = false;
    $missing_medals = [];
    if (!$subject_staff_access)
    {
	if (!$activity->registered || $activity->leader <= 0)
	    $display_subject = false;
	if ($activity->teamable && $activity->user_team && (count($activity->user_team["user"]) < $activity->min_team_size && $activity->user_team["canjoin"]))
	    $display_subject = false;
	if (!$not_too_soon)
	    $display_subject = false;
	if (!$not_too_late)
	    $display_subject = false;
	$preaccess_status = activity_preaccess_status($activity, (int)$User["id"]);
	if ($preaccess_status["configured"] && !$preaccess_status["passed"])
	    $display_subject = false;
	foreach ($activity->medal as $medal)
	{
	    if ($medal["role"] >= 0)
		continue ;
	    // Condition
	    else if ($medal["result"] <= 0)
	    {
		// On vérifie un peu sauvagement...
		// La médaille peut venir de n'importe ou du coup...
		// la localité n'est pas prise en compte
		$check = db_select_one("
                    result FROM user_medal
                    WHERE id_user = {$User["id"]}
                    AND id_medal = {$medal["id"]}
                    AND result = 1
		    ");
		if ($check == NULL)
		{
		    $display_subject = false;
		    $missing_medals[] = $medal;
		}
	    }
	}
    }

    // Dynamic subjects are generated only for an actual authorized request.
    // FullActivity::build() deliberately has no document-generation side effect.
    if ($display_subject && $activity->current_configuration !== NULL
        && is_file($activity->current_configuration))
        ensure_subject_for_access($activity, $User);

    if ($activity->current_subject == "")
        $display_subject = false;
    ?>

    <h4><?=$Dictionnary["ActivitySubject"]; ?></h4>
    <?php $pdf = strlen($activity->current_subject) > 256 || pathinfo($activity->current_subject, PATHINFO_EXTENSION) == "pdf"; ?>

    <?php if ($display_subject) { ?>
	<div style="float: right; height: 25px;">
	    <?php if ($pdf) { ?>
		<a href="<?=$activity->current_subject; ?>">
		    <?=$Dictionnary["Download"]; ?>
		</a>
	    <?php } ?>
	</div>
	<iframe
	    src="<?=$activity->current_subject; ?><?=$pdf ? '#toolbar=0&navpanes=0&scrollbar=0' : ''; ?>"
		 style="width: 99%; height: 90%;"
	>
	</iframe>
    <?php } else { ?>
	<div style="position: absolute; top: 40%; text-align: center; width: 100%; font-size: xx-large;" id="subject_error_box">
	    <?php if ($activity->current_subject == "") { ?>
		<i><?=$Dictionnary["SubjectNotAvailable"]; ?></i>
                <?php if ($subject_staff_access && !empty($activity->subject_generation_error)) { ?>
                    <pre
                        style="font-size: 13px; text-align: left; white-space: pre-wrap; margin: 20px; padding: 12px; overflow: auto;"
                    ><?=htmlspecialchars(
                        $activity->subject_generation_error,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        "UTF-8"
                    ); ?></pre>
                <?php } ?>
	    <?php } else if (!$activity->registered && !$subject_staff_access) { ?>
		<i><?=$Dictionnary["YouMustBeRegisteredToSee"]; ?></i>
	    <?php } else if ($activity->teamable && $activity->user_team["canjoin"]) { ?>
		<i><?=$Dictionnary["YouMustCompleteYourTeamToGetTheSubject"]; ?></i>
	    <?php } else if (!$not_too_soon) { ?>
		<i><?=$Dictionnary["SubjectNotAvailableYet"]; ?></i>
	    <?php } else if (!$not_too_late) { ?>
		<i><?=$Dictionnary["SubjectNotAvailableAnymore"]; ?></i>
	    <?php } else if ($preaccess_status["configured"] && !$preaccess_status["passed"]) { ?>
                <div class="activity-preaccess-lock">
                    <i><?=$Dictionnary["QuizPreaccessSubjectLocked"] ?? "Un questionnaire préalable doit être réussi avant l'accès au sujet."; ?></i>
                    <div class="activity-preaccess-list">
                    <?php foreach ($preaccess_status["quizzes"] as $pre_qstatus) {
                        $pre_quiz = $pre_qstatus["quiz"];
                        $pre_loaded = questionnaire_load_model($pre_quiz);
                        $pre_name = $pre_loaded["ok"] ? $pre_loaded["model"]["name"] : $pre_quiz["codename"];
                    ?>
                        <div class="activity-preaccess-item <?=$pre_qstatus["passed"] ? "passed" : "pending"; ?>">
                            <span><?=$pre_qstatus["passed"] ? "✓" : "○"; ?> <?=htmlspecialchars($pre_name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span>
                            <?php if (!$pre_qstatus["passed"] && is_array($pre_qstatus["attempt"])) { ?>
                                <a class="button_link" href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$pre_qstatus["attempt"]["id"]; ?>"><?=$Dictionnary["QuizAttemptResume"] ?? "Reprendre"; ?></a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    </div>
                    <?php if ($preaccess_status["next"] !== NULL && !is_array($preaccess_status["next"]["attempt"])) { ?>
                        <form method="post" action="/api/activity/<?=(int)$activity->id; ?>/preaccess_attempt" onsubmit="return silent_submitf(this, {after_success: function(result, msg, content) { if (content) window.location.href = content; }});">
                            <button type="submit"><?=$Dictionnary["QuizPreaccessStart"] ?? "Commencer le questionnaire préalable"; ?></button>
                        </form>
                    <?php } ?>
                </div>
	    <?php } else if (count($missing_medals)) { ?>
		<i><?=$Dictionnary["YouNeedThesesMedalsToSeeSubject"]; ?>:</i>
		<br /><br />
		<?php $no_text = true; ?>
		<?php foreach ($missing_medals as $medal) { ?>
		    <a href="index.php?p=MedalsMenu&amp;a=<?=$medal["id"]; ?>">
			<?php require ("single_medal.php"); ?>
		    </a>
		<?php } ?>
	    <?php } ?>
	</div>
    <?php } ?>
</div>

