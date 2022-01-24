<?php

function edit_codename($table, $old, $new)
{
    global $Database;

    if (($old = resolve_codename($table, $old))->is_error())
	return ($old);
    $old = $old->value;
    $oldname = db_select_one("codename FROM `$table` WHERE id = $old")["codename"];
    if (!($err = resolve_codename($table, $new))->is_error())
	return (new ErrorResponse("CodeNameAlreadyUsed", $new));
    if ($err->label != "BadCodeName")
	return ($err);
    if (!is_symbol($new))
	return (new ErrorResponse("InvalidParameter", $new));
    if ($table == "activity")
    {
	$newfile = "./dres/activity/$new";
	$oldname = "./dres/activity/$oldname";
	$ret = 0;
	system("rm -rf '$newfile'", $ret);
	if ($ret != 0)
	    return (new ErrorResponse("CannotClearExistingActivity"));
	if (is_dir($oldname))
	{
	    if (@rename($oldname, $newfile) == false)
		return (new ErrorResponse("CannotRenameAssociatedDirectory", error_get_last()["message"]));
	}
	else
	{
	    if (@mkdir($newfile) == false)
		return (new ErrorResponse("CannotCreateActivityDirectory", error_get_last()["message"]));
	}
    }
    $Database->query("UPDATE `$table` SET codename = '$new' WHERE id = $old");
}

