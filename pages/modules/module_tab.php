<br />
<table class="module_tab">
    <tr><td colspan="2">
	<h3><?=$act->name ?: $act->codename; ?></h3>
	<td rowspan="2">
	    <div style="width: 100%; height: 100%; background-color: #181818; color: lightgrey; border-radius: 10px; text-align: center;">
		<?php
		$link = [
		    "p" => "InstancesMenu",
		    "a" => $matter->id,
		    "b" => $act->id
		];
		?>
		<br />
		<?php if (is_teacher_for_activity($act->id)) { ?>
		    <a href="<?=unrollurl($link); ?>">
			<?=$Dictionnary["SeeInstanceConfiguration"]; ?>
		    </a><br />
		<?php } ?>
		<?php if ($act->type_type != 2 || $act->session_registered != NULL) { ?>
		    <?php
		    $date = "";
		    if (($session = $act->session_registered) != NULL)
		    {
			$sess = $act->session_registered;
			$date = " ".
				datex("d/m/Y H:i", $sess->begin_date)." - ".
				datex("H:i", $sess->end_date)
			;
		    }
		    $link = [
			"p" => "ActivityMenu",
			"a" => $act->id,
			"b" => $session
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
		<br />
	    </div>
	</td>
    </tr><tr>
	<td colspan="2">
	    <p><i><?=$Dictionnary["ActivityType"]; ?> : <?=$Dictionnary[$ActivityType[$act->type]["codename"]]; ?></i></p>
	    <p><?=$act->description; ?></p>
	</td>
    </tr><tr>
	<td colspan="3" class="medalscroll" style="height: 54px;">
	    <div style="height: 54px;">
		<?php foreach ($act->medal as $medal) { ?>
		    <div
			class="medal_box_picture"
			style="
			       display: inline-block; margin-left: 5px; margin-right: 5px; margin-top: 2px;
			       background-image: url('<?=$medal["icon"]; ?>');
			       <?php if ($medal["band"] != NULL) { ?>
			       border-radius: 25px;
			       border: 2px black solid;
			       width: 46px !important; height: 46px !important;
			       <?php } ?>
			       "
		    ></div>
		<?php } ?>
	    </div>
	</td>
    </tr>
</table>
