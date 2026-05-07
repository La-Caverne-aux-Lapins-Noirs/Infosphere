<?php

function longhash($seed)
{
    $len = "";
    for ($i = 0; $i < 10; ++$i)
    {
	$seed = hash("whirlpool", $len.$seed);
	$len .= $seed;
    }
    return ($len);
}
