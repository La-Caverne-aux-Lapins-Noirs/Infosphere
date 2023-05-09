<?php

if (isset($_POST["start"]) || isset($_GET["start"]))
{
    $start = try_get($_POST, "start", try_get($_GET, "start"));
    $start = date_to_timestamp($start);
}
else
    $start = time();

$start = first_day_of_week($start);
$start = floor(($start / (60 * 60 * 24))) * 60 * 60 * 24;

if (isset($_POST["end"]) || isset($_GET["end"]))
{
    $end = try_get($_POST, "end", try_get($_GET, "end"));
    $end = date_to_timestamp($end);
    $end = first_day_of_week($end) + $one_week - 1;
}
else
{
    $recent = db_select_one("
        first_day
        FROM cycle
        LEFT JOIN user_cycle ON cycle.id = user_cycle.id_cycle
        WHERE user_cycle.id_user = ".$User["id"]."
        ORDER BY first_day DESC
	");
    if ($recent == NULL)
	$end = $start + $one_week - 1;
    else
    {
	$first_day = date_to_timestamp($recent["first_day"]);
	if ($first_day + 14 * $one_week < time())
	    $end = $start + $one_week - 1;
	else
	    $end = $first_day + 14 * $one_week - 1;
    }
}

if ($end < $start)
    $end = $start + $one_week - 1;

$fix = [];
$wlist["cycle"] = [];
$is_filtered = false;
if (isset($_COOKIE["filter_cycle"]) && $_COOKIE["filter_cycle"] != "")
{
    $is_filtered = true;
    $cys = explode("XXXSEPARATORXXX", $_COOKIE["filter_cycle"]);
    foreach ($cys as $c)
    {
	if (($c = resolve_codename("cycle", $c))->is_error())
	    continue ;
	$fix[] = $c->value;
	$wlist["cycle"][] = $c->value;
    }
}
else
{
    foreach ($User["cycle"] as $cycle)
    {
	$wlist["cycle"][] = $cycle["id_cycle"];
	$tmp[] = $cycle["codename"];
    }
}

$wlist["type"] = "";
if (isset($_COOKIE["filter_type"]) && strtolower($_COOKIE["filter_type"]) == "template")
    if (is_teacher())
	$wlist["type"] = "template";

