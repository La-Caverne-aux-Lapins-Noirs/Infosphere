<table class="home_table blackened">
    <?php if ($activity->type != 19) { // Misc ?>
	<tr style="border-bottom: 0;"><td colspan="2" style="height: 16px; padding: 0px;">
	    <span style="font-size: 16px; font-weight: bold;">
		<?=$Dictionnary[$activity->type_name]; ?>
	    </span>
	</td></tr>
    <?php } ?>
    <tr><td style="line-break: loose;">
	<?php if (isset($activity->parent_name)) { ?>
	    <a href="index.php?p=ModulesMenu&amp;a=<?=$activity->parent_activity; ?>" style="font-size: x-large;">
		<?=$activity->parent_name; ?>
	    </a><br />
	<?php } ?>
	<?php if ($activity->is_assistant && strlen($activity->name)) { ?>
	    <span style="font-size: x-small;">
		<?=$activity->codename; ?>
	    </span><br />
	<?php } ?>
	
	<?php if ($activity->reference_activity != -1) { ?>
	    <a href="<?=unrollurl([
		     "a" => $activity->reference_activity,
		     "b" => NULL
		     ]); ?>"
	       style="font-size: x-small;"
	    >
		<h5>
		    <?=$Dictionnary["LinkedTo"]; ?>:
		    <?php if (@strlen($activity->reference_name)) { ?>
			<?=$activity->reference_name; ?>
		    <?php } else { ?>
			<?=$activity->reference_codename; ?>
		    <?php } ?>
		    <?php if ($activity->is_assistant && !strlen($activity->name)) { ?>
			(<?=$activity->codename; ?>)
		    <?php } ?>
		    
		</h5>
	    </a>
	<?php } ?>
    </td><td>
	<div style="width: 100%; font-size: xx-large; text-decoration: underline; line-break: loose;">
	    <?=strlen($activity->name) ? $activity->name : $activity->codename; ?>
	</div>
    </td></tr>

    <?php $fields = 0; ?>
    <?php $fields += $activity->current_icon != NULL; ?>
    <?php $fields += !!strlen(trim($activity->description)); ?>
    <?php if ($fields != 0) { ?>
	<tr>
	    <?php if ($activity->current_icon) { ?>
		<td colspan="<?=3 - $fields; ?>" style="min-height: 200px;">
		    <div style="position: relative; height: 100%; width: 100%; min-height: 200px; background-color: transparent;">
			<div
			    style="
				   position: absolute; top: -2.5%; left: -2.5%;
				   background-color: transparent;
				   background-image: url('<?=$activity->current_icon; ?>');
				   background-size: cover;
				   background-repeat: no-repeat;
				   background-position: center center;
				   min-height: 200px;
				   height: 105%;
				   filter: blur(10px);
				   width: 105%;
				   z-index: 1;
				   "
			>
			</div>
			<div
			    style="
				   position: absolute; top: 0px; left: 0px;
				   background-color: transparent;
				   background-image: url('<?=$activity->current_icon; ?>');
				   background-size: contain;
				   background-repeat: no-repeat;
				   background-position: center center;
				   min-height: 200px;
				   height: 100%;
				   width: 100%;
				   z-index: 1;
				   "
			>
			</div>
		    </div>
		</td>
	    <?php } ?>
	    <?php if (strlen($activity->description)) { ?>
		<td
		    class="text"
		    colspan="<?=3 - $fields; ?>"
		    style="
			   background-color: transparent;
			   text-align: justify;
			   text-indent: 4em;
			   width: calc(100% - 10px);
			   padding-left: 10px;
			   font-size: small;
			   "
		>
		    <?=markdown($activity->description); ?>
		</td>
	    <?php } ?>
	</tr>
    <?php } ?>

    <?php if ($activity->unique_session) { ?>
	<tr>
	    <td colspan="2">
		<br />
		<b><?=$Dictionnary["SessionDate"]; ?></b>:<br />
		<span style="font-size: xx-large;">
		    <?=litteral_date($activity->unique_session->begin_date, true); ?>
		    <?=datex("H:i", $activity->unique_session->begin_date); ?>
		    -
		    <?=datex("H:i", $activity->unique_session->end_date); ?>
		</span>
		<br /><br />
	    </td>
	</tr>
    <?php } ?>
    <?php if ($activity->maximum_subscription != -1
	      || ($activity->unique_session && count($activity->unique_session->room))) { ?>
	<tr><td>
	    <h4><b><?=$Dictionnary["RoomCapacity"]; ?></b></h4>
	    <?php $max_established = false; ?>
	    <?php if ($activity->maximum_subscription != -1) { ?>
		<?=$activity->maximum_subscription; ?>
		<?php $max_established = true; ?>
		<br /><br />
	    <?php } ?>
	    <?php if ($activity->unique_session
		      && $activity->unique_session->maximum_subscription != -1) { ?>
		<b><?=$Dictionnary["AvailableSeats"]; ?></b>:
		<?=$activity->unique_session->current_occupation; ?>
		/
		<?=$activity->unique_session->maximum_subscription; ?>
		<?php $max_established = true; ?>
	    <?php } ?>
	    <?php if ($max_established == false) { ?>
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
	<table>
	    <tr style="border: 0;">
		<th style="width: 50%;"><h4><b><?=$Dictionnary["Date"]; ?></b></h4></th>
		<th><h4><b><?=$Dictionnary["Supervision"]; ?></b></h4></th>
		<th><h4><b><?=$Dictionnary["Cycle"]; ?></b></h4></th>
	    </tr>
	    <tr style="border: 0;"><td>
		<table class="left_align_right">
		    <?php if ($activity->emergence_date != NULL) { ?>
			<tr><td><b><?=$Dictionnary["EmergenceDate"]; ?></b>:</td>
			    <td><span><?=litteral_date($activity->emergence_date); ?></span></td>
			</tr>
		    <?php } ?>
		    <?php if ($activity->registration_date != NULL) { ?>
			<tr><td><b><?=$Dictionnary["RegistrationOpenDate"]; ?></b>:</td>
			    <td><span><?=litteral_date($activity->registration_date); ?></span></td>
			</tr>
		    <?php } ?>
		    <?php if ($activity->close_date != NULL) { ?>
			<tr><td><b><?=$Dictionnary["RegistrationCloseDate"]; ?></b>:</td>
			    <td><span><?=litteral_date($activity->close_date); ?></span></td>
			</tr>
		    <?php } ?>
		    <?php if ($activity->subject_appeir_date != NULL) { ?>
			<tr><td><b><?=$Dictionnary["SubjectAppeirDate"]; ?></b>:</td>
			    <td><span><?=litteral_date($activity->subject_appeir_date); ?></span></td>
			</tr>
		    <?php } ?>
		    <?php if ($activity->pickup_date != NULL) { ?>
			<tr><td><b><?=$Dictionnary["PickupDate"]; ?></b>:</td>
			    <td><span><?=litteral_date($activity->pickup_date); ?></span></td>
			</tr>
		    <?php } ?>
		    <?php if ($activity->subject_disappeir_date != NULL) { ?>
			<tr><td><b><?=$Dictionnary["SubjectDisappeirDate"]; ?></b>:</td>
			    <td><span><?=litteral_date($activity->subject_disappeir_date); ?></span></td>
			</tr>
		    <?php } ?>
		    <?php if ($activity->done_date != NULL) { ?>
			<tr><td><b><?=$Dictionnary["DoneDate"]; ?></b>:</td>
			    <td><span><?=litteral_date($activity->done_date); ?></span></td>
			</tr>
		    <?php } ?>
		</table>
	    </td><td>
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
		<?php if (count($activity->cycle)) { ?>
		    <?php foreach ($activity->cycle as $prom) { ?>
			<a href="index.php?p=CycleMenu&amp;a=<?=$prom["id"]; ?>"><?=$prom["codename"]; ?></a><br />
		    <?php } ?>
		<?php } else { ?>
		    /<br />
		<?php } ?>
	    </td></tr>
	</table>
    </td></tr>

    <?php if (@strlen($activity->repository_name) && ($activity->user_team != NULL || $activity->is_assistant)) { ?>
	<tr><td colspan="2">
	    <h4><b><?=$Dictionnary["PickupDirectory"]; ?></b></h4>
	    <?php if (substr($activity->repository_name, 0, 4) == "git:") { ?>
		<?php /*
			 <input type="submit" onclick="window.open('http://<?=$Configuration->Properties["forge"]; ?>/
			 <?=$activity->user_team["leader"]["codename"]; ?>/<?=$activity->repository_name; ?>');"
			 value="<?=$Dictionnary["JoinRepository"].": ".$activity->repository_name; ?>" />
		       */ ?>
	    <?php } else if (substr($activity->repository_name, 0, 4) == "svn:") { ?>

	    <?php } else if (substr($activity->repository_name, 0, 4) == "cvs:") { ?>
		
	    <?php } else {
		if (substr($activity->repository_name, 0, 4) == "nfs:")
		    $activity->repository_name = substr($activity->repository_name, 4);
		if ($activity->user_team != NULL)
		    $dir = $activity->user_team["leader"]["codename"];
		else
		    $dir = "login";
		$target = "/home/users/$dir/work/{$activity->repository_name}";
	    ?>
	    <?php } ?>
	    <input
		type="button"
		class="instance_button"
		value="<?=$target; ?>"
		onclick="navigator.clipboard.writeText('<?=$target; ?>');"
	    />
	</td></tr>
    <?php } ?>
    <tr><td>
	<table class="left_align_right">
	    <tr>
		<td>
		    <b><?=$Dictionnary["SizeOfTeam"]; ?></b>:
		</td>
		<td><span>
		    <?php
		    if ($activity->min_team_size == $activity->max_team_size)
		    {
			if ($activity->min_team_size == -1)
			    echo "1";
			else
			    echo $activity->min_team_size;
		    }
		    else
		    {
			echo "[";
			if ($activity->min_team_size > 1)
			    echo $activity->min_team_size;
			else
			    echo "1";
			echo ";";
			if ($activity->max_team_size > 1)
			{
			    echo $activity->max_team_size;
			    echo "]";
			}
			else
			{
			    echo "&infin;";
			    echo "[";
			}
		    }
		    ?>
		</span></td>
	    </tr>
	    <tr>
		<td>
		    <b><?=$Dictionnary["Participation"]; ?></b>:
		</td>
		<td><span>
		    <?=$Dictionnary[[
			"Optional", "Mandatory", "Automatic"
		    ][$activity->subscription]]; ?>
		</span></td>
	    </tr>
	    <?php if ($activity->mark) { ?>
		<tr>
		    <td>
			<b><?=$Dictionnary["MoneyToWin"]; ?></b>:
		    </td>
		    <td><span>
			<?=$activity->mark; ?>
		    </span></td>
		</tr>
	    <?php } ?>
	    <?php if (count($activity->session)) { ?>
		
	    <?php } ?>
	    <tr>
		<td>
		    <b><?=$Dictionnary["RegisteredUsers"]; ?></b>:
		</td>
		<td><span>
		    <?=count($activity->session) > 1 ? $local_students : $total_students; ?>
		</span></td>
	    </tr>
	    <?php if ($activity->teamable) { ?>
		<tr>
		    <td>
			<b><?=$Dictionnary["RegisteredTeams"]; ?></b>:
		    </td>
		    <td><span>
			<?=count($activity->session) > 1 ? $local_teams : $total_teams; ?>
		    </span></td>
		</tr>
	    <?php } ?>
	</table>
    </td><td id="about_buttons">
	<?php require ("about_buttons.php"); ?>
    </td></tr>
</table>
