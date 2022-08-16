<?php

function remove_teacher($session, $type, $id, $table)
{
    global $Database;

    if ($table != "session" && $table != "instance")
	return (new ErrorResponse("InvalidTableName", $table));
    $session_id = $session["id_".$table];
    if (!is_number($id))
	return (new ErrorResponse("NotAnAId"));
    $forge = "DELETE FROM {$table}_teacher WHERE id_{$table} = $session_id && id_$type = $id";
    $Database->query($forge);
    return (new ValueResponse(""));
}
