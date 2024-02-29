<br />
<table
    class="module_tab"
    <?php if ($act->current_wallpaper) { ?>
	style="background-image: url('<?=$act->current_wallpaper; ?>');"
    <?php } ?>
>
    <tr>
	<td colspan="2" style="position: relative;">
	    <h3><?=$act->name ?: $act->codename; ?></h3>
	    <?php if ($act->type_type != 0) { ?>
		<p style="position: absolute; top: 10px; right: 10px; text-align: right;">
		    <i><?=$Dictionnary["ActivityType"]; ?> : <b><?=$Dictionnary[$ActivityType[$act->type]["codename"]]; ?></b></i>
		</p>
	    <?php } ?>

	</td><td rowspan="2">
	    <?php if ($act->is_teacher) { ?>
		<?php
		$link = [
		    "p" => "InstancesMenu",
		    "a" => $act->id,
		];
		?>
		<input
		    type="button"
		    class="modulebutton"
		    value="<?=$Dictionnary["SeeInstanceConfiguration"]; ?>"
		    onclick="document.location='<?=unrollurl($link); ?>';"
		    style="cursor: pointer; width: 100%; height: 35px; font-size: large; border: 0; white-space: normal;"
		/>
	    <?php } ?>
	    
	    <?php
	    $date = "";
	    if (($session = $act->session_registered) != NULL)
		$session = [$session];
	    else if ($act->session)
		$session = $act->session;
	    else
		$session = [];
	    $link = [
		"p" => "ActivityMenu",
		"a" => $act->id,
	    ];
	    ?>
	    <?php if (count($session) == 0) { ?>
		<?php if ($act->type_type == 2) { ?>
		    <input
			type="button"
			value="<?=$Dictionnary["NoSessionProgrammed"]; ?>"
			style="font-weight: bold; width: 100%; height: 35px; font-size: large; border: 0; cursor: pointer;"
		    />
		<?php } else { // Projet ?>
		    <input
			type="button"
			onclick="document.location = '<?=unrollurl($link); ?>';"
			value="<?=$Dictionnary["SeeActivityPage"]; ?>"
			style="font-weight: bold; width: 100%; height: 35px; font-size: large; border: 0; cursor: pointer;"
		    /><br />    
		<?php } ?>
	    <?php } ?>
	    <?php foreach ($session as $sess) { ?>
		<?php
		$link["b"] = $sess->id;
		$date = " ".
			datex("d/m/Y H:i", $sess->begin_date)." - ".
			datex("H:i", $sess->end_date)
		;
		?>
		<input
		    type="button"
		    onclick="document.location = '<?=unrollurl($link); ?>';"
		    value="<?=$Dictionnary["GoToSessionOf"]; ?> <?=$date; ?>"
		    style="font-weight: bold; width: 100%; height: 35px; font-size: large; border: 0; cursor: pointer;"
		/><br />
	    <?php } ?>

	    <?php
	    $date_source = $act;
	    require ("list_of_dates.php");
	    ?>
	</td>
    </tr>

    <tr>
	<td
	    style="
		   text-indent: 2em;
		   text-align: justify;
		   border-radius: 10px;
		   background-color: rgba(255, 255, 255, 0.5);
		   "
	    <?php if (!count($act->medal)) { ?>
		colspan="2"
	    <?php } ?>
	>
	    <?=markdown($act->description, true); ?>
	</td>
	<?php if (count($act->medal)) { ?>
	    <td style="text-align: center; vertical-align: middle;">
		<?php if ($act->codename == "TESTB") { ?>
		    <?php AddDebugLogR($act->medal); ?>
		<?php } ?>
		<?php foreach ($act->medal as $medal) { ?>
		    <div
			class="medal_box_picture"
			style="
			       display: inline-block;
			       position: relative;
			       margin-left: 5px;
			       margin-right: 5px;
			       margin-top: 2px;
			       background-image: url('<?=$medal["icon"]; ?>');
			       <?php if (strstr($medal["command"], " sband ") !== false) { ?>
			       width: <?=$medal_size=42; ?>px !important;
			       height: <?=$medal_size; ?>px !important;
			       border-radius: 25px;
			       border: 2px black solid;
			       <?php } else { ?>
			       width: <?=$medal_size=46; ?>px !important;
			       height: <?=$medal_size; ?>px !important;
			       <?php } ?>
			       "
		    >
			<?php if (@$medal["local"]) { ?>
			    <img
				src="res/local.png"
				style="
				     position: absolute;
				     bottom: 0px;
				     right: -10px;
				     width: <?=$medal_size / 2; ?>px;
				     height: <?=$medal_size / 2; ?>px;
				     "
			    />
			<?php } ?>
		    </div>
		<?php } ?>
	    </td>
	<?php } ?>
    </tr>
</table>
