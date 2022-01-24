<?php

function get_authorities($attr = [])
{
    global $Database;

    if ($attr == []) // Get all authorities. Return an array
	$forge = "1";
    else if (is_int($attr)) // Return label for specific authority
	$forge = unroll(["id" => $attr], WHERE);
    else if (is_array($attr)) // Return label for multiples authority
    {
	$forge = [];
	foreach ($attr as $i)
	    $forge[] = " id = $i ";
	$forge = implode (" OR ", $forge);
    }

    $forge = "SELECT id, label FROM authorities WHERE $forge ORDER BY id ASC";
    if (!($q = $Database->query($forge)))
        throw new Exception("InvalidRequest"); // @codeCoverageIgnore
    if (is_int($attr))
	return ($q->fetch_assoc()["label"]);
    $a = [];
    while (($sq = $q->fetch_assoc()))
	$a[$sq["id"]] = $sq["label"];
    return ($a);
}

