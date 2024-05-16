<?php if ($medal["icon"] != "") { ?>
    <div style="float: left;">
	<img
	    src="<?=$medal["icon"]; ?>"
	    alt="<?=$medal["name"]; ?>"
	/>
<?php } else { ?>
    <div style="float: left; width: 150px; height: 100%;">
	<img src="genicon.php?function=<?=$medal["codename"]; ?>"
	     alt="<?=$medal["name"]; ?>"
	     height="30" width="100"
	     style="width: 100px; height: 30px;"
	/>
<?php } ?>
<?php if ($medal["result"] > 0) { ?>
    <div class="medal_acquired">&nbsp;</div>
<?php } ?>
<?php if ($medal["result"] < 0) { ?>
    <div class="medal_failed">&nbsp;</div>
<?php } ?>
<?php if ($medal["local"] == 1) { ?>
    <div class="medal_local">&nbsp;</div>
<?php } ?>
    </div>

    <?php if (!isset($no_text) || !$no_text) { ?>
	<p style="font-size: xx-small; text-align: justify; padding-right: 10px;">
	    <u><?=$medal["name"]; ?></u><br />
	    <?php if ($activity->is_teacher) { ?><i><?=$medal["codename"]; ?></i><br /><?php } ?>
	    <?=$medal["description"]; ?>
	</p>
    <?php } ?>
