<?php
$medal_result = (int)try_get($medal, "result", 0);
$medal_type = (int)try_get($medal, "type", 0);
$medal_lost_by_eliminatory = false;
if (isset($activity_has_acquired_eliminatory_medal)
    && $activity_has_acquired_eliminatory_medal
    && $medal_result > 0
    && $medal_type == 0)
    $medal_lost_by_eliminatory = true;
$medal_visual_class = $medal_result <= 0 ? " medal_not_acquired" : "";
?>
<?php if ($medal["icon"] != "") { ?>
    <div class="activity_medal_visual<?=$medal_visual_class; ?>" style="float: left;">
	<img
	    src="<?=$medal["icon"]; ?>"
	    alt="<?=$medal["name"]; ?>"
	/>
<?php } else { ?>
    <div class="activity_medal_visual<?=$medal_visual_class; ?>" style="float: left; width: 150px; height: 100%;">
	<img src="genicon.php?function=<?=$medal["codename"]; ?>"
	     alt="<?=$medal["name"]; ?>"
	     height="30" width="100"
	     style="width: 100px; height: 30px;"
	/>
<?php } ?>
<?php if ($medal_result > 0 && !$medal_lost_by_eliminatory) { ?>
    <div class="medal_acquired">&nbsp;</div>
<?php } ?>
<?php if ($medal_lost_by_eliminatory) { ?>
    <div class="medal_failed">&nbsp;</div>
<?php } ?>
<?php if ($medal["local"] == 1) { ?>
    <div class="medal_local">&nbsp;</div>
<?php } ?>
    </div>

    <?php if (!isset($no_text) || !$no_text) { ?>
	<p class="scrollable">
	    <u><?=$medal["name"]; ?></u><br />
	    <?php if ($activity->is_teacher) { ?><i><?=$medal["codename"]; ?></i><br /><?php } ?>
	    <?=$medal["description"]; ?>
	</p>
    <?php } ?>
