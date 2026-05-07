<?php

function get_docs($filter = NULL)
{
    global $Configuration;

    $docs = [];
    $files = glob($Configuration->DocDir($filter)."*.dab");
    foreach ($files as $file)
    {
	$dir = pathinfo($file, PATHINFO_FILENAME);
	$subs = glob($Configuration->DocDir()."$dir/*.*");
	foreach ($subs as $sub)
	    $docs[
		substr($file, strlen($Configuration->DocDir()))
	    ][] = substr($sub, strlen($Configuration->DocDir()));
    }
    return ($docs);
}
