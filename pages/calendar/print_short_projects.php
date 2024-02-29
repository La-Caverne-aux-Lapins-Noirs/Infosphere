<?php

function print_short_projects($data)
{
    $h = 0;
    foreach ($data as $act)
    {
?>
    <a href="index.php?p=ActivityMenu&amp;a=<?=$act->id; ?>" class="<?=$act->registered != NULL ? "registered_act" : "unregistered_act"; ?>">
	<div style="
		    left: calc(<?=$act->left; ?>% + 2px);
		    width: calc(<?=$act->width; ?>% - 4px);
		    top: <?=$h++ * 20; ?>px;
		    border-radius: 10px;
		    background-color: <?=color_from_name($act->parent_codename, ["white"]); ?>;
		    "
	     class="short_projects"
	>
	    <?=$act->name; ?>
	    <?php require ("missing_icon.phtml"); ?>
	</div>
    </a>
	<?php
    }
}
