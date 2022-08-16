<?php

function print_short_projects($data)
{
    $h = 0;
    foreach ($data as $d)
    {
?>
    <a href="index.php?p=ActivityMenu&amp;a=<?=$d->id; ?>" class="<?=$d->registered != NULL ? "registered_act" : "unregistered_act"; ?>">
	<div style="left: <?=$d->left; ?>%; width: <?=$d->width; ?>%; top: <?=$h++ * 20; ?>px; background-color: <?=color_from_name($d->parent_codename, ["white"]); ?>;" class="short_projects">
	    <?=$d->name; ?>
	</div>
    </a>
	<?php
    }
}
