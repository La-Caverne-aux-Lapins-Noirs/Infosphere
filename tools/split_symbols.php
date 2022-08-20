<?php

/*
** Il serait interessant d'utiliser get_prefix.
*/

function split_symbols($x, $c = ";", $negation = true, $getmod = false, $addprefix = "")
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
		$res = get_prefix($x[$i]);
		if (!is_integer($res["label"]) && !is_symbol($res["label"]))
		    return (new ErrorResponse("InvalidParameter", $x[$i]));
		if ($res["label"] == "")
		    continue ;
		$out[] = $getmod ? $res["mod"] : (
		    ($res["negative"] ? "-" : "").
		    $res["prefix"].$addprefix.
		    $res["label"]
		);
	    }
	}
    }
    return (new ValueResponse($out));
}

