<?php

function mark_as_deleted($table, $id, $codename_column = "codename", $fetch = false, $delete_dir = false)
{
    global $Database;

    if (!isset($table))
	return (new ErrorResponse("MissingTableName"));
    if (!is_symbol($table))
	return (new ErrorResponse("InvalidTableName", $table));

    if ($codename_column != "")
    {
	if (($id = resolve_codename($table, $id, $codename_column, true))->is_error())
	    return ($id);
	$codename = $id->value["codename"];
	$id = $id->value["id"];

	$fix = hash("md5", "$id", false);
	$forge = "
           UPDATE $table SET deleted = NOW(), codename = CONCAT('del_', codename, '_".$fix."')
           WHERE id = $id
	   ";
	if ($delete_dir)
	    system("mv ./dres/$table/$codename/ ./dres/trash/$table/del_{$codename}_{$fix}");
    }
    else
    {
	$id = (int)$id;
	$forge = "
           UPDATE $table SET deleted = NOW()
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

