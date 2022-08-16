<?php

function add_cycle($codename, $year, $first_week, $last_day)
{
    global $Database;

    if (!isset($year))
	return (new ErrorResponse("InvalidCycleNumber"));
    // 5 ans de scolarité + Les modules anciens élèves
    if (!is_number($year) || $year < 0 || $year > 20)
	return (new Response("InvalidCycleNumber", $year));

    if (!isset($first_week))
	return (new ErrorResponse ("InvalidDate"));
    if (!check_date($first_week))
	return (new Response ("InvalidDate", $first_week));

    if (!isset($last_day))
	return (new ErrorResponse ("InvalidDate"));
    if (!check_date($last_day))
	return (new Response ("InvalidDate", $last_day));

    $fields = [
	"cycle" => $year,
	"first_day" => $first_week,
	"last_day" => $last_day
    ];
    return (@try_insert("cycle", $codename, $fields));
}

