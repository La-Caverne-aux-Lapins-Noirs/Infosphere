<?php

function display_nickname($usr, $link = true)
{
    if ($link)
	echo '<a href="index.php?p=ProfileMenu&amp;a='.$usr["id"].'">';
    if (@strlen($usr["nickname"]))
    {
?>
    <span
	onmouseover="this.innerText='<?=$usr["codename"]; ?>';"
	onmouseout="this.innerText='<?=$usr["nickname"]; ?>';"
    >
	<?=$usr["nickname"]; ?>
    </span>
<?php
    }
    else
	echo $usr["codename"];
    if ($link)
        echo '</a>';
}


