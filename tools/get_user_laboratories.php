<?php

function get_user_laboratories(&$User)
{
    global $Language;

    if (isset($User["laboratories"]))
	return ($User);
    $User["laboratories"] = db_select_all("
       laboratory.*, laboratory.{$Language}_name as name, user_laboratory.authority
       FROM laboratory
       LEFT JOIN user_laboratory ON laboratory.id = user_laboratory.id_laboratory
       WHERE user_laboratory.id_user = ".$User["id"]."
       AND deleted = 0
       ", "codename");
    return ($User);
}

