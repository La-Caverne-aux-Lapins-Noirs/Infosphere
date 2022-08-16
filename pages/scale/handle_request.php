<?php

if ($_POST["action"] == "add_scale")
{
    $fields = [];
    if (@strlen($_POST["tags"]))
    {
	if (!($request = split_symbols($_POST["tags"], ";", false))->is_error())
	    $fields["tags"] = implode(";", $request->value);
    }
    if (strlen(@$_POST["codename"]) == 0)
    {
	$ret = forge_language_fields(["name"]);
	foreach ($ret as $r)
	{
	    $_POST[$r] = $_POST["codename"];
	}
    }
    $request = @try_insert(
	"scale",
	$_POST["codename"],
	$fields,
	NULL,
	NULL,
	["name"],
	$_POST
    );
    $LogMsg = "Added";
}
else if ($_POST["action"] == "edit_codename")
{
    $request = edit_codename("scale", $_POST["id"], $_POST["codename"]);
    $LogMsg = "Edited";
}
