<?php if ($activity->unique_session->user_team["present"] == 0) { ?>
    <?php if (period($activity->unique_session->begin_date - 2 * $five_minute, $activity->unique_session->end_date)) { ?>
	<form
	    <?php $js = "silent_submitf(this, {tofill: 'about_buttons'});"; ?>
	    method="put"
	    onsubmit="return <?=$js; ?>"
	    action="/api/instance/<?=$activity->id; ?>/declare"
	>
	    <input
		type="button"
		class="instance_button"
		onclick="<?=$js; ?>"
		value="<?=$Dictionnary["DeclareMyPresence"]; ?>"
	    />
	</form>
    <?php } else if ($activity->unique_session->begin_date > now()) { ?>
	<input
	    type="button"
	    class="instance_button"
	    value="<?=$Dictionnary["DeclarationPeriodIsNotOpenedYet"]; ?>"
	/>
    <?php } else { ?>
	<input
	    type="button"
	    class="instance_button"
	    value="<?=$Dictionnary["DeclarationPeriodIsClose"]; ?>"
	/>
    <?php } ?>
<?php } else if ($activity->unique_session->user_team["present"] == 1) { ?>
    <input
	type="button"
	class="instance_button"
	value="<?=$Dictionnary["DeclaredPresent"]; ?>"
    />
<?php } else if ($activity->unique_session->user_team["present"] == -1) { ?>
    <?php
    if ($activity->unique_session->user_team["late_time"] != NULL)
    {
	$late = date_to_timestamp($activity->unique_session->user_team["late_time"]) / 60;
	if ($late > 60)
	    $late = sprintf("%.1fh", $late / 60);
	else
	    $late = sprintf("%dm", $late);
	$late = " ($late)";
    }
    else
	$late = "";
    ?>
    <input
	type="button"
	class="instance_button"
	value="<?=$Dictionnary["DeclaredLate"]; ?><?=$late; ?>"
    />
<?php } else { ?>
    <input
	type="button"
	class="instance_button"
	value="<?=$Dictionnary["DeclaredMissing"]; ?>"
    />
<?php } ?>

