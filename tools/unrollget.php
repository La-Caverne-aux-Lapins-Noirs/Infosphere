<?php

function unrollget($add = [])
{
    global $_GET;

    $get = $_GET;
    $x = [];
    foreach ($add as $k => $v)
    {
	if ($v == NULL)
	    unset($get[$k]);
	else
	    $get[$k] = $v;
    }
    foreach ($get as $k => $v)
    {
	$x[] = "$k=$v";
    }
    $x = implode("&amp;", $x);
    return ($x);
}

