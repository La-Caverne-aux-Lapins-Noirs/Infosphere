<?php

function by_codename($arr)
{
    $new = [];
    foreach ($arr as $v)
    {
	$new[$v["codename"]] = $v;
    }
    return ($new);
}
