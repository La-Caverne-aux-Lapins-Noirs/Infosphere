<?php

function print_icon($dir, $codename, $w = 200, $h = 200)
{
    global $Configuration;

    ?>
    <img
	src="<?=$Configuration->$dir.$codename."/icon.png"; ?>"
	alt="<?=$codename; ?>"
	width="<?=$w; ?>"
	height="<?=$h; ?>"
    />
    <?php
}


