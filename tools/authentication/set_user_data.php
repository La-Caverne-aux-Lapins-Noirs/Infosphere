<?php

// Cette fonction ne peut éditer aucun aspect critique lié a l'authentification ou au contact.
function set_user_data($id, $vals, $misc_fields = [], $adduser = false, $fake_account = false)
{
    global $Database;
    global $User;

    if (isset($vals["id"]))
	$id = $vals["id"];
    if ($id == -1 || ($id = resolve_codename("user", $id))->is_error())
    {
	if ($id != -1 && ($id->label != "CodeNameAlreadyUsed" || $adduser == false))
	    return ($id);
	if (($ret = try_subscribe($vals["codename"], $vals["mail"], NULL, NULL, $fake_account))->is_error())
	{
	    if ($ret->label != "LoginAndMailUsed"
		&& $ret->label != "MailUsed"
		&& $ret->label != "LoginUsed")
	    return ($ret);
	}
	$id = resolve_codename("user", $vals["codename"]);
    }

    if (($id = $id->value) == 1) // && !UNIT_TEST && 0)
	return (new ErrorResponse("CannotEditAdministrator")); // @codeCoverageIgnore
    if (!isset($vals))
	return (new ErrorResponse("MissingParameter"));

    /*
    $misc = $User["misc_configuration"];
    foreach ($misc_fields as $i => $v)
    {
	if ($v["type"] == "checkbox")
	    $misc["profile"][$v["label"]] = (@$vals[$v["label"]] == "on");
	else
	    $misc["profile"][$v["label"]] = $vals[$v["label"]];
	unset($vals[$v["label"]]);
    }
    $vals["misc_configuration"] = json_encode($misc, JSON_UNESCAPED_SLASHES);
    */

    /// Il faut resoudre id
    $constfields = ["id", "password", "salt", "local_salt", "codename", "registration_date"];
    $forge = unroll($vals, UPDATE, $constfields);
    $req = "UPDATE user SET $forge WHERE id = $id";
    if ($Database->query($req) == false)
	return (new ErrorResponse("CannotEdit")); // @codeCoverageIgnore

    if ($User && $id == $User["id"])
    {
	foreach ($vals as $i => $v)
	{
	    if (in_array($i, $constfields))
		continue ; // @codeCoverageIgnore
	    $User[$i] = $v;
	}
    }
    return (resolve_codename("user", $id, "codename", true));
}

