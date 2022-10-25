<?php

function display_nickname($usr, $link = true)
{
    if ($link)
	echo '<a href="index.php?p=ProfileMenu&amp;a='.$usr["id"].'">';
    $nick = ucfirst(@$usr["nickname"]);
    if (@strlen($usr["first_name"]) && @strlen($usr["family_name"]))
	$names = ucfirst($usr["first_name"])." ".strtoupper($usr["family_name"]);
    else
	$names = "";
    $codename = $usr["codename"];

    if (@strlen($usr["nickname"])) { ?>
        <span
	    onmouseover="this.innerText='<?=$codename; ?>';"
	    onmouseout="this.innerText='<?=$nick; ?>';"
        >
            <?=$nick; ?>
        </span>
    <?php } else if (strlen($names)) { ?>
        <span
	    onmouseover="this.innerText='<?=$codename; ?>';"
	    onmouseout="this.innerText='<?=$names; ?>';"
        >
	    <?=$names; ?>
        </span>
    <?php } else { ?>
        <?=$codename; ?>
    <?php } ?>
    <?php
    if ($link)
        echo '</a>';
}


