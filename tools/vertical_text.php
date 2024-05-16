<?php

function vertical_text($str)
{
    $len = mb_strlen($str);
    for ($k = 0; $k < $len; ++$k)
    {
	if (($c = mb_substr($str, $k, 1)) == "&")
	{
	    $acc = "&";
	    for ($i = 1;
		$k + $i < $len
		   && ($cc = mb_substr($str, $k + $i, 1)) != ";"
		   && $cc != " "
		;
		++$i)
	        $acc .= $cc;
	    if ($cc == ";")
	    {
		echo "$acc<br />";
		$k += $i;
		continue ;
	    }
	}
	else if ($c == ",")
	    continue ;
	echo "$c<br />";
    }
}
