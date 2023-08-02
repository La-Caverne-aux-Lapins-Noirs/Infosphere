<?php

function display_avatar($usr, $siz = 200, $photo_first = false)
{
    global $Configuration;

    if (is_object($usr))
	$codename = $usr->codename;
    else
	$codename = $usr["codename"];
    $nw["avatar"] = $Configuration->UsersDir().$codename."/public/avatar.png";
    $nw["photo"] = $Configuration->UsersDir().$codename."/admin/photo.png";
    $nw["id"] = $codename;

    if (!file_exists($nw["avatar"]))
	unset($nw["avatar"]);
    if (!file_exists($nw["photo"]))
	unset($nw["photo"]);

    echo '<a href="index.php?p=ProfileMenu&amp;a='.$nw["id"].'">';
    if (@strlen($nw["avatar"]) && @strlen($nw["photo"]))
    {
?>
    <img
	style="width: <?=$siz; ?>px; height: <?=$siz; ?>px; border-radius: 10px;"
	src="<?=$nw[$photo_first ? "photo" : "avatar"]; ?>"
	onmouseover="this.src='<?=$nw[$photo_first ? "avatar" : "photo"]; ?>';"
	onmouseout="this.src='<?=$nw[$photo_first ? "photo" : "avatar"]; ?>';"
    />
<?php
    }
    else if (@strlen($nw["avatar"])) // Désactivé temporairement
    {
	?><img style="width: <?=$siz; ?>px; height: <?=$siz; ?>px; border-radius: 10px;" src="<?=$nw["avatar"]; ?>" /><?php
    }
    else if (@strlen($nw["photo"]))
    {
	?><img style="width: <?=$siz; ?>px; height: <?=$siz; ?>px; border-radius: 10px;" src="<?=$nw["photo"]; ?>" /><?php
    }
    else
    {
	?><img style="width: <?=$siz; ?>px; height: <?=$siz; ?>px; border-radius: 10px;" src="res/no_avatar.png" /><?php
    }
    echo '</a>';
}
