<table class="home_table">
    <tr><td>
	<?php if (isset($activity->parent_name)) { ?>
	    <a href="index.php?p=ModulesMenu&amp;a=<?=$activity->parent_activity; ?>">
		<?=$activity->parent_name; ?>
	    </a><br />
	<?php } ?>
	<?php if ($activity->type != 19) { // Misc ?>
	    <?=$Dictionnary[$activity->type_name]; ?>
	<?php } ?>
	<br /><br />
    </td><td>
	<h1 style="width: 100%; font-size: xx-large; text-decoration: underline;">
	    <?=strlen($activity->name) ? $activity->name : $activity->codename; ?>
	</h1><br /><br />
	<?php if ($activity->is_assistant && strlen($activity->name)) { ?>
	    <p><?=$activity->codename; ?></p><br /><br />
	<?php } ?>
	
	<?php if ($activity->reference_activity != -1) { ?>
	    <a href="<?=unrollurl([
		     "a" => $activity->reference_activity,
		     "b" => NULL
		     ]); ?>"
	    >
		<h5>
		    <?=$Dictionnary["LinkedTo"]; ?>:
		    <?php if (@strlen($activity->reference_name)) { ?>
			<?=$activity->reference_name; ?>
		    <?php } else { ?>
			<?=$activity->reference_codename; ?>
		    <?php } ?>
		    <?php if ($activity->is_assistant && strlen($activity->name)) { ?>
			(<?=$activity->codename; ?>)
		    <?php } ?>
		    
		</h5><br /><br />
	    </a>
	<?php } ?>
	
    </td></tr>

    <?php if ($activity->current_icon) { ?>
	<tr><td colspan="2" style="text-align: center;">
	    <div style="
			background-image: url('<?=$activity->current_icon; ?>');
			width: 100%; height: 200px;
			background-size: contain;
			background-repeat: no-repeat;
			background-position: center center;
			"
	    >
	    </div>
	</td></tr>
    <?php } ?>

    <?php if ($activity->unique_session) { ?>
	<tr><td colspan="2">
	    <br />
	    <b><?=$Dictionnary["SessionDate"]; ?></b>:<br />
	    <span style="font-size: xx-large;">
		<?=litteral_date($activity->unique_session->begin_date, true); ?>
		<?=datex("H:i", $activity->unique_session->begin_date); ?>
		-
		<?=datex("H:i", $activity->unique_session->end_date); ?>
	    </span>
	    <br /><br />
	</td><tr><td>
	    <h4><b><?=$Dictionnary["RoomCapacity"]; ?></b></h4>
	    <?php if ($activity->maximum_subscription != -1) { ?>
		<?=$activity->maximum_subscription; ?>
		<br /><br />
		<b><?=$Dictionnary["AvailableSeats"]; ?></b>:
		<?=$activity->unique_session->current_occupation; ?>
		/
		<?=$activity->unique_session->maximum_subscription; ?>
		<br /><br />
	    <?php } else { ?>
		<?=$Dictionnary["NoSeatLimitation"]; ?>
	    <?php } ?>
	</td><td>
	    <?php if (count($activity->unique_session->room)) { ?>
		<h4><b><?=$Dictionnary["Room"]; ?></b></h4>
		<?php foreach ($activity->unique_session->room as $room) { ?>
		    <?=$room["name"]; ?><br />
		<?php } ?>
	    <?php } ?>
	</td></tr>
    <?php } ?>

    <tr><td colspan="2">
	<style>
	 .left_align_right td:first-of-type
	 {
	     text-align: right;
	     padding-right: 5px;
	 }
	 .left_align_right tr
	 {
	     border: 0;
	 }
	 .left_align_right td
	 {
	     padding-top: 0px;
	     padding-bottom: 0px;
	 }
	</style>
	<table><tr style="border: 0;"><td style="width: 50%;">
	    <h4><b><?=$Dictionnary["Date"]; ?></b></h4>
	    <table class="left_align_right">
		<?php if ($activity->emergence_date != NULL) { ?>
		    <tr><td><b><?=$Dictionnary["EmergenceDate"]; ?></b>:</td>
			<td><?=litteral_date($activity->emergence_date); ?></td>
		    </tr>
		<?php } ?>
		<?php if ($activity->registration_date != NULL) { ?>
		    <tr><td><b><?=$Dictionnary["RegistrationOpenDate"]; ?></b>:</td>
			<td><?=litteral_date($activity->registration_date); ?></td>
		    </tr>
		<?php } ?>
		<?php if ($activity->close_date != NULL) { ?>
		    <tr><td><b><?=$Dictionnary["RegistrationCloseDate"]; ?></b>:</td>
			<td><?=litteral_date($activity->close_date); ?></td>
		    </tr>
		<?php } ?>
		<?php if ($activity->subject_appeir_date != NULL) { ?>
		    <tr><td><b><?=$Dictionnary["SubjectAppeirDate"]; ?></b>:</td>
			<td><?=litteral_date($activity->subject_appeir_date); ?></td>
		    </tr>
		<?php } ?>
		<?php if ($activity->pickup_date != NULL) { ?>
		    <tr><td><b><?=$Dictionnary["PickupDate"]; ?></b>:</td>
			<td><?=litteral_date($activity->pickup_date); ?></td>
		    </tr>
		<?php } ?>
		<?php if ($activity->subject_disappeir_date != NULL) { ?>
		    <tr><td><b><?=$Dictionnary["SubjectDisappeirDate"]; ?></b>:</td>
			<td><?=litteral_date($activity->subject_disappeir_date); ?></td>
		    </tr>
		<?php } ?>
		<?php if ($activity->done_date != NULL) { ?>
		    <tr><td><b><?=$Dictionnary["DoneDate"]; ?></b>:</td>
			<td><?=litteral_date($activity->done_date); ?></td>
		    </tr>
		<?php } ?>
	    </table>
	</td><td>
	    <h4><b><?=$Dictionnary["Supervision"]; ?></b></h4>
	    <?php if (count($activity->teacher)) { ?>
		<?php foreach ($activity->teacher as $t) { ?>
		    <?php if (substr($t["codename"], 0, 1) == "#") { ?>
			<a href="index.php?p=GroupsMenu&amp;a=<?=$t["id"]; ?>"><?=$t["codename"]; ?></a>
		    <?php } else { ?>
			<a href="index.php?p=ProfileMenu&amp;a=<?=$t["id"]; ?>"><?=$t["codename"]; ?></a>&nbsp;
		    <?php } ?>
		<?php } ?>
	    <?php } else { ?>
		/
	    <?php } ?>
	</td><td>
	    <h4><b><?=$Dictionnary["Cycle"]; ?></b></h4>
	    <?php if (count($activity->cycle)) { ?>
		<?php foreach ($activity->cycle as $prom) { ?>
		    <a href="index.php?p=CycleMenu&amp;a=<?=$prom["id"]; ?>"><?=$prom["codename"]; ?></a><br />
		<?php } ?>
	    <?php } else { ?>
		/<br />
	    <?php } ?>
	</table>
    </td></tr>

    <?php if (@strlen($activity->repository_name) && $activity->user_team != NULL) { ?>
	<tr><td colspan="2">
	    <h4><b><?=$Dictionnary["PickupDirectory"]; ?></b></h4>
	    <?php if (substr($activity->repository_name, 0, 4) == "git:") { ?>

	    <?php } else if (substr($activity->repository_name, 0, 4) == "svn:") { ?>

	    <?php } else if (substr($activity->repository_name, 0, 4) == "cvs:") { ?>
		
	    <?php } else {
		if (substr($activity->repository_name, 0, 4) == "nfs:")
		    $activity->repository_name = substr($activity->repository_name, 4);
		$target = "/home/users/".$activity->user_team["leader"]["codename"]."/work/".$activity->repository_name;
	    ?>
	    <?php } ?>
	    <input
		type="button"
		style="width: 80%;
		      height: 50px;
		      font-size: large;
		      font-weight: bold;
		      "
		value="<?=$target; ?>"
		onclick=""
	    />
	</td></tr>

    <?php } ?>

</table>
