<?php

function split_symbols($x, $c = ";", $negation = true, $getmod = false, $addprefix = "", $tolerated_tokens = [])
{
    if (!isset($x))
	return (new ErrorResponse("MissingCodeName"));
    if (strlen($x) == 0)
	return (new ValueResponse([]));
    $x = explode($c, " $x ");
    $out = [];
    foreach ($x as $i => $j)
    {
	if (($x[$i] = trim($j)) != "")
	{
	    if (is_number($x[$i]))
		$out[] = intval($x[$i]);
	    else
	    {
		// On va tolérer certains symboles normalement non supporté
		// afin de permettre - entre autres - l'utilisation de wildcard
		$symbol = $x[$i];
		foreach ($tolerated_tokens as $tok)
		    $symbol = str_replace($tok, "", $symbol);
		// On vérifie que la syntaxe est correcte en considérant
		// les chaines une fois les tokens tolérés retiré
		$symres = get_prefix($symbol, $tolerated_tokens);
		if (!is_integer($symres["label"]) && !is_symbol($symres["label"]))
		    return (new ErrorResponse("InvalidParameter", $x[$i]));

		// On repart sur la chaine originale contenant les tokens supportés
		$res = get_prefix($x[$i], $tolerated_tokens);
		if ($res["label"] == "")
		    continue ;
		$out[] = $getmod ? $res["mod"] : (
		    ($res["negative"] ? "-" : "").
		    $res["prefix"].$addprefix.
		    $res["label"].
		    (count($res["parameters"]) ?
		     "(".implode(",", $res["parameters"]).")" :
		     "")
		);
	    }
	}
    }
    return (new ValueResponse($out));
}

