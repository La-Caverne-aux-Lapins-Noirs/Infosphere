<?php

$OriginalUser = $User;
$ParentConnexion = false;
if (isset($User["authority"]) && $User["authority"] >= ADMINISTRATOR)
{
    $x = "";
    if (isset($_POST["log_as"]))
	$x = $_POST["log_as"];
    else if (isset($_COOKIE["log_as"]))
	$x = $_COOKIE["log_as"];
    if ($x != "")
    {
	if (($usr = resolve_codename("user", $x, "codename", true))->is_error())
	    $ErrorMsg = strval($usr);
	else
	{
	    $User = $usr->value;
	    // if (($User["misc_configuration"] = json_decode($User["misc_configuration"], true)) == NULL)
	    // $user["misc_configuration"] = [];
	    get_user_promotions($User);
	    get_user_children($User);
	    get_user_laboratories($User);
	    set_cookie("log_as", $User["id"], time() + 60 * 60 * 24 * 7);
	}
    }
}
