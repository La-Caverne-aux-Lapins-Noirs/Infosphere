<?php

function resolve_codename($table, $codename, $codename_column = "codename", $fetch_all = false)
{
    global $Database;

    if (!isset($codename))
	return (new ErrorResponse("MissingCodeName", $table));
    if (!isset($table))
	return (new ErrorResponse("MissingTableName"));
    if (!is_symbol($table))
	return (new ErrorResponse("InvalidTableName", $table));
    if ($codename === NULL)
	return (new ValueResponse(NULL));
    if (is_array($codename))
	$codename = implode(";", $codename);

    // Verifie que tous les symboles sont des symboles au passage.
    if (($codename = split_symbols($codename))->is_error())
	return ($codename);
    $codename = $codename->value;
    if (count($codename) == 1)
	$codename = $codename[0];
    else
	return (resolve_multiple_codenames($table, $codename, $codename_column, $fetch_all));

    $codename = trim($codename);
    $desc = get_prefix($codename);
    $codename = $desc["label"];
    $NotFoundError = "BadCodeName";
    
    if (is_number($codename))
    {
	$codename_column = "id";
	$NotFoundError = "NotAnId";
    }
    else if (!is_symbol($codename_column))
	return (new ErrorResponse("InvalidParameter", $codename_column));

    if ($fetch_all == false)
	$fetch_all = "id";
    else
	$fetch_all = "*";
    $q = $Database->query("
       SELECT $fetch_all FROM `$table` WHERE `$codename_column` = '$codename'
    ");
    if (($q = $q->fetch_assoc()) == false)
	return (new ErrorResponse($NotFoundError, $codename));
    if ($fetch_all == "*")
	return (new ValueResponse($q));

    $out = ($desc["negative"] ? "-" : "").$desc["prefix"].$q["id"];
    return (new ValueResponse($out));
}

function resolve_multiple_codenames($t, $c, $cc, $fa)
{
    $i = 0;
    $res = [];
    foreach ($c as $v)
    {
	if ($v != "")
	{
	    if (($res[$i] = resolve_codename($t, $v, $cc, $fa))->is_error())
		return ($res[$i]); // @codeCoverageIgnore
	    $res[$i] = $res[$i]->value;
	    $i += 1;
	}
    }
    return (new ValueResponse($res));
}

