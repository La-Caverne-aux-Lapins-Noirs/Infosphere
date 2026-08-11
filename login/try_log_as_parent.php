<?php

$x = "";
if (isset($_POST["children"]))
    $x = $_POST["children"];
else if (isset($_COOKIE["children"]))
    $x = $_COOKIE["children"];
if ($x == "")
{
    setcookie("children", "", time() - 1);
    $_COOKIE["children"] = "";
}
else
{
    if (is_number($x))
    {
	$child_link = db_select_one("
               id_child FROM parent_child
               WHERE id_parent = ".$OriginalUser["id"]."
               AND (id_child = ".((int)$x)." OR id = ".((int)$x).")
	");
	if ($child_link)
	    $x = $child_link["id_child"];
    }
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
	{
	    $ErrorMsg = strval(new ErrorResponse("NotYourChildren", $usr["codename"]));
	    setcookie("children", "", time() - 1);
	    $_COOKIE["children"] = "";
	}
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

