<?php

function mark_as_deleted($table, $id, $codename_column = "codename", $fetch = false)
{
    global $Database;

    if (($id = resolve_codename($table, $id, $codename_column))->is_error())
	return ($id);
    $id = $id->value;

    if ($codename_column != "")
    {
	$fix = hash("md5", "$id", false);
	$forge = "
           UPDATE $table SET deleted = 1, codename = CONCAT('del_".$fix."_', codename)
           WHERE id = $id
	";
    }
    else
    {
	$forge = "
           UPDATE $table SET deleted = 1
           WHERE id = $id
	";
    }

    if ($Database->query($forge) == false)
        return (new ErrorResponse("CannotEdit")); // @codeCoverageIgnore
    add_log(DESTRUCTIVE_OPERATION, "$table $id");
    if ($fetch)
	return (new ValueResponse(db_select_one("* FROM $table WHERE id = $id")));
    return (new ValueResponse($id));
}

