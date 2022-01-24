<?php

function unmark_as_deleted($table, $id, $codename_column = "codename", $fetch = false)
{
    global $Database;

    if (!is_number($id))
	return (new ErrorResponse("AnIdIsRequired"));

    if ($codename_column != "")
    {
	$target = db_select_one("codename FROM $table WHERE id = $id");
	$codename = explode("_", $target["codename"], 3);
	if ($Database->query("
          UPDATE $table SET deleted = 0, codename = '{$codename[2]}' WHERE id = $id
	") == false)
	  return (new ErrorResponse("CannotEdit"));
    }
    else
    {
	if ($Database->query("
          UPDATE $table SET deleted = 0 WHERE id = $id
	") == false)
	return (new ErrorResponse("CannotEdit"));
    }

    add_log(EDITING_OPERATION, "$table $id");
    if ($fetch)
	return (new ValueResponse(db_select_one("* FROM $table WHERE id = $id")));
    return (new ValueResponse($id));
}

