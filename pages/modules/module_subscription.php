<?php $js = "silent_submitf(this, {after_success: refresh});"; ?>

<?php if ($matter->registered) { ?>

    <?php if ($matter->full_activity->subscription == FullActivity::AUTOMATIC_SUBSCRIPTION) { ?>
	<b><?=$Dictionnary["SubscriptionIsAutomatic"]; ?></b>
	
    <?php } else { ?>

	<b><?=$Dictionnary["Subscribed"]; ?></b><br />
	<?php if ($matter->allow_unregistration) { ?>
	    <form method="delete" action="/api/module/<?=$matter->id; ?>/registration">
		<input
		    type="button"
		    class="modulebutton"
		    onclick="<?=$js; ?>"
		    value="<?=$Dictionnary["Unsubscribe"]; ?>"
		    style="width: 100%; height: 70px; font-size: large; border: 0;"
		/>
	    </form>
	<?php } else { ?>
	    <?=$Dictionnary["UnsubscribeIsNotPossible"]; ?>
	<?php } ?>

    <?php } ?>
    
<?php } else { ?>

    <?php if (!$matter->full_activity->can_subscribe) { ?>
	<b><?=$Dictionnary["YouAreNotConcerned"]; ?></b>
    
    <?php } else if (!period($matter->registration_date, $matter->close_date)) { ?>
	<b><?=$Dictionnary["SubscriptionPeriodIsClose"]; ?></b>

    <?php } else { ?>
	<form method="put" action="/api/module/<?=$matter->id; ?>/registration">
	    <input
		type="button"
		class="modulebutton"
		onclick="<?=$js; ?>"
		value="<?=$Dictionnary["Subscribe"]; ?>"
		style="width: 100%; height: 70px; font-size: large; border: 0; cursor: pointer;"
	    />
	</form>
	<b style="color: red;"><?=$Dictionnary["UnsubscribeIsForbidden"]; ?>.</b>
    <?php } ?>

<?php } ?>

