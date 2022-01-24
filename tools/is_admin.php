<?php

function is_admin($usr = NULL)
{
    global $User;

    if ($usr == NULL)
	$usr = &$User;
    if ($usr == NULL)
	return (false);
    if ($usr["authority"] < ADMINISTRATOR)
	return (false);
    if (!isset($usr["admin_mode"]))
	return (false);
    return ($usr["admin_mode"]);
}

