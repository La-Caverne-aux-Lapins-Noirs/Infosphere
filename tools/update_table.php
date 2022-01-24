<?php

function update_table($table, $id, array $vals, array $filter_out = [], array $filter_in = [])
{
    global $Database;

    if (($id = resolve_codename($table, $id))->is_error())
	return ($id);
    $id = $id->value;

    if (($forge = unroll($vals, UPDATE, $filter_out, $filter_in)) != NULL)
	if ($Database->query("UPDATE $table SET $forge WHERE id = $id") == false)
	    return (new ErrorResponse("CannotEdit")); // @codeCoverageIgnore
    if (($ret = db_select_one("* FROM $table WHERE id = $id")) == NULL)
	return (new ErrorResponse("CannotRetrieveContent")); // @codeCoverageIgnore
    return (new ValueResponse($ret));
}

