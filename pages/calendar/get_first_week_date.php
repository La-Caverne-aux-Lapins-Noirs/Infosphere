<?php

function get_first_week_date()
{
    global $Database;
    global $User;

    if (!isset($User["cycle"]))
    {
	// Set une date automatiquement a maintenant si aucun cycle n'est rataché
	return (db_form_date(now()));
    }

    $oldest_active = NULL;
    foreach ($User["cycle"] as $i => $v)
    {
	if ($v["done"] != 0)
	    continue ;
	if ($oldest_active == NULL)
	    $oldest_active = $v;
	if (date_to_timestamp($oldest_active["first_day"]) > date_to_timestamp($v["first_day"]))
	    $oldest_active = $v;
    }
    if ($oldest_active == NULL)
    {
	// Set une date automatiquement a maintenant
	return (db_form_date(now()));
    }

    return ($oldest_active["first_day"]);
}

$first_week_date = strtotime(get_first_week_date());
