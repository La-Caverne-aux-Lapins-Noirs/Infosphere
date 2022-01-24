<div style="text-align: center; position: absolute; top: 15%; height: 50%; width: 100%;">
    <div style="width: 50%; float: left;">
	<h3>
	    <?php if (isset($activity->parent_name)) { ?>
		<a href="index.php?p=ModulesMenu&amp;a=<?=$activity->parent_activity; ?>">
		    <?=$activity->parent_name; ?>
		</a><br />
	    <?php } ?>
	    <?=$Dictionnary[$activity->type_name]; ?>
	</h3>
	<h1><?=$activity->name; ?></h1>
	<p><?=$activity->codename; ?></p>

	<?php if ($activity->reference_activity != -1) { ?>
	    <a href="index.php?p=ActivityMenu&amp;a=<?=$activity->reference_activity; ?>">
		<h5><?=$Dictionnary["LinkedTo"]; ?>: <?=@strlen($activity->reference_name) ? $activity->reference_name : $activity->reference_codename; ?></h5>
	    </a>
	<?php } ?>

	<br />

	<?php if ($activity->team_size > 1) { ?>
	    <p style="font-size: x-large;">
		<b><?=$Dictionnary["SizeOfTeam"]; ?>:</b>
		<?=$activity->team_size; ?>
	    </p>
	<?php } ?>

	<?php if ($activity->mark > 1) { ?>
	    <p>
		<b><?=$Dictionnary["MoneyToWin"]; ?>:</b>
		<?=$activity->mark; ?>
	    </p>
	<?php } ?>

	<?php if ($activity->unique_session) { ?>
	    <p>
		<b><?=$Dictionnary["SessionDate"]; ?></b>:<br />
		<?=litteral_date($activity->unique_session->begin_date, true); ?>
		<?=datex("H:i", $activity->unique_session->begin_date); ?>
		-
		<?=datex("H:i", $activity->unique_session->end_date); ?>
		<br /><br />
	    </p>

	    <?php if ($activity->maximum_subscription != -1) { ?>
		<p><b><?=$Dictionnary["RoomCapacity"]; ?></b>:
		    <?=$activity->maximum_subscription; ?>
		</p>
		<p><b><?=$Dictionnary["AvailableSeats"]; ?></b>:
		    <?=$activity->unique_session->current_occupation; ?>
		    /
		    <?=$activity->unique_session->maximum_subscription; ?>
		    <br /><br />
		</p>
	    <?php } ?>

	    <?php if (count($activity->unique_session->room)) { ?>
		<h4><?=$Dictionnary["Room"]; ?></h4>
		<p>
		    <?php foreach ($activity->unique_session->room as $room) { ?>
			<?=$room["name"]; ?>
		    <?php } ?>
		</p>
	    <?php } ?>
	<?php } ?>

	<p>
	    <?php if ($activity->emergence_date != NULL) { ?>
		<b><?=$Dictionnary["EmergenceDate"]; ?></b>:
		<?=litteral_date($activity->emergence_date); ?><br />
	    <?php } ?>
	    <?php if ($activity->registration_date != NULL) { ?>
		<b><?=$Dictionnary["RegistrationOpenDate"]; ?></b>:
		<?=litteral_date($activity->registration_date); ?><br />
	    <?php } ?>
	    <?php if ($activity->close_date != NULL) { ?>
		<b><?=$Dictionnary["RegistrationCloseDate"]; ?></b>:
		<?=litteral_date($activity->close_date); ?><br />
	    <?php } ?>
	    <?php if ($activity->subject_appeir_date != NULL) { ?>
		<b><?=$Dictionnary["SubjectAppeirDate"]; ?></b>:
		<?=litteral_date($activity->subject_appeir_date); ?><br />
	    <?php } ?>
	    <?php if ($activity->pickup_date != NULL) { ?>
		<b><?=$Dictionnary["PickupDate"]; ?></b>:
		<?=litteral_date($activity->pickup_date); ?><br />
	    <?php } ?>
	    <?php if ($activity->subject_disappeir_date != NULL) { ?>
		<b><?=$Dictionnary["SubjectDisappeirDate"]; ?></b>:
		<?=litteral_date($activity->subject_disappeir_date); ?><br />
	    <?php } ?>
	    <?php if ($activity->done_date != NULL) { ?>
		<b><?=$Dictionnary["DoneDate"]; ?></b>:
		<?=litteral_date($activity->done_date); ?><br />
	    <?php } ?>
	</p>

    </div>
    <div style="width: 50%; float: left;">
	<p style="text-align: justify; padding-right: 20px;">
	    &nbsp;&nbsp;&nbsp;&nbsp;<?=str_replace("\n", "<br />", $activity->description); ?>
	</p>
	<br /><br />
	<div class="bigbuttons" style="padding-right: 20px;">
	    <?php if ($activity->registered) { ?>

		<?php if (!period($activity->registration_date, $activity->close_date)) { ?>
		    <input type="button" value="<?=$Dictionnary["SubscriptionPeriodIsClose"]; ?>" />
		<?php } else if ($activity->allow_unregistration == false) { ?>
		    <input type="button" value="<?=$Dictionnary["UnsubscribeIsForbidden"]; ?>" />
		<?php } else if ($activity->registered_elsewhere) { ?>
		    <input type="button" value="<?=$Dictionnary["RegisteredElsewhere"]; ?>" />
		<?php } else { ?>
		    <form method="post" action"=index.php?<?=unrollget(); ?>">
			<input type="hidden" name="action" value="unsubscribe" />
			<input type="submit" value="<?=$Dictionnary["Unsubscribe"]; ?>" />
		    </form>
		<?php } ?>

		<?php if ($Configuration->Properties["self_signing"] && $activity->unique_session) { ?>
		    <?php if ($activity->registered_elsewhere == false) { ?>

			<?php if ($activity->unique_session->user_team["present"] == 0) { ?>

			    <?php if (period($activity->unique_session->begin_date - 2 * $five_minute,
					     $activity->unique_session->end_date)) { ?>
				<form method="post" action"=index.php?<?=unrollget(); ?>">
				    <input type="hidden" name="action" value="declare_present" />
				    <input type="submit" value="<?=$Dictionnary["DeclareMyPresence"]; ?>" />
				</form>
			    <?php } else { ?>
				<input type="button" value="<?=$Dictionnary["DeclarationPeriodIsClose"]; ?>" />
			    <?php } ?>

			<?php } else if ($activity->unique_session->user_team["present"] == 1) { ?>
			    <input type="button" value="<?=$Dictionnary["DeclaredPresent"]; ?>" />
			<?php } else if ($activity->unique_session->user_team["present"] == -1) { ?>
			    <input type="button" value="<?=$Dictionnary["DeclaredLate"]; ?>" />
			<?php } else { ?>
			    <input type="button" value="<?=$Dictionnary["DeclaredMissing"]; ?>" />
			<?php } ?>
		    <?php } ?>
		<?php } ?>

		<?php if (@strlen($activity->repository_name) && $activity->user_team != NULL) { ?>
		    <?php if (substr($activity->repository_name, 0, 4) != "git") { ?>
			<input type="button" value="<?=$Dictionnary["PickupDirectory"]." ".$PickupDirectories[$activity->type]."/".$activity->repository_name; ?>" />
		    <?php } else { ?>
			<input type="submit" onclick="window.open('http://<?=$Configuration->Properties["forge"]; ?>/<?=$activity->user_team["leader"]["codename"]; ?>/<?=$activity->repository_name; ?>');" value="<?=$Dictionnary["JoinRepository"].": ".$activity->repository_name; ?>" />
		    <?php } ?>
		<?php } ?>
		<?php if ($activity->pickup_date && period($activity->subject_appeir_date, $activity->pickup_date)) { ?>
		    <form method="post" action="index.php?p=FetchingMenu&amp;a=<?=urlencode($activity->codename); ?>">
			<input
			    type="submit"
				  value="<?=$Dictionnary["DeliverWork"]; ?>"
			/>
		    </form>
		<?php } ?>

	    <?php } else if ($activity->can_subscribe) {?>

		<?php if ($activity->full) { ?>
		    <input type="button" value="<?=$Dictionnary["SessionIsFull"]; ?>" />
		<?php } else {?>
		    <form method="post" action"=index.php?<?=unrollget(); ?>">
			<input type="hidden" name="action" value="subscribe" />
			<input type="submit" value="<?=$Dictionnary["Subscribe"]; ?>" />
		    </form>
		<?php } ?>

	    <?php } else if ($activity->is_teacher) { ?>
		<input type="button" value="<?=$Dictionnary["YouAreNotConcerned"]; ?>" />
	    <?php } ?>
	</div>
    </div>
</div>

<div style="text-align: center; position: absolute; top: 65%; height: 30%; width: 100%;">
    <div style="width: 50%; float: left;">
	<?php if (count($activity->cycle)) { ?>
	    <h4><?=$Dictionnary["Cycle"]; ?>:</h4>
	    <?php foreach ($activity->cycle as $prom) { ?>
		<a href="index.php?p=CycleMenu&amp;a=<?=$prom["id"]; ?>"><?=$prom["codename"]; ?></a><br />
	    <?php } ?>
	<?php } ?>
    </div>
    <div style="width: 50%; float: left;">
	<?php if (count($activity->teacher)) { ?>
	    <h4><?=$Dictionnary["Supervision"]; ?></h4>
	    <?php foreach ($activity->teacher as $t) { ?>
		<?php if (substr($t["codename"], 0, 1) == "#") { ?>
		    <a href="index.php?p=GroupsMenu&amp;a=<?=$t["id"]; ?>"><?=$t["codename"]; ?></a>
		<?php } else { ?>
		    <a href="index.php?p=ProfileMenu&amp;a=<?=$t["id"]; ?>"><?=$t["codename"]; ?></a>&nbsp;
		<?php } ?>
	    <?php } ?>
	<?php } ?>
    </div>
</div>
