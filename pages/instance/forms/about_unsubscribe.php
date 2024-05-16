<?php if ($activity->allow_unregistration == false) { ?>
    <input
	type="button"
	class="instance_button"
	value="<?=$Dictionnary["UnsubscribeIsNotPossible"]; ?>"
    />
<?php } else if ($activity->registered_elsewhere) { ?>
    <input
	type="button"
	class="instance_button"
	value="<?=$Dictionnary["RegisteredElsewhere"]; ?>"
    />
<?php } else if ($activity->close_date && $activity->close_date < now()) { ?>
    <input
	type="button"
	class="instance_button"
	value="<?=$Dictionnary["UnsubscriptionPeriodIsClose"]; ?>"
    />
<?php } else { ?>
    <form
	<?php $js = "silent_submitf(this, {tofill: 'about_buttons', after_success: refresh});"; ?>
	method="delete"
	onsubmit="return <?=$js; ?>"
	action="/api/instance/<?=$activity->id; ?>/subscribe"
    >
	<input
	    type="button"
	    onclick="<?=$js; ?>"
	    class="instance_button"
	    <?php if ($activity->teamable) { ?>
		<?php if ($activity->leader) { ?>
		    value="<?=$Dictionnary["DestroyMyTeam"]; ?>"
		<?php } else { ?>
	            value="<?=$Dictionnary["LeaveMyTeam"]; ?>"
		<?php } ?>
	    <?php } else { ?>
	        value="<?=$Dictionnary["Unsubscribe"]; ?>"
	    <?php } ?>
	/>
    </form>
<?php } ?>
