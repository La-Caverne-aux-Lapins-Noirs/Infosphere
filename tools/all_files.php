<?php

function _all_files($dir, $ext)
{
    $list = [];
    foreach (scandir($dir) as $i)
    {
	if ($i[0] == ".")
	    continue ;
	if (is_dir("$dir/$i"))
	    $list = array_merge($list, _all_files("$dir/$i", $ext));
	else if (in_array(pathinfo($i, PATHINFO_EXTENSION), $ext))
	    $list[] = "$dir/$i";
    }
    return ($list);
}

function all_files($dir, $ext)
{
    $list = _all_files($dir, $ext);
    foreach ($list as &$l)
    {
	$l = substr($l, strlen($dir));
	if ($l[0] == "/")
	    $l = substr($l, 1);
    }
    sort($list);
    return ($list);
}

function all_configuration_files($dir)
{
    return (all_files($dir, ["dab", "ini", "json"]));
}

function all_font_files($dir)
{
    return (all_files($dir, ["ttf", "woff2"]));
}

function all_picture_files($dir)
{
    return (all_files($dir, ["png", "jpg", "jpeg"]));
}

