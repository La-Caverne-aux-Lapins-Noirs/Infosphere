<?php

function period($start, $end, $tstamp = NULL)
{
    if ($tstamp == NULL)
	$tstamp = now();
    if ($start == NULL)
	$start = -INF;
    else if (!is_number($start))
	$start = date_to_timestamp($start);

    if ($end == NULL)
	$end = INF;
    else if (!is_number($end))
	$end = date_to_timestamp($end);
    return ($start <= $tstamp && $tstamp <= $end);
}

