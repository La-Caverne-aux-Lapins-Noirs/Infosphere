<?php

function all_configuration_files($dir)
{
    $list = [];
    foreach (scandir($dir) as $i)
    {
	if ($i[0] == ".")
	    continue ;
	if (is_dir("$dir/$i"))
	    $list = array_merge($list, all_configuration_files("$dir/$i"));
	else if (in_array(pathinfo($i, PATHINFO_EXTENSION), ["dab", "ini", "json"]))
	    $list[] = "$dir/$i";
    }
    return ($list);
}
