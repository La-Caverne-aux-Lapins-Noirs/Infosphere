<?php $medals = []; ?>
<?php for ($i = 0; $i < 2; ++$i) { ?>
    <?php foreach ($medlist as $im) { ?>
	<?php
	if ($activity->teamable)
	{
	    if ($medalteam && $im["id_user_team"] != -1)
		continue ;
	    if (!$medalteam && $im["id_user_team"] == -1)
		continue ;
	}
	if (($im["icon"] == "" && $i == 0) || ($im["icon"] != "" && $i == 1))
	    continue ;
	?>
	<?php if ($activity->is_teacher) { ?>
	    <form
		method="PUT"
		<?php if ($medalteam) { ?>
		    <?php $js = "silent_submitf(this, {tofill: 'team_{$cteam["id"]}_medal_list'});"; ?>
		    action="/api/instance/<?=$cteam["id"]; ?>/medal"
		<?php } else { ?>
		    <?php $js = "silent_submitf(this, {tofill: 'team_{$cteam["id"]}_user_{$usr["id"]}_medal_list'});"; ?>
		    action="/api/instance/<?=$cteam["id"]; ?>/medal/<?=$usr["id"]; ?>"
		<?php } ?>
		style="display: inline-block; position: relative;"
		onsubmit="<?=$js; ?>"
	    >
		<?php if ($im["result"] == 1) { ?>
		    <input type="hidden" name="id_medal" value="#<?=$im["codename"]; ?>" />
		<?php } else { ?>
		    <input type="hidden" name="id_medal" value="-<?=$im["codename"]; ?>" />
		<?php } ?>
		<div style="display: inline-block;
			    width: 50px;
			    height: 50px;
			    position: relative;"
		     onclick="<?=$js; ?>"
		>
		    <img
			width="50"
			height="50"
			src="<?=$im["icon"]; ?>"
			title="<?=$im["name"].":\n".$im["description"]; ?>"
			style="opacity: <?=$im["result"] > 0 ? "1.0" : "0.5"; ?>"
		    />
		    <img
			width="15"
			height="15"
			src="./res/cross.png"
			style="position: absolute; right: 0px; bottom: 0px;"
		    />
		    <?php if (isset($im["strength"])) { ?>
			<div class="<?=[
				    "very_weak_medal", "weak_medal",
				    "normal_medal",
				    "strong_medal", "very_strong_medal"
				    ][$im["strength"]]; ?>"
			>&nbsp;</div>
		    <?php } ?>		    
		</div>
	    </form>
	<?php } else { ?>
	    <img
		width="50"
		src="<?=$im["icon"]; ?>"
		title="<?=$im["name"].":\n".$im["description"]; ?>"
		style="opacity: <?=$im["result"] > 0 ? "1.0" : "0.5"; ?>"
	    />
	<?php } ?>
	
	<?php $medals[] = $im["codename"]; ?>
    <?php } ?>
<?php } ?>
<?php if (count($medals)) { ?>
    <?php if ($activity->is_assistant) { ?>
	<img
	    onclick="navigator.clipboard.writeText('<?=implode($medals, ";"); ?>');"
	    width="<?=$activity->is_assistant ? 50 : 30; ?>"
	    height="<?=$activity->is_assistant ? 50 : 30; ?>"
		     title="<?=$Dictionnary["ClickToPutMedalsToClipboard"]; ?>"
		     src="res/copy_icon.png"
	/>
    <?php } ?>
<?php } else { ?>
    <i><?=$Dictionnary["NoMedalToDisplay"]; ?></i>
<?php } ?>

