<?php

function add_cycle($codename, $year, $first_week = NULL)
{
    global $Database;

    if (!isset($year))
	return (new ErrorResponse("InvalidCycleNumber"));
    // 5 ans de scolarité + Les modules anciens élèves
    if (!is_number($year) || $year < 0 || $year > 20)
	return (new Response("InvalidCycleNumber", $year));

    if (@$first_week != NULL && !check_date($first_week))
	return (new Response ("InvalidDate", $first_week));

    $fields = [
	"cycle" => $year,
	"first_day" => $first_week,
	"is_template" => $first_week === NULL ? 1 : 0
    ];
    return (@try_insert("cycle", $codename, $fields));
}

