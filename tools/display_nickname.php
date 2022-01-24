<?php

function display_nickname($usr)
{
    echo '<a href="index.php?p=ProfileMenu&amp;a='.$usr["id"].'">';
    if (0 && @strlen($usr["nickname"])) // Désactivé temporairement.
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
    echo '</a>';
}


