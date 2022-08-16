<?php

function resolve_path($path)
{
    $dirs = explode("/", str_replace("//", "/", $path));
    $path = [];
    foreach ($dirs as $d)
    {
        if ($d == ".")
	    continue;
        if ($d == "..")
            array_pop($path);
	else
            $path[] = $d;
    }
    return (implode("/", $path));
}
