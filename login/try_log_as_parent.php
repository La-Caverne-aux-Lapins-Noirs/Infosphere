<?php

$x = "";
if (isset($_POST["children"]))
    $x = $_POST["children"];
else if (isset($_COOKIE["children"]))
    $x = $_COOKIE["children"];
if ($x != "")
{
    if (($usr = resolve_codename("user", $x, "codename", true))->is_error())
	$ErrorMsg = strval($usr);
    else
    {
	$usr = $usr->value;
	$check = db_select_one("
               * FROM parent_child
               WHERE id_parent = ".$OriginalUser["id"]." AND id_child = ".$usr["id"]
	);
	if (!$check && $usr["id"] != $OriginalUser["id"])
	    $ErrorMsg = strval(new ErrorResponse("NotYourChildren", $usr["codename"]));
	else
	{
	    $User = $usr;
	    get_user_promotions($User);
	    get_user_children($User);
	    get_user_laboratories($User);
	    set_cookie("children", $User["id"], time() + 60 * 60 * 24 * 7);
	    if ($User["id"] != $OriginalUser["id"])
		$ParentConnexion = true;
	}
    }
}

