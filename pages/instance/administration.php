
<style>
 .fdw <?php // full date width ?>
 {
     width: 90%;
 }
 .bcb <?php // big check box ?>
 {
     position: relative;
     top: 7px;
     left: 5px;
     width: 22px;
     height: 22px;
 }
</style>

<div class="double_horizontal">

    <div>
	<h3><?=$Dictionnary["AdministrateActivity"]; ?></h3>

	<p>
	    <?=$Dictionnary["ClickToGetCode"]; ?><br />
	    <input
		type="text"
		style="width: 96%; text-align: center;"
		value="<?=$activity->codename; ?>"
		onclick="this.select(); document.execCommand('copy');"
	    />
	</p>

	<form
	    method="POST"
	    action="index.php?<?=unrollget(); ?>"
	>
	    <input type="hidden" name="action" value="edit_date" />
	    <input type="hidden" name="activity" value="<?=$activity->id; ?>" />

	    <label for="emergence_date">
		<?=$Dictionnary["EmergenceDate"]; ?>
	    </label>
	    <?=print_datetime("emergence_date", $activity, true, "fdw", "bcb"); ?>
	    <br />

	    <label for="registration_date">
		<?=$Dictionnary["RegistrationDate"]; ?>
	    </label>
	    <?=print_datetime("registration_date", $activity, true, "fdw", "bcb"); ?>
	    <br />

	    <label for="close_date">
		<?=$Dictionnary["CloseDate"]; ?>
	    </label>
	    <?=print_datetime("close_date", $activity, true, "fdw", "bcb"); ?>
	    <br />

	    <label for="subject_appeir_date">
		<?=$Dictionnary["SubjectAppeirDate"]; ?>
	    </label>
	    <?=print_datetime("subject_appeir_date", $activity, true, "fdw", "bcb"); ?>
	    <br />

	    <label for="pickup_date">
		<?=$Dictionnary["PickupDate"]; ?>
	    </label>
	    <?=print_datetime("pickup_date", $activity, true, "fdw", "bcb"); ?>
	    <br />

	    <label for="subject_disappeir_date">
		<?=$Dictionnary["SubjectDisappeirDate"]; ?>
	    </label>
	    <?=print_datetime("subject_disappeir_date", $activity, true, "fdw", "bcb"); ?>
	    <br />

	    <?php if ($activity->unique_session) { ?>
		<input type="hidden" name="session" value="<?=$activity->unique_session->id; ?>" />
		<label for="begin_date">
		    <?=$Dictionnary["SessionBeginDate"]; ?>
		</label>
		<?=print_datetime("begin_date", $activity->unique_session, true, "fdw", "bcb"); ?>
		<br />

		<label for="end_date">
		    <?=$Dictionnary["SessionEndDate"]; ?>
		</label>
		<?=print_datetime("end_date", $activity->unique_session, true, "fdw", "bcb"); ?>
		<br />
	    <?php } ?>

	    <input type="submit" style="width: 96%;" value="&#10003;" />
	    <br />
	    <br />
	</form>

	<?php if ($activity->unique_session) { ?>
	    <form method="POST" action="index.php?<?=unrollget(); ?>">
		<label for="rooms" style="width: 96%; text-align: center;">
		    <?=$Dictionnary["EditRoom"]; ?>
		</label>
		<input type="hidden" name="action" value="add_room" />
		<input type="hidden" name="session" value="<?=$activity->unique_session->id; ?>" />
		<input
		    type="text"
			  name="rooms"
			  style="width: 96%;"
			  placeholder="<?=$Dictionnary["Rooms"]; ?>"
		/>
		<input type="submit" style="width: 96%;" value="&#10003;" />
	    </form>
	<?php } ?>
    </div>

    <div>
	<h3>&nbsp;<?php // $Dictionnary["AdministrateStudents"]; ?></h3>

	<p style="text-align: center;">
	    <a href="index.php?p=InstancesMenu&amp;activity=<?=$activity->id; ?>">
		<?=$Dictionnary["SeeInstanceConfiguration"]; ?>
	    </a><br />
	</p>

	<p style="text-align: center;">
	    <a href="index.php?p=ActivitiesMenu&amp;activity=<?=$activity->id_template; ?>">
		<?=$Dictionnary["SeeTemplateConfiguration"]; ?>
	    </a><br />
	</p>

	<?php if ($activity->unique_session) { ?>
	    <form
		method="get"
		target="_blank"
		action="pages/token/print_token.phtml"
	    >
		<input
		    type="hidden"
		    name="session"
		    value="<?=$activity->unique_session->id; ?>"
		/>
		<input
		    style="width: 96%;"
		    type="submit"
		    value="<?=$Dictionnary["GetToken"]; ?>"
		/>
	    </form>

	    <form
		method="get"
		target="_blank"
		action="pages/instance/student_status.phtml"
	    >
		<input
		    type="hidden"
		    name="instance"
		    value="<?=$activity->id; ?>"
		/>
		<input
		    type="hidden"
		    name="session"
		    value="<?=$activity->unique_session->id; ?>"
		/>
		<input
		    type="hidden"
		    name="export"
		    value="1"
		/>
		<input
		    style="width: 96%;"
		    type="submit"
		    value="<?=$Dictionnary["PrintStudentStatus"]; ?>"
		/>
	    </form>

	    <form
		method="get"
			target="_blank"
			action="index.php"
	    >
		<input
		    type="hidden"
		    name="p"
		    value="<?=$Position; ?>"
		/>
		<input
		    type="hidden"
		    name="a"
		    value="<?=try_get($_GET, "a", -1); ?>"
		/>
		<input
		    type="hidden"
		    name="b"
		    value="<?=try_get($_GET, "b", -1); ?>"
		/>
		<input
		    type="hidden"
		    name="fetch"
		    value="1"
		/>
		<input
		    type="hidden"
		    name="silent"
		    value="1"
		/>
		<input
		    type="submit"
		    style="width: 96%;"
		    value="<?=$Dictionnary["RetrieveAllWork"]; ?>"
		/>
	    </form>
	<?php } ?>
	<form method="post" action="index.php?<?=unrollget(); ?>">
	    <input type="hidden" name="action" value="subscribe_all" />
	    <input type="hidden" name="activity" value="<?=$activity->id; ?>" />
	    <?php if (isset($activity->unique_session->id)) { ?>
		<input type="hidden" name="session" value="<?=@$activity->unique_session->id; ?>" />
	    <?php } else { ?>
		<input type="hidden" name="session" value="-1" />
	    <?php } ?>
	    <input
		type="submit"
		style="width: 96%;"
		value="<?=$Dictionnary["SubscribeAllStudents"]; ?>"
	    />
	</form>

	<form method="POST" action="index.php?<?=unrollget(); ?>">
	    <label for="logins" style="width: 96%; text-align: center;">
		<?=$Dictionnary["SubscribeUser"]; ?>
	    </label>
	    <input type="hidden" name="action" value="force_subscribe" />
	    <input
		type="text"
		name="logins"
		style="width: 96%;"
		placeholder="<?=$Dictionnary["UserToSubscribe"]; ?>"
	    />
	    <input type="submit" style="width: 96%;" value="&#10003;" />
	</form>

	<form method="POST" action="index.php?<?=unrollget(); ?>">
	    <label for="logins" style="width: 96%; text-align: center;">
		<?=$Dictionnary["UnsubscribeUser"]; ?>
	    </label>
	    <input type="hidden" name="action" value="force_unsubscribe" />
	    <input
		type="text"
		name="logins"
		style="width: 96%;"
		placeholder="<?=$Dictionnary["UserToUnsubscribe"]; ?>"
	    />
	    <input type="submit" style="width: 96%;" value="&#10003;" />
	</form>

	<?php if ($activity->unique_session) { ?>
	    <form method="post" action="index.php?<?=unrollget(); ?>">
		<h4><?=$Dictionnary["GenerateSlots"]; ?></h4>
		<input type="hidden" name="action" value="generate_slots" />
		<input type="hidden" name="session" value="<?=$activity->unique_session->id; ?>" />
		<label for="duration">
		    <?=$Dictionnary["SlotDuration"]; ?>
		</label>
		<input
		    type="time"
			  name="duration"
			  style="width: 96%; text-align: center;"
		/>
		<label for="simultaneous">
		    <?=$Dictionnary["SimultaneousSlot"]; ?>
		</label>
		<input
		    type="input"
			  name="simultaneous"
			  placeholder="<?=$Dictionnary["SimultaneousSlot"]; ?>"
			  style="width: 96%; text-align: center;"
		/><br />
		<input type="submit" style="width: 96%;" value="<?=$Dictionnary["Generate"]; ?>" />
	    </form>
	    <form method="post" action="index.php?<?=unrollget(); ?>">
		<input type="hidden" name="action" value="add_simultaneous_slots" />
		<input type="submit" style="width: 96%;" value="<?=$Dictionnary["AddASlotLayer"]; ?>" />
	    </form>
	<?php } ?>

	<form method="post" action="index.php?<?=unrollget(); ?>">
	    <label for="logins">
		<?=$Dictionnary["AddTeachers"]; ?>
	    </label>
	    <input type="hidden" name="action" value="add_teacher" />
	    <input type="hidden" name="activity" value="<?=$activity->id; ?>" />
	    <input
		type="text"
		name="logins"
		style="width: 96%; text-align: center;"
		placeholder="<?=$Dictionnary["AddTeachers"]; ?>"
	    /><br />
	    <input type="submit" style="width: 96%;" value="&#10003;" />
	</form>

	<?php if ($activity->unique_session) { ?>
	    <form method="post" action="index.php?<?=unrollget(); ?>">
		<br />
		<input type="hidden" name="action" value="delete_session" />
		<input
		    type="submit"
		    style="width: 96%; color: red; background-color: black; font-weight: bold;"
		    value="<?=$Dictionnary["DeleteSession"]; ?>"
		/>
	    </form>
	<?php } ?>
	<form method="post" action="index.php?<?=unrollget(); ?>">
	    <br />
	    <input type="hidden" name="action" value="delete_instance" />
	    <input
		type="submit"
		style="width: 96%; color: red; background-color: black; font-weight: bold;"
		value="<?=$Dictionnary["DeleteInstance"]; ?>"
	    />
	</form>
    </div>
</div>

