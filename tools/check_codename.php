<?php

// Return codename that was not found
function check_codename($table, $codename, $codename_column = "codename")
{
    global $Database;

    // Si codename est un tableau, alors on va le parcourir
    if (is_array($codename))
    {
	$not_found = [];
	foreach ($codename as $c)
	{
	    if (($tmp = check_codename($table, $c))->is_error())
		return ($tmp);
	    if (count($tmp->value) != 0)
		$not_found[] = $tmp->value[0];
	}
	return (new ValueResponse($not_found));
    }

    // On regarde si c'est une demande de retrait.
    if (substr($codename, 0, 1) == "-")
	$codename = substr($codename, 1);

    // On va chercher a verifier l'id, verifiant le nom de code au passage
    if (($q = resolve_codename($table, $codename, $codename_column))->is_error())
    {
	if ($q->label == "BadCodeName")
	    return (new ValueResponse([$codename]));
	return ($q);
    }
    return (new ValueResponse([]));
}
