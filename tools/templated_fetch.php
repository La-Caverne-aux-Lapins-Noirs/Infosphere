<?php

function templated_fill($table, &$data, $blist = [])
{
    global $Language;

    if (($tem = db_select_one("
        *
        FROM $table
        WHERE id = {$data["id_template"]}")
    ) == NULL)
        return ($data); // Il n'y a pas de modèle, on renvoit la donnée comme ca du coup
    foreach ($data as $i => &$d)
    {
	if (array_search($i, $blist) !== false)
	    continue ;
	if ($d == NULL && isset($tem[$i]))
	    $d = $tem[$i];
    }
    return ($data);
}


function templated_fetch($id, $table, $deleted = true)
{
    // On a une instance A bati sur le modele B.
    // Si un champ de A est vide, alors on prend la valeur de B.

    if ($deleted)
	$deleted = " AND deleted = 0 ";
    else
	$deleted = "";

    if (($ret = resolve_codename($table, $id))->is_error())
	return (NULL);
    $id = $ret->value;

    if (($dat = db_select_one("* FROM $table WHERE id = $id $deleted")) == NULL)
	return (NULL);
    return (templated_fill($table, $dat));
}

function templated_fetch_all($table, $deleted = true)
{
    if ($deleted)
	$deleted = " WHERE deleted = 0 ";
    else
	$deleted = "";

    if (($dat = db_select_all("* FROM $table $deleted")) == NULL)
	return (NULL);
    foreach ($dat as &$d)
    {
	templated_fill($table, $d);
    }
    return ($dat);
}
