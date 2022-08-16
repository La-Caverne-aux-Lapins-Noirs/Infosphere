<?php

function add_teacher($idx, $logins, $table = "activity")
{
    global $Database;

    if (($tc = split_teacher($logins))->is_error())
	return ($tc);
    $tc = $tc->value;
    $groups = $tc["laboratory"];
    $usrs = $tc["user"];

    foreach ($groups as $id)
    {
	if (($request = @handle_links($idx, $id, $table, "laboratory", false, $table."_teacher"))->is_error())
	    return ($request);
    }

    foreach ($usrs as $id)
    {
	if (($request = @handle_links($idx, $id, $table, "user", false, $table."_teacher"))->is_error())
	    return ($request);
    }

    return (new ValueResponse(""));
}

