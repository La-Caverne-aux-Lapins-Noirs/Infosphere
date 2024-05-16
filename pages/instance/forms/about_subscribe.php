<?php if ($activity->registration_date && $activity->registration_date > now()) { ?>
    <input
	type="button"
	class="instance_button"
	value="<?=$Dictionnary["SubscriptionPeriodIsNotOpenedYet"]; ?>"
    />
<?php } else if ($activity->close_date && $activity->close_date < now()) { ?>
    <input
	type="button"
	class="instance_button"
	value="<?=$Dictionnary["SubscriptionPeriodIsClose"]; ?>"
    />
<?php } else if ($activity->full) { ?>
    <input
	class="instance_button"
	type="button"
	value="<?=$Dictionnary["SessionIsFull"]; ?>"
    />
<?php } else { ?>
    <form
	<?php $js = "silent_submitf(this, {tofill: 'about_buttons', after_success: refresh});"; ?>
	method="put"
	onsubmit="return <?=$js; ?>"
	action="/api/instance/<?=$activity->id; ?>/subscribe"
    >
	<input
	    type="button"
	    onclick="<?=$js; ?>"
	    class="instance_button"
	    <?php if ($activity->teamable) { ?>
		value="<?=$Dictionnary["CreateATeam"]; ?>"
	    <?php } else { ?>
	        value="<?=$Dictionnary["Subscribe"]; ?>"
	    <?php } ?>
	/>
    </form>
<?php } ?>
