<?php

function default_val(&$out, $f, $l, $def = NULL)
{
    if (!isset($f[$l]))
    {
	if (func_num_args() == 3)
	    return ;
	$f[$l] = $def;
    }
    $out[$l] = $f[$l];
}

function default_date_val(&$out, $f, $l, $def)
{
    if (!isset($f[$l]) || date_to_timestamp($f[$l]) == 0)
	$f[$l] = $def;
    $out[$l] = $f[$l];
}

function default_int_val(&$out, $f, $l, $def)
{
    if (!isset($f[$l]) || !is_number($f[$l]))
	$f[$l] = $def;
    $out[$l] = $f[$l];
}

function default_string_val(&$out, $f, $l, $def)
{
    if (!isset($f[$l]) || !is_string($f[$l]))
	$f[$l] = $def;
    $out[$l] = $f[$l];
}

function default_bool_val(&$out, $f, $l, $def)
{
    if (!isset($f[$l]))
	$f[$l] = $def;
    $t = $f[$l];
    if (!is_number($f[$l]))
	$t = $def;
    if ($f[$l] === "on")
	$t = 1;
    if ($f[$l] === "")
	$t = 0;
    $out[$l] = $f[$l] = $t;
}


