<?php

function check_promotions(&$dat)
{
    foreach ($dat as $k => $v)
    {
	if (is_number($id = resolve_codename("cycle", $k)))
	    $dat[$k]["id"] = $id; // La promo existe
	else if ($id == "NotAnId")
	    return (new Response("InvalidEntry", "[].$k")); // Il y a une erreur de syntaxe
	else if (!is_number(@$v["cycle"]))
	    return (new Response("MissingField", "[].$k.cycle"));
	else if (!check_date(@$v["first_day"]))
	    return (new Response("MissingField", "[].$k.first_day"));
	foreach ($v["user"] as $i => $vs)
	{
	    if (!is_number($id = resolve_codename("user", $vs)))
		return (new Response("UnknownLogin", "[].$k.user[$i]=$vs")); // Un eleve n'existe pas
	    $dat[$k][$i]["id"] = $id;
	}
    }
    return (new Response());
}

function import_promotion($file)
{
    global $Database;

    if (($dat = load_configuration($file))->is_error())
	return ($dat);
    $dat = $dat->value;
    if (($err = check_promotions($dat))->is_error())
	return ($err);

    foreach ($dat as $k => $v)
    {
	if (!isset($v["id"]))
	{
	    if ($Database->query("
              INSERT INTO cycle (codename, first_day, cycle)
              VALUES ('$k', '".$v["first_day"]."', '".$v["cycle"]."')
              ") == false)
	        return (new Response("CannotAdd", "[].$k")); // @codeCoverageIgnore
	    $v["id"] = $Database->insert_id;
	}

	foreach ($v["user"] as $vt)
	{
	    if (strval($err = add_link($vt, $v["id"], "user", "cycle")) != "")
		return ($err); // @codeCoverageIgnore
	}
    }
    return (new Response());
}

