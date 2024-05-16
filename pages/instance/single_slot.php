<?php if (isset($s["team"]["team_name"])) { ?>

    <?php $team = $s["team"]; ?>
    <?php if (count($team["user"]) > 1) { ?>
	<em><?=$team["team_name"]; ?></em>
    <?php } ?>
    <?php foreach ($team["user"] as $m) { ?>
	<a href="index.php?p=ProfileMenu&amp;a=<?=$m["id"]; ?>"><?=$m["codename"]; ?></a>&nbsp;
    <?php } ?>

    <?php if ($activity->is_teacher
	      || ($activity->registered
	       && $team["id"] == $activity->unique_session->user_team["id"]
	       && $activity->allow_unregistration)) { // SUPPRIMER LE RENDEZ VOUS ?>
	<form action="index.php?<?=unrollget(); ?>" method="post" style="display: inline-block;">
	    <input type="hidden" name="action" value="delete_appointment" />
	    <input type="hidden" name="id_slot" value="<?=$s["id"]; ?>" />
	    <input type="submit" value="&#10007;" style="color: red;" />
	</form>
    <?php } ?>

<?php } else { ?>

    <?php // CE SLOT EST LIBRE ?>

    <?php if ($activity->unique_session->slot_reserved == false) { ?>
	<?=$s["id_team"] == -1 ? $Dictionnary["SlotOpened"] : ""; ?>
	<?=$s["id_team"] == -2 ? $Dictionnary["SlotLocked"] : ""; ?>
	<br />
    <?php } ?>

    <?php if ($activity->is_teacher) { // FORCER UN RENDEZ-VOUS ?>

	<?php if ($s["id_team"] == -1) { ?>
	    <form action="index.php?<?=unrollget(); ?>" method="post" style="display: inline-block;">
		<input type="hidden" name="action" value="set_appointment" />
		<input type="hidden" name="id_slot" value="<?=$s["id"]; ?>" />
		<select name="id_team" style="width: 50%;">
		    <?php foreach ($activity->unique_session->team as $t) { ?>
			<?php if (!isset($t["slot"]) || $t["slot"] == false) { ?>
			    <?php
			    $logins = [];
			    foreach ($t["user"] as $usr)
			    $logins[] = $usr["codename"];
			    ?>
			    <option value="<?=$t["id"]; ?>">
				<?=implode(", ", $logins); ?>
			    </option>
			<?php } ?>
		    <?php } ?>
		</select>
		<input type="submit" value="&#10003;" style="color: green;" />
	    </form>
	<?php } ?>

	<form action="index.php?<?=unrollget(); ?>" method="post" style="display: inline-block;">
	    <input type="hidden" name="action" value="switch_slot" />
	    <input type="hidden" name="id_slot" value="<?=$s["id"]; ?>" />
	    <input type="hidden" name="id_team" value="<?=$s["id_team"]; ?>" />
	    <input type="submit" value="&#x23FB;" style="color: red;" />
	</form>
    <?php } else if ($s["id_team"] == -1) { // M'inscrire comme élève, et le slot est dispo ?>
	<?php if ($activity->can_subscribe
		  && $activity->unique_session->slot_reserved == false
		  && ($activity->registered == false || $activity->leader))
	{ // JE PEUX M'INSCRIRE ET JE N'AI PAS ENCORE RENDEZ VOUS ET (JE NE SUIS PAS INSCRIT OU JE SUIS INSCRIT ET JE SUIS CHEF) ?>
	    <form action="index.php?<?=unrollget(); ?>" method="post" style="display: inline-block;">
		<input type="hidden" name="action" value="take_appointment" />
		<input type="hidden" name="id_slot" value="<?=$s["id"]; ?>" />
		<input type="submit" value="&#10003;" style="color: green;" />
	    </form>
	<?php } ?>
    <?php } ?>
<?php } ?>
