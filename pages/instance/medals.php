<h4><?=$Dictionnary["Medals"]; ?></h4>

<?php if (is_admin()) { ?>
    <?php $listed = []; ?>
    <?php foreach ($activity->medal as $medal) { ?>
	<?php $listed[] = $medal["codename"]; ?>
    <?php } ?>
    <input type="text" value="<?=implode(";", $listed); ?>" />
<?php } ?>

<div style="text-align: center;">
    <?php foreach ($activity->medal as $medal) { ?>
	<div class="activity_single_medal_box">
	    <?php if ($medal["icon"] != "") { ?>
		<div style="float: left; height: 100%;">
		    <img
			src="<?=$medal["icon"]; ?>"
			alt="<?=$medal["name"]; ?>"
		    />
		    <?php if ($medal["result"] > 0) { ?>
			<div class="medal_acquired">&nbsp;</div>
		    <?php } ?>
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
		 <?php if ($medal["parent_mandatory"] == 1) { ?>
		     <div class="medal_mandatory">&nbsp;</div>
		 <?php } ?>
		 <?php if ($medal["local"] == 1) { ?>
		     <div class="medal_local">&nbsp;</div>
		 <?php } ?>
		</div>
		<p style="font-size: small; text-align: justify; padding-right: 10px;">
		    <b style="font-size: small;"><?=$medal["name"]; ?></b><br />
		    <?php if ($activity->is_teacher) { ?><i><?=$medal["codename"]; ?></i><br /><?php } ?>
		    <?=$medal["description"]; ?>
		</p>
	</div>
    <?php } ?>
</div>
