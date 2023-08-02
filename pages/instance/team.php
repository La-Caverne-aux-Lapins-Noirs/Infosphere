<h4><?=$Dictionnary["Team"]; ?></h4>

<h5 style="width: 100%; font-size: xx-large; text-align: center;"><?=$activity->user_team["team_name"]; ?></h5>

<?php if (@strlen($activity->repository_name) && substr($activity->repository_name, 0, 1) != "/") { ?>
    <h5><?=$Dictionnary["TeamRepository"]; ?></h5>
    <div style="text-align: center;">
	<?=$activity->repository_name; ?>
    </div>
<?php } ?>

<h5><?=$Dictionnary["TeamAdministration"]; ?></h5>

<?php if (isset($activity->user_team["user"][$User["id"]]["status"]) && $activity->user_team["user"][$User["id"]]["status"] == 2) { ?>
    <form method="post" action="index.php?<?=unrollget(); ?>">
	<input type="hidden" name="team_id" value="<?=$activity->user_team["id"]; ?>" />
	<?php if ($activity->user_team["canjoin"]) { ?>
	    <input type="hidden" name="action" value="lockteam" />
	    <input type="submit" value="<?=$Dictionnary["LockTeam"]; ?>" style="width: 100%; height: 50px; font-size: large;" />
	<?php } else { ?>
	    <input type="hidden" name="action" value="unlockteam" />
	    <input type="submit" value="<?=$Dictionnary["UnlockTeam"]; ?>" style="width: 100%; height: 50px; font-size: large;" />
	<?php } ?>
    </form>
    <br />
<?php } ?>

<?php foreach ($activity->user_team["user"] as $usr) { ?>
    <div style="background-color: rgba(255, 255, 255, 0.3); border-radius: 5px; width: 100%; height: 200px;">
	<div style="display: inline-block; width: 200px; height: 200px; float: left;">
	    <?php display_avatar($usr, 200); ?>
	</div>
	<div style="display: inline-block; height: 200px; padding-left: 20px; padding-top: 20px;">
	    <h5><?php display_nickname($usr); ?></h5>
	    <?php if ($usr["status"] == 2) { ?>
		<?=$Dictionnary["Administrator"]; ?>
	    <?php } else if ($usr["status"] == 0) { ?>
		<i><?=$Dictionnary["SubscriptionNotValidatedYet"]; ?></i><br />
		<?php if (isset($activity->user_team["user"][$User["id"]]["status"]) && $activity->user_team["user"][$User["id"]]["status"] == 2) { ?>
		    <form action="index.php?<?=unrollget(); ?>" method="post" style="display: inline-block;">
			<input type="hidden" name="action" value="accept_member" />
			<input type="hidden" name="team_id" value="<?=$activity->user_team["id"]; ?>" />
			<input type="hidden" name="user_id" value="<?=$usr["id"]; ?>" />
			<input type="submit" value="<?=$Dictionnary["Accept"]; ?>" style="height: 120px; width: 200px; color: green; font-size: xx-large;" />
		    </form>
		    <form action="index.php?<?=unrollget(); ?>" method="post" style="display: inline-block;">
			<input type="hidden" name="action" value="refuse_member" />
			<input type="hidden" name="team_id" value="<?=$activity->user_team["id"]; ?>" />
			<input type="hidden" name="user_id" value="<?=$usr["id"]; ?>" />
			<input type="submit" value="<?=$Dictionnary["Refuse"]; ?>" style="height: 120px; width: 200px; color: red; font-size: xx-large;" />
		    </form>
		<?php } ?>
	    <?php } ?>
	</div>
    </div>
<?php } ?>
