<?php

function unrollget($add = [], $js = false)
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
    if ($js == false)
	$x = implode("&amp;", $x);
    else
	$x = implode("&", $x);
    return ($x);
}

