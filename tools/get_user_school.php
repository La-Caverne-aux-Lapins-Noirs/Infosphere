<?php

function get_user_school(array &$usr, $by_name = false)
{
    global $Database;
    global $Language;

    if (!isset($usr["id"]) || !is_number($usr["id"]))
	return ([]);
    if (isset($usr["school"]))
	return ($usr["school"]);
    $forge = "
        school.id as id_school,
	school.codename as codename,
	school.{$Language}_name as name,
        user_school.authority as authority,
        user_school.id as id
        FROM school
        LEFT JOIN user_school
        ON user_school.id_school = school.id
        WHERE user_school.id_user = ".$usr["id"]." AND school.deleted IS NULL
	";
    $usr["school"] = db_select_all($forge, $by_name ? "codename" : "");
    return ($usr["school"]);
}

