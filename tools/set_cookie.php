<?php

function set_cookie($key, $val, $expire = NULL)
{
    if ($expire == NULL)
	$expire = now();
    $opt = [
	"expires" => $expire,
	"path" => "/",
	"samesite" => "Strict"
    ];
    setcookie($key, $val, $opt);
}

