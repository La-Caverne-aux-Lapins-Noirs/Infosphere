<div class="full_box_with_title final_box">
    <h4><?=$Dictionnary["AssociatedCourseMaterial"]; ?></h4>
    <div style="width: 100%;">
	<ul>
	    <?php foreach ($activity->support as $ass) { ?>
		<li style="width: 95%; height: 50px; font-size: 30px; line-height: 50px; text-align: center; background-color: rgba(255, 255, 255, 0.25); border-radius: 10px; margin-bottom: 10px; margin-left: 2.5%; list-style-type: none;">
		    <a
			<?php if ($ass["type"] == 0) { ?>
			    href="index.php?p=<?=$ass["position"]; ?>&amp;a=<?=$ass["id_support"]; ?>&amp;b=<?=$ass["id_support_asset"]; ?>"
			<?php } else if ($ass["type"] == 1) { ?>
			href="index.php?p=<?=$ass["position"]; ?>&amp;a=<?=$ass["id_suppport"]; ?>"
			<?php } else { ?>
			href="index.php?p=<?=$ass["position"]; ?>&amp;a=<?=$ass["id_activity"]; ?>"
			<?php } ?>
			style="color: black; text-decoration: none;" target="_blank"
		    >
			- <?=$ass["codename"]; ?> -
		    </a>
		</li>
	    <?php } ?>
	</ul>
    </div>
</div>
