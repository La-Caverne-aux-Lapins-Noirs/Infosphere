<?php

function delete($id)
{
    global $Database;

    if (!isset($id))
	return ("MissingId");
    if ($Database->query("UPDATE robot SET deleted='1' WHERE id='$id'") == false)
	return ("CannotDelete");
}

function reset_complaint($id)
{
    global $Database;

    if (!isset($id))
	return ("MissingId");
    if ($Database->query("UPDATE robot SET complaint='0' WHERE id='$id'") == false)
	return ("CannotReset");
}

function full_delete($id)
{
    global $Database;
    global $LanguageList;

    if (!is_number($id))
	return ("NotAnId");
    $deleter = $Database->query("
          SELECT robot.file as file
          FROM robot
          WHERE robot.id = $id
	  ");
    while ($del = $deleter->fetch_assoc())
	if (!(unlink($del["file"])))
	    return ("CannotDeleteFile");
    if ($Database->query("DELETE FROM robot WHERE robot.id = $id") == false)
	return ("CannotDelete");
    return ("");
}
