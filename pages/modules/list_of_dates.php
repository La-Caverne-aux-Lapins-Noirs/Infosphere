<?php if ($date_source->is_teacher) { ?>
    <?php if ($date_source->emergence_date != NULL) { ?>
	<b><?=$Dictionnary["EmergenceDate"]; ?></b>:
	<?=litteral_date($date_source->emergence_date); ?>
	<br />
	<?php $nbline += 1; ?>
    <?php } else if (isset($cycle->first_day)) { ?>
	<b><?=$Dictionnary["EmergenceDate"]; ?></b>:
	<?=litteral_date($cycle->first_day); ?>
	<br />
	<?php $nbline += 1; ?>
    <?php } ?>
<?php } ?>

<?php if ($date_source->session_registered != NULL) { ?>
    <?php if (($uslot = $date_source->session_registered->user_slot) != NULL) { ?>
	<p style="text-align: center; background-color: rgb(200, 143, 26); margin-top: 5px; margin-bottom: 5px; border-radius: 5px;">
	    <?=$Dictionnary["AppointmentThe"]." ".
	       datex("H:i d/m/Y", $uslot["begin_date"])." (".
	       ((date_to_timestamp($uslot["end_date"]) -
		 date_to_timestamp($uslot["begin_date"])
	       ) / 60)." ".$Dictionnary["minutes"].")"
	    ;
	    ?>
	</p>
    <?php } ?>
<?php } ?>

<?php if ($date_source->registration_date != NULL) { ?>
    <b><?=$Dictionnary["RegistrationOpenDate"]; ?></b>:
    <?=litteral_date($date_source->registration_date); ?>
    <?php $nbline += 1; ?>
    <br />
<?php } ?>
<?php if ($date_source->close_date != NULL) { ?>
    <b><?=$Dictionnary["RegistrationCloseDate"]; ?></b>:
    <?=litteral_date($date_source->close_date); ?>
    <?php $nbline += 1; ?>
    <br />
<?php } ?>


<?php if ($date_source->subject_appeir_date != NULL) { ?>
    <b><?=$Dictionnary["SubjectAppeirDate"]; ?></b>:
    <?=litteral_date($date_source->subject_appeir_date); ?>
    <?php $nbline += 1; ?>
    <br />
<?php } ?>
<?php if ($date_source->subject_disappeir_date != NULL) { ?>
    <b><?=$Dictionnary["SubjectDisappeirDate"]; ?></b>:
    <?=litteral_date($date_source->subject_disappeir_date); ?>
    <?php $nbline += 1; ?>
    <br />
<?php } ?>
<?php if ($date_source->pickup_date != NULL) { ?>
    <b><?=$Dictionnary["PickupDate"]; ?></b>:
    <?=litteral_date($date_source->pickup_date); ?>
    <?php $nbline += 1; ?>
    <br />
<?php } ?>

<?php if ($date_source->done_date != NULL) { ?>
    <b><?=$Dictionnary["DoneDate"]; ?></b>:
    <?=litteral_date($date_source->done_date); ?>
    <?php $nbline += 1; ?>
    <br />
<?php } ?>

<?php if ($date_source->maximum_subscription != -1) { ?>
    <?php $nbline += 1; ?>
    <?=$Dictionnary["MaximumSubscription"].": ".$date_source->maximum_subscription; ?>
<?php } ?>

