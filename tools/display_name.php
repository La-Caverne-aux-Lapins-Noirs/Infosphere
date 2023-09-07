<?php

function display_name($stuff, $admin = false)
{
    ob_start();
    if (@strlen($stuff["name"]))
    {
	echo $stuff["name"];
	if ($admin)
	    echo "(".$stuff["codename"].") #".$stuff["id"];
	return (ob_get_clean());
    }
    echo $stuff["codename"];
    return (ob_get_clean());
}

