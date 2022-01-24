<?php

define("UPDATE", "UPDATE");
define("WHERE", "WHERE");
define("WHERE_OR", "WHERE_OR");
define("SELECT", "SELECT");

function unroll(array $tab, $type = SELECT, $filter_out = [])
{
    global $Database;

    $forge = [];
    foreach ($tab as $i => $v)
    {
	if (is_int($i))
	{
	    if (in_array($v, $filter_out))
		continue ;
	}
	else
	{
	    if (in_array($i, $filter_out))
		continue ;
	}

	if (is_bool($v))
	    $v = ($v ? "1" : "0");

	if ($type == UPDATE || $type == WHERE || $type == WHERE_OR)
	{
	    if (!is_integer($v))
		$v = "'".$Database->real_escape_string($v)."'";
	    $forge[] = $Database->real_escape_string($i)." = $v";
	}
	else if ($type == SELECT)
	{
	    if (is_int($i))
		$forge[] = $Database->real_escape_string($v);
	    else
		$forge[] = $Database->real_escape_string($i);
	}
	else
	    throw new Exception("Invalid parameter type for unroll function");
    }
    if ($type == UPDATE || $type == SELECT)
	$forge = implode(",", $forge);
    else if ($type == WHERE)
	$forge = implode(" AND ", $forge);
    else if ($type == WHERE_OR)
	$forge = implode(" OR ", $forge);
    return ($forge);
}
