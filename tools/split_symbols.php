<?php

function split_symbols($x, $c = ";", $negation = true)
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
		$neg = false;
		$lab = false;
		$alt = false;
		do
		{
		    $found = false;
		    if ($negation)
		    {
			if (substr($x[$i], 0, 1) == "-")
			{
			    $neg = true;
			    $found = true;
			    $x[$i] = substr($x[$i], 1);
			}
		    }
		    if (substr($x[$i], 0, 1) == "#") // Supporte les groupes
		    {
			$found = true;
			$x[$i] = substr($x[$i], 1);
			$lab = true;
		    }
		    if (substr($x[$i], 0, 1) == "$") // Supporte l'alterateur $
		    {
			$found = true;
			$x[$i] = substr($x[$i], 1);
			$alt = true;
		    }
		}
		while ($found);
		if (!is_symbol($x[$i]))
		    return (new ErrorResponse("InvalidParameter", $x[$i]));
		if ($x[$i] == "")
		    continue ;
		$new = "";
		if ($neg)
		    $new .= "-";
		if ($lab)
		    $new .= "#";
		if ($alt)
		    $new .= "$";
		$out[] = $new.$x[$i];
	    }
	}
    }
    return (new ValueResponse($out));
}

