<?php

function is_group_admin($grp, $usr = NULL)
{
    global $User;

    if (is_admin())
	return (true);
    if ($usr == NULL)
	$usr = $User;
    if (!is_number($usr))
	$usr = $usr["id"];
    $sel = db_select_one("
	* FROM laboratory
	LEFT JOIN user_laboratory ON laboratory.id = user_laboratory.id_laboratory
	WHERE user_laboratory.id_user = $usr AND user_laboratory.authority >= 3
    ");
    return ((bool)$sel);
}
