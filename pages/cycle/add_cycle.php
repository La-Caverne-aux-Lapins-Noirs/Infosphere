<?php

function add_cycle($codename, $year, $first_week)
{
    global $Database;

    if (!isset($year))
	return (new ErrorResponse("InvalidCycleNumber"));
    // 5 ans de scolarité + Les modules anciens élèves
    if (!is_number($year) || $year < 0 || $year > 20)
	return (new Response("InvalidCycleNumber", $year));

    print_r($first_week);
    if (!isset($first_week))
	return (new ErrorResponse ("InvalidDate"));
    if (!check_date($first_week))
	return (new Response ("InvalidDate", $first_week));

    $fields = [
	"cycle" => $year,
	"first_day" => $first_week
    ];
    return (@try_insert("cycle", $codename, $fields));
}

