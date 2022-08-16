<?php

function get_prefix($str)
{
    $sub = false;
    $pfx = "";
    $symbol = "";
    $len = strlen($str);
    for ($i = 0; $i < $len; ++$i)
    {
	if (in_array($tmp = substr($str, $i, 1), ["#", "$", "@", "%", "&", "*"]))
	    $pfx .= $tmp;
	else if (substr($str, $i, 1) == "-")
	    $sub = !$sub;
	else if (substr($str, $i, 1) == "+")
	    continue ;
	else
	{
	    $symbol = substr($str, $i);
	    break ;
	}
    }
    return (["prefix" => $pfx, "negative" => $sub, "label" => $symbol, "mod" => ($sub ? "-" : "").$symbol]);
}

