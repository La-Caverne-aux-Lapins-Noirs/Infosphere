<?php

function add_menu_entries($menu)
{
    global $Dictionnary;
    global $User;
    
    foreach ($menu as $Name => $Address)
    {
	if (is_page_authorized($Address, $User))
	{
	    if (substr($Address, 0, 4) == "http")
		echo '<a href="'.$Address.'">';
	    else
	    {
		echo '<a href="index.php?p='.$Name.'"';
		if (isset($_GET["p"]) && $Name == $_GET["p"])
		    echo 'class="current_page"';
		echo '>';
	    }
	    echo '<div>'.$Dictionnary[str_replace("Menu", "", $Name)].'</div>';
	    echo '</a>';
        }
    }
}

