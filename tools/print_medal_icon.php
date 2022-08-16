<?php

function print_medal_icon($codename, $height = 50, $link = true)
{
    global $Dictionnary;

    if ($link) { ?>
    <a href="index.php?p=MedalsMenu&amp;a=<?=$codename; ?>">
    <?php }
    
    if (file_exists($icon = get_icon("MedalsDir", $codename))) { ?>
    <img
	src="<?=$icon; ?>"
	height="<?=$height; ?>"
	width="<?=$height; ?>"
	alt="<?=$Dictionnary["Medal"]." ".$codename; ?>"
    />
    <?php } else { ?>
    <img
	src="genicon.php?function=<?=$codename; ?>"
	height="<?=$height / 2; ?>"
	width="<?=$height * 3; ?>"
	alt="<?=$Dictionnary["Medal"]." ".$codename; ?>"
    />
    <?php
    }

    if ($link) { ?>
    </a>
    <?php }
}
