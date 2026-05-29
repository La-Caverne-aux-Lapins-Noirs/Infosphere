<?php

function refresh_school_logo_path($school)
{
    global $Configuration;

    $root = dirname(__DIR__);
    $codename = $school["codename"] ?? "";
    $candidates = [];
    if ($codename != "")
    {
	foreach (["icon.png", "icon.jpg", "icon.jpeg", "logo.png", "logo.jpg", "logo.jpeg", "logo.pdf"] as $name)
	    $candidates[] = $Configuration->SchoolsDir($codename).$name;
    }
    foreach (["res/logo.png", "res/logo.jpg", "res/no_avatar_lab.png"] as $name)
	$candidates[] = $name;

    foreach ($candidates as $candidate)
    {
	$absolute = $candidate;
	if ($absolute != "" && $absolute[0] != "/")
	    $absolute = $root."/".$absolute;
	if (file_exists($absolute) && !is_dir($absolute))
	    return ($absolute);
    }
    return ("");
}

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

	    "logo" => refresh_school_logo_path($school),
	    "logo_width" => "3cm",
	    "logo_height" => "2cm",
	]
    ];

    return (generate_dabsic(
	$dabsic,
	$Configuration->SchoolsDir($school["codename"])."identity.dab"
    ));
}

