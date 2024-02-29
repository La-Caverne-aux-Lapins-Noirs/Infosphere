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
    $not_too_soon = $activity->subject_appeir_date == NULL || $activity->subject_appeir_date < now();
    $not_too_late = $activity->subject_disappeir_date == NULL || $activity->subject_disappeir_date > now();
    $display_subject = true;
    if ($activity->current_subject == "")
	$display_subject = false;
    $missing_medals = [];
    if (!$activity->is_teacher)
    {
	if (!$activity->registered || $activity->leader <= 0)
	    $display_subject = false;
	if (!$not_too_soon)
	    $display_subject = false;
	if (!$not_too_late)
	    $display_subject = false;
	foreach ($activity->medal as $medal)
	{
	    if ($medal["role"] >= 0)
		continue ;
	    else if ($medal["result"] <= 0)
	    {
		$display_subject = false;
		$missing_medals[] = $medal;
	    }
	}
    }
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
	    <?php } else if (!$activity->registered && !$activity->is_teacher) { ?>
		<i><?=$Dictionnary["YouMustBeRegisteredToSee"]; ?></i>
	    <?php } else if (!$not_too_soon) { ?>
		<i><?=$Dictionnary["SubjectNotAvailableYet"]; ?></i>
	    <?php } else if (!$not_too_late) { ?>
		<i><?=$Dictionnary["SubjectNotAvailableAnymore"]; ?></i>
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

