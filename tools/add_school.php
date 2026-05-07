<?php

function add_school($codename, $icon, $lng)
{
    global $Configuration;

    $phone = $lng["phone"] ?? "";
    $legal_name = $lng["legal_name"] ?? $codename;
    
    $mail = $lng["mail"] ?? "";
    if ($mail != "" && filter_var($mail, FILTER_VALIDATE_EMAIL) === false)
	return (new ErrorResponse("BadMail"));

    $address = $lng["main_info"] ?? "";
    $main_info = $lng["main_info"] ?? "";
    $school_info = $lng["school_info"] ?? "";
    $formation_info = $lng["formation_info"] ?? "";
    $alternation_info = $lng["alternation_info"] ?? "";

    $fields = [
	"phone" => $phone,
	"legal_name" => $legal_name,
	"address" => $address,
	"mail" => $mail,
	"main_info" => $main_info,
	"school_info" => $school_info,
	"formation_info" => $formation_info,
	"alternation_info" => $alternation_info,
    ];
    
    if (($ret = @try_insert(
	"school", $codename, $fields, $icon, $Configuration->SchoolsDir($codename), ["name"], $lng
    ))->is_error())
        return ($ret);
    
    $dabsic = [
	"Company" => [
	    "Name" => $lng["fr_name"],
	    "LegalName" => $lng["fr_name"],
	    "Address" => $address,
	    "Phone" => $phone,
	    "Mail" => $mail,
	    "Main" => $main_info,
	    "School" => $school_info,
	    "Formation" => $formation_info,
	    "Alternation" => $alternation_info,
	    "Logo" => $Configuration->SchoolsDir($codename)."icon.png",
	]
    ];

    $from = $Configuration->SchoolsDir($codename)."configuration.json";
    $to = $Configuration->SchoolsDir($codename)."configuration.dab";
    file_put_contents($from, json_encode($dabsic, JSON_UNESCAPED_SLASHES));
    shell_exec("mergeconf -i $from -o $to --resolve");
    unlink($from);
    return ($ret);
}

