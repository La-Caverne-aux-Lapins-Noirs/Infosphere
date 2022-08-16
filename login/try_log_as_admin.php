<?php

if (isset($OriginalUser["authority"]) && $OriginalUser["authority"] >= ADMINISTRATOR && $User["authority"] >= ADMINISTRATOR)
{
    if (isset($_COOKIE["admin_mode"]))
	$User["admin_mode"] = $_COOKIE["admin_mode"];
    else
	$User["admin_mode"] = false;
    if (isset($_POST["admin_mode"]))
	set_cookie("admin_mode", $User["admin_mode"] = !$User["admin_mode"], time() + 60 * 60 * 24 * 7);
}
