<?php

function sort_by_month($session, $dat = "begin_date")
{
    $m = [];
    foreach ($session as $x)
    {
	$m
	[date("Y", date_to_timestamp($x[$dat]))]
	[date("n", date_to_timestamp($x[$dat]))]
	[] = $x;
    }
    return ($m);
}
