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

    $hashprefix = false;
    $subprefix = false;
    $dollarprefix = false;
    $NotFoundError = "BadCodeName";
    do
    {
	$found = false;
	if (substr($codename, 0, 1) == "#")
	{
	    $found = true;
	    $hashprefix = true;
	    $codename = substr($codename, 1);
	}
	if (substr($codename, 0, 1) == "$")
	{
	    $found = true;
	    $dollarprefix = true;
	    $codename = substr($codename, 1);
	}
	if (substr($codename, 0, 1) == "-")
	{
	    $found = true;
	    if ($codename == -1) // C'est un problème, car on doit pouvoir supprimer l'id 1.
		return (new ValueResponse(-1));
	    $subprefix = true;
	    $codename = substr($codename, 1);
	}
    }
    while ($found);
    if (is_number($codename))
    {
	if ($codename == -1)
	    return (new ValueResponse(-1));
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

    $out = $q["id"];
    if ($subprefix)
	$out = "-".$out;
    if ($hashprefix)
	$out = "#".$out;
    if ($dollarprefix)
	$out = "$".$out;
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

