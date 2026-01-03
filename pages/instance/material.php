<div class="full_box_with_title final_box activity_material">
    <h4><?=$Dictionnary["AssociatedCourseMaterial"]; ?></h4>
    <div style="width: 100%;">
	<ul>
	    <?php foreach ($activity->support as $ass) { ?>
		<li>
		    <a
			<?php if ($ass["type"] == 0) { ?>
			    href="index.php?p=<?=$ass["position"]; ?>&amp;a=<?=$ass["type"]; ?>&amp;b=<?=$ass["id_support_asset"]; ?>"
			<?php } else if ($ass["type"] == 1) { ?>
			    href="index.php?p=<?=$ass["position"]; ?>&amp;a=<?=$ass["type"]; ?>&amp;b=<?=$ass["id_support"]; ?>"
			<?php } else if ($ass["type"] == 2) { ?>
			    href="index.php?p=<?=$ass["position"]; ?>&amp;a=<?=$ass["type"]; ?>&amp;b=<?=$ass["id_support_category"]; ?>"
			<?php } else { ?>
			    href="index.php?p=<?=$ass["position"]; ?>&amp;a=<?=$ass["id_activity"]; ?>"
			<?php } ?>
			target="_blank"
		    >
			<b><?=$Dictionnary[["SupportAsset", "SupportSection", "SupportCategory", "Activity", "Module"][$ass["type"]]]; ?></b>
			:
			<?=$ass[@strlen($ass["name"]) ? "name" : "codename"]; ?>
		    </a>
		</li>
	    <?php } ?>
	</ul>
    </div>
</div>
