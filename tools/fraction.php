<?php

function fraction($fraction, $multiplier = 1)
{
    $matches = [];
    if (preg_match('/([0-9]+)\\/([0-9]+)/', $fraction, $matches) !== 1)
    {
	if (preg_match('/([0-9]+)/', $fraction, $matches) !== 1)
	    return (NULL);
	if ($matches[1] > $multiplier)
	    return ($multiplier);
	return ($matches[1]);
    }
    return ($multiplier * ($matches[1] / $matches[2]));
}
