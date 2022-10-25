<?php

function get_user_promotions(array &$usr, $by_name = false)
{
    global $Database;
    global $Language;
    global $one_week;

    if (!isset($usr["id"]) || !is_number($usr["id"]))
	return ([]);
    if (isset($usr["cycle"]))
	return ($usr["cycle"]);
    $forge = "
        cycle.id as id_cycle,
        cycle.{$Language}_name as name,
        cycle.*,
        user_cycle.commentaries as commentaries,
        user_cycle.hidden as hidden,
        user_cycle.id as id
        FROM cycle
        LEFT JOIN user_cycle
        ON user_cycle.id_cycle = cycle.id
        WHERE user_cycle.id_user = ".$usr["id"]." AND deleted IS NULL
	ORDER BY cycle.cycle DESC
	";
    $usr["cycle"] = db_select_all($forge, $by_name ? "codename" : "");
    $usr["greatest_cycle"] = -1;
    foreach ($usr["cycle"] as $i => $v)
    {
	$usr["cycle"][$i]["last_day"] = date_to_timestamp($usr["cycle"][$i]["first_day"]) + 15 * $one_week;
	$usr["cycle"][$i]["year"] = floor($v["cycle"] / 4); // Fait ici car SQLite n'a pas FLOOR.
	if ($usr["greatest_cycle"] < $v["cycle"])
	    $usr["greatest_cycle"] = $v["cycle"];
    }
    return ($usr["cycle"]);
}

