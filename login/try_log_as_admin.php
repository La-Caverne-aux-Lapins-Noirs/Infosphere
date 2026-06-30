<?php

if (isset($OriginalUser["authority"]) &&
    ($OriginalUser["authority"] >= ADMINISTRATOR || $OriginalUser["id"] == 1) &&
    $User["authority"] >= ADMINISTRATOR)
{
    if (isset($_COOKIE["admin_mode"]))
	$User["admin_mode"] = $_COOKIE["admin_mode"];
    else
	$User["admin_mode"] = false;
    if (isset($_POST["admin_mode"]))
    {
	$User["admin_mode"] = !$User["admin_mode"];
	set_cookie("admin_mode", $User["admin_mode"] ? 1 : 0, time() + 60 * 60 * 24 * 7);
	$_COOKIE["admin_mode"] = $User["admin_mode"] ? 1 : 0;
	if (!$User["admin_mode"])
	{
	    set_cookie("virtual_now", "", time() - 3600);
	    unset($_COOKIE["virtual_now"]);
	}
    }
}

if (virtual_now_can_control())
{
    if (isset($_POST["virtual_now_clear"]))
    {
	set_cookie("virtual_now", "", time() - 3600);
	unset($_COOKIE["virtual_now"]);
	add_log(TRACE, "Virtual date disabled");
    }
    else if (isset($_POST["virtual_now_set"]))
    {
	$virtual_now_value = trim((string)try_get($_POST, "virtual_now_value", ""));
	if ($virtual_now_value != "")
	{
	    $virtual_now_timestamp = (int)date_to_timestamp($virtual_now_value);
	    if ($virtual_now_timestamp > 0)
	    {
		set_cookie("virtual_now", $virtual_now_timestamp, time() + 60 * 60 * 24 * 7);
		$_COOKIE["virtual_now"] = $virtual_now_timestamp;
		if (function_exists("add_log"))
		    add_log(TRACE, "Virtual date enabled: ".human_date($virtual_now_timestamp));
	    }
	}
    }
}

