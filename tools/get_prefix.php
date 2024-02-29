<?php

function get_prefix($str, $removed_prefix = [])
{
    $prefixes = ["#", "$", "@", "%", "&", "*"];
    foreach ($removed_prefix as $rp)
	if (($k = array_search($rp, $prefixes)) !== false)
	    unset($prefixes[$k]);
    $sub = false;
    $pfx = "";
    $symbol = "";
    $len = strlen($str);
    $parameters = [];
    for ($i = 0; $i < $len; ++$i)
    {
	if (in_array($tmp = substr($str, $i, 1), $prefixes))
	    $pfx .= $tmp;
	else if (substr($str, $i, 1) == "-")
	    $sub = !$sub;
	else if (substr($str, $i, 1) == "+")
	    continue ;
	else
	{
	    for ($j = $i; $j < $len && substr($str, $j, 1) != '('; ++$j);
	    $symbol = substr($str, $i, $j - $i);
	    if ($j < $len)
	    {
		for ($k = $j; $k < $len && $str[$k] != ')'; ++$k);
		if ($k == $len)
		    continue ;
		$parameters = substr($str, $j + 1, $k - 1 - $j);
		$parameters = explode(",", $parameters);
		foreach ($parameters as $k => $v)
		    $parameters[$k] = trim($v);
	    }
	    break ;
	}
    }
    return (["prefix" => $pfx, "negative" => $sub, "label" => $symbol, "mod" => ($sub ? "-" : "").$symbol, "parameters" => $parameters]);
}

