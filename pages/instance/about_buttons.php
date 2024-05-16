<?php if ($activity->registered) { ?>
    <?php require ("forms/about_unsubscribe.php"); ?>
    <?php if ($Configuration->Properties["self_signing"] && $activity->unique_session && !$activity->teamable) { ?>
	<?php if ($activity->registered_elsewhere == false) { ?>
	    <?php require ("forms/about_declare.php"); ?>
	<?php } ?>
    <?php } ?>
    <?php require ("forms/about_pickup.php"); ?>		
<?php } else if ($activity->can_subscribe) { ?>
    <?php require_once ("forms/about_subscribe.php"); ?>
<?php } else { ?>	
    <input
	type="button"
	class="instance_button"
	value="<?=$Dictionnary["YouAreNotConcerned"]; ?>"
    />	
<?php } ?>
