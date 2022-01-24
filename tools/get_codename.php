<?php

function get_codename($table, $id)
{
    return (db_select_one("codename FROM $table WHERE id = $id")["codename"]);
}
