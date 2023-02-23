<?php

function convert_to_codename($str)
{
    $out = "";
    $str = handle_french(strtolower($str));
    for ($i = 0, $len = strlen($str); $i < $len; ++$i)
    {
	if (preg_match('/[a-zA-Z]/', $str[$i]))
	    $out .= strtolower($str[$i]);
    }
    return ($out);
}

