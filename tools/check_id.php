<?php

function check_id($table, $id, $id_column = "id")
{
    global $Database;

     if (!is_symbol($table) || !is_number($id) || !is_symbol($id_column))
	return (false);
    $req = $Database->query("SELECT id FROM `$table` WHERE `$id_column` = $id");
    $req = $req->fetch_assoc();
    return (isset($req["id"]));
}

