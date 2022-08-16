<?php

function is_admin($usr = NULL)
{
    global $User;

    if ($usr == NULL)
	$usr = &$User;
    if ($usr == NULL)
	return (false);
    if (is_string($usr) || is_integer($usr) || is_symbol($usr))
    {
	if (($usr = resolve_codename("user", $usr, "codename", true))->is_error())
	    return (false);
	$usr = $usr->value;
	if (isset($_COOKIE["admin_mode"]))
	    $usr["admin_mode"] = $_COOKIE["admin_mode"];
	else
	    $usr["admin_mode"] = false;
    }
    if ($usr["authority"] != ADMINISTRATOR)
	return (false);
    if (!isset($usr["admin_mode"]))
	return (false);
    return ($usr["admin_mode"]);
}

