<?php

function _get_docs($start)
{
    global $Configuration;

    $dab = [];
    foreach (glob("$start*") as $d)
    {
	if (pathinfo($d, PATHINFO_EXTENSION) == "dab")
	    $dab[] = $d;
	if (is_dir($d))
	    $dab = array_merge($dab, _get_docs("$d/"));
    }
    return ($dab);
}

function get_docs()
{
    global $Configuration;
    
    $dab = _get_docs($Configuration->DocDir());
    foreach ($dab as &$d)
	$d = substr($d, strlen($Configuration->DocDir()));
    return ($dab);
}

