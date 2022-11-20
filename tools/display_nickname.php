<?php

function display_nickname($usr, $link = true)
{
    if (is_object($usr))
    {
	$nusr["nickname"] = $usr->nickname;
	$nusr["first_name"] = $usr->first_name;
	$nusr["family_name"] = $usr->family_name;
	$nusr["codename"] = $usr->codename;
	$usr = $nusr;
    }
    
    if ($link)
	echo '<a href="index.php?p=ProfileMenu&amp;a='.$usr["id"].'">';
    $nick = ucfirst(@$usr["nickname"]);
    if (@strlen($usr["first_name"]) && @strlen($usr["family_name"]))
	$names = ucfirst($usr["first_name"])." ".strtoupper($usr["family_name"]);
    else
	$names = "";
    $codename = @$usr["codename"];

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


