<?php

function fetch_books($id = -1)
{
    global $Database;
    
    $filter = "";
    if ($id !== -1 && $id != "")
    {
	$id = $Database->real_escape_string($id);
	$filter = "WHERE codename LIKE '%$id%' OR name LIKE '%$id%'";
    }
    return (new ValueResponse(db_select_all("
	* FROM book $filter ORDER BY codename ASC
    ")));
}
