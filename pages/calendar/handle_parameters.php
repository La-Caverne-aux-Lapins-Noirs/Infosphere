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

if (!isset($User["misc_configuration"]["calendar"]["filter_cycle"])
    || $User["misc_configuration"]["calendar"]["filter_cycle"] == "")
{
    $wlist["cycle"] = [];
    $tmp = [];
    foreach ($User["cycle"] as $cycle)
    {
	$wlist["cycle"][] = $cycle["id"];
	$tmp[] = $cycle["codename"];
    }
    $User["misc_configuration"]["calendar"]["filter_cycle"] = implode(";", $tmp);
}
else
{
    if (($cycle = resolve_codename("cycle", $User["misc_configuration"]["calendar"]["filter_cycle"], "codename", true))->is_error())
	$wlist["cycle"] = [];
    else
    {
	if (!isset(($wlist["cycle"] = $cycle->value)[0]))
	    $wlist["cycle"] = [$wlist["cycle"]];
	$User["misc_configuration"]["calendar"]["filter_cycle"] = [];
	foreach ($wlist["cycle"] as $c)
	{
	    $User["misc_configuration"]["calendar"]["filter_cycle"][] = $c["codename"];
	}
    }
}

if (isset($User["misc_configuration"]["calendar"]["filter_room"]) && $User["misc_configuration"]["calendar"]["filter_room"] != "")
{
    if (($cycle = resolve_codename("room", $User["misc_configuration"]["calendar"]["filter_room"], "codename", true))->is_error())
	$wlist["room"] = [];
    else
    {
	if (!isset(($wlist["room"] = $cycle->value)[0]))
	    $wlist["room"] = [$wlist["room"]];
	$User["misc_configuration"]["calendar"]["filter_room"] = [];
	foreach ($wlist["room"] as $c)
	{
	    //$User["misc_configuration"]["calendar"]["filter_room"][] = $c["codename"];
	}
    }
}


