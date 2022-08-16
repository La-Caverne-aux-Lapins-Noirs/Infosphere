<?php

function array_dsearch($needle, $haystack)
{
    if (!is_array($needle))
	$needle = [$needle];
    $fnd = [];
    foreach ($needle as $n)
	if (array_search($n, $haystack) !== false)
	    $fnd[$n] = $n;
    if ($fnd == [])
	return (false);
    return ($fnd);
}
