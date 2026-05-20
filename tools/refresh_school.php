<?php

function refresh_school($school)
{
    global $Configuration;

    if (!is_array($school))
    {
	$ret = fetch_school($school);
	if (is_object($ret) && $ret->is_error())
	    return ($ret);
	$school = $ret;
    }
    if (!isset($school["codename"]))
	return (new ErrorResponse("MissingCodeName"));

    $name = $school["fr_name"] ?? ($school["name"] ?? $school["codename"]);
    $legal_name = $school["legal_name"] ?? $name;

    $main_info = $school["main_info"] ?? "";
    $school_info = $school["school_info"] ?? "";
    $formation_info = $school["formation_info"] ?? "";
    $alternation_info = $school["alternation_info"] ?? "";

    $dabsic = [
	"company" => [
	    "name" => $name,
	    "legal_name" => $legal_name,
	    "address" => $school["address"] ?? "",
	    "phone" => $school["phone"] ?? "",
	    "mail" => $school["mail"] ?? "",

	    "main_info" => $main_info,
	    "school_info" => $school_info,
	    "formation_info" => $formation_info,
	    "alternation_info" => $alternation_info,

	    // Compatibilité avec les anciens noms courts.
	    "main" => $main_info,
	    "school" => $school_info,
	    "formation" => $formation_info,
	    "alternation" => $alternation_info,

	    "logo" => $Configuration->SchoolsDir($school["codename"])."icon.png",
	]
    ];

    return (generate_dabsic(
	$dabsic,
	$Configuration->SchoolsDir($school["codename"])."identity.dab"
    ));
}

