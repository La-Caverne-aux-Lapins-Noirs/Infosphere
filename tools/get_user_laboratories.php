<?php

function get_user_laboratories(&$user)
{
    global $Language;

    if (isset($user["laboratories"]))
	return ($user);
    $user["laboratories"] = db_select_all("
       laboratory.*, laboratory.{$Language}_name as name, user_laboratory.authority
       FROM laboratory
       LEFT JOIN user_laboratory ON laboratory.id = user_laboratory.id_laboratory
       WHERE user_laboratory.id_user = ".$user["id"]."
       AND deleted IS NULL
       ", "codename");
    return ($user);
}

