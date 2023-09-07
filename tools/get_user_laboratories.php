<?php

function get_user_laboratories(&$user)
{
    global $Language;

    if (isset($user["laboratories"]))
	return ($user);
    $user["laboratories"] = db_select_all("
       laboratory.*,
       laboratory.{$Language}_name as name,
       user_laboratory.authority
       FROM laboratory
       LEFT JOIN user_laboratory ON laboratory.id = user_laboratory.id_laboratory
       WHERE user_laboratory.id_user = ".$user["id"]."
       AND deleted IS NULL
       ", "codename");
    $user["laboratory_authority"] = 0;
    foreach ($user["laboratories"] as $lab)
	if ($user["laboratory_authority"] < $lab["authority"])
	    $user["laboratory_authority"] = $lab["authority"];
    return ($user["laboratories"]);
}

