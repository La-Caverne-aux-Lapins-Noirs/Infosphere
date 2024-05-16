<?php

$LoadedUserPromotions = [];

function get_user_promotions(array &$usr, $by_name = false)
{
    global $Database;
    global $Language;
    global $one_week;
    global $LoadedUserPromotions;

    if (!isset($usr["id"]) || !is_number($usr["id"]))
	return ([]);
    if (isset($usr["cycle"]))
	return ($usr["cycle"]);
    if (isset($LoadedUserPromotions[$usr["id"]]))
    {
	$usr["cycle"] = $LoadedUserPromotions[$usr["id"]]["cycle"];
	$usr["greatest_cycle"] = $LoadedUserPromotions[$usr["id"]]["greatest_cycle"];
	return ($usr["cycle"]);
    }
    $forge = "
        cycle.id as id_cycle,
        cycle.{$Language}_name as name,
        cycle.*,
        user_cycle.hidden as hidden,
        user_cycle.cursus as cursus,
        user_cycle.id as id,
        user_cycle.id as id_user_cycle
        FROM cycle
        LEFT JOIN user_cycle
        ON user_cycle.id_cycle = cycle.id
        WHERE user_cycle.id_user = ".$usr["id"]."
        AND deleted IS NULL
	ORDER BY cycle.cycle DESC
	";
    $usr["cycle"] = db_select_all($forge, $by_name ? "codename" : "");
    $usr["greatest_cycle"] = -1;
    $usr["cycle_authority"] = 0;
    foreach ($usr["cycle"] as $i => $v)
    {
	$usr["cycle"][$i]["commentaries"] = db_select_one("
            * FROM comment WHERE id_misc = {$v["id_user_cycle"]} AND misc_type = 2
            AND deleted IS NULL
            ORDER BY comment_date DESC
	    ");
	$usr["cycle"][$i]["cursus"] = explode(";", $v["cursus"]);
	
	$usr["cycle"][$i]["last_day"] = date_to_timestamp($v["first_day"]) + 15 * $one_week;
	$usr["cycle"][$i]["year"] = floor($v["cycle"] / 4); // Fait ici car SQLite n'a pas FLOOR.
	if ($usr["greatest_cycle"] < $v["cycle"])
	{
	    $usr["greatest_cycle"] = $v["cycle"];
	    $usr["greatest_cycle_data"] = $v;
	}

	$id = $usr["id"];
	$auths = db_select_all("
	    cycle_teacher.id_user as id_user,
            user_laboratory.authority as authority
	    FROM cycle_teacher
	    LEFT JOIN user_laboratory
	    ON user_laboratory.id_laboratory = cycle_teacher.id_laboratory
	    WHERE ( cycle_teacher.id_user = $id
	    OR user_laboratory.id_user = $id
	    ) AND id_cycle = {$v["id"]}
	    ");
	foreach ($auths as $auth)
	{
	    if ($auth["authority"] == 1)
		$usr["cycle"][$i]["authority"] = 1; // assistant
	    if ($auth["authority"]  > 1)
		$usr["cycle"][$i]["authority"] = 2; // teacher
	    if ($auth["id_user"] != NULL)
		$usr["cycle"][$i]["authority"] = 2; // teacher
	    
	    if ($usr["cycle"][$i]["authority"] > $usr["cycle_authority"])
		$usr["cycle_authority"] = $usr["cycle"][$i]["authority"];
	}
    }
    
    $LoadedUserPromotions[$usr["id"]]["cycle"] = &$usr["cycle"];
    $LoadedUserPromotions[$usr["id"]]["greatest_cycle"] = &$usr["greatest_cycle"];
    return ($usr["cycle"]);
}

