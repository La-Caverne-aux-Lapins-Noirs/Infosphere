<?php

function is_page_authorized($page, $user)
{
    global $User;
    global $Dictionnary;
    global $Database;

    if (!file_exists("$page/access.php"))
	return (true);
    if ($User != NULL && $User["authority"] == BANISHED)
	return (false);
    require ("$page/access.php");
    return ($access);
}

