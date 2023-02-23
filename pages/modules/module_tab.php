<?php
$link = [
    "p" => "ModulesMenu",
    "a" => $act->id
];
?>
<br />
<table class="module_tab">
    <tr><td colspan="<?=isset($_GET["a"]) ? 2 : 1; ?>">
	<h3 onclick="document.location = '<?=unrollurl($link); ?>';"><?=$act->name ?: $act->codename; ?></h3>
	<?php if (!isset($_GET["a"])) { ?>
          </td><td rowspan="2">
	      <?php require ("grade_array.phtml"); ?>
	  </td>
	<?php } ?>
	<?php
	$link = [
	    "p" => "InstancesMenu",
	    "a" => $act->id,
	    "b" => $act->type_type == 2 && $act->session_registered != NULL ? $act->session_registered->id : -1
	];
	?>
	<td rowspan="2">
	    <div class="modulebutton" style="width: 100%; height: 100%; text-align: center;">
		<br />
		<?php if (is_teacher_for_activity($act->id)) { ?>
		    <a href="<?=unrollurl($link); ?>">
			<?=$Dictionnary["SeeInstanceConfiguration"]; ?>
		    </a><br />
		<?php } ?>
		<?php if ($act->type_type == 2) { ?>
		    <?php
		    $date = "";
		    if (($session = $act->session_registered) != NULL)
		    {
			$sess = $act->session_registered;
			$date = " ".
				datex("d/m/Y H:i", $sess->begin_date)." - ".
				datex("H:i", $sess->end_date)
			;
			$link = [
			    "p" => "ActivityMenu",
			    "a" => $act->id,
			    "b" => $session->id
			];
		    ?>
			<a href="<?=unrollurl($link); ?>">
			    <?=$Dictionnary["SeeActivityPage"]; ?><?=$date; ?>
			</a><br />
		    <?php } else { ?>
			<?php foreach ($act->session as $sess) { ?>
			    <?php
			    $link = [
				"p" => "ActivityMenu",
				"a" => $act->id,
				"b" => $sess->id
			    ];
			    $date = " ".
				    datex("d/m/Y H:i", $sess->begin_date)." - ".
				    datex("H:i", $sess->end_date)
			    ;
			    ?>
			    <a href="<?=unrollurl($link); ?>">
				<?=$Dictionnary["SeeActivityPage"]; ?><?=$date; ?>
			    </a><br />
			<?php } ?>
		    <?php } ?>
		<?php } else if ($act->type_type == 0) { ?>
		    <?php
		    $link = [
			"p" => "ModulesMenu",
			"a" => $act->id,
		    ];
		    ?>
		    <a href="<?=unrollurl($link); ?>">
			<?=$Dictionnary["SeeMatter"]; ?>
		    </a><br /><br />
		    <?php if ($act->registered == false) { ?>
			- <?=$Dictionnary["NotSubscribed"]; ?> -
		    <?php } else { ?>
			- <?=$Dictionnary["SingleSubscribed"]; ?> -
		    <?php } ?>
		<?php } else { ?>
		    <?php
		    $link = [
			"p" => "ActivityMenu",
			"a" => $act->id
		    ];
		    ?>
		    <a href="<?=unrollurl($link); ?>">
			<?=$Dictionnary["SeeActivityPage"]; ?>
		    </a><br />
		<?php } ?>
		<br />
	    </div>
	</td>
    </tr><tr>
	<td colspan="<?=isset($_GET["a"]) ? 2 : 1; ?>">
	    <?php if ($act->type_type != 0) { ?>
		<p><i><?=$Dictionnary["ActivityType"]; ?> : <?=$Dictionnary[$ActivityType[$act->type]["codename"]]; ?></i></p>
	    <?php } ?>
	    <p style="text-indent: 2em; text-align: justify;">
		<?=$act->description; ?>
	    </p>
	</td>
    </tr><tr>
	<td colspan="3" class="medalscroll" style="height: 54px;">
	    <div style="height: 54px;">
		<?php if (!count($act->medal)) { ?>
		    <span style="position: relative; top: 10px; left: 10px; font-style: italic; color: gray;">
			<?php if ($act->type_type == 0) { ?>
			    <?=$Dictionnary["NoAssociatedMedalToMatter"]; ?>
			<?php } else { ?>
			    <?=$Dictionnary["NoAssociatedMedalToActivity"]; ?>
			<?php } ?>
		    </span>
		<?php } ?>
		<?php foreach ($act->medal as $medal) { ?>
		    <div
			class="medal_box_picture"
			style="
			       display: inline-block; margin-left: 5px; margin-right: 5px; margin-top: 2px;
			       background-image: url('<?=$medal["icon"]; ?>');
			       width: 46px !important;
			       height: 46px !important;
			       <?php if ($medal["band"] != NULL) { ?>
			       border-radius: 25px;
			       border: 2px black solid;
			       <?php } ?>
			       "
		    ></div>
		<?php } ?>
	    </div>
	</td>
    </tr>
</table>
