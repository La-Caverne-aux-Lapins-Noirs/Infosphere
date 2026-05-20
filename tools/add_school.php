<?php

function add_school($codename, $icon, $lng)
{
    global $Configuration;

    $phone = $lng["phone"] ?? "";
    $legal_name = $lng["legal_name"] ?? $codename;
    $mail = $lng["mail"] ?? "";

    if ($mail != "" && filter_var($mail, FILTER_VALIDATE_EMAIL) === false)
	return (new ErrorResponse("BadMail"));

    $fields = [
	"phone" => $phone,
	"legal_name" => $legal_name,
	"address" => $lng["address"] ?? "",
	"mail" => $mail,
	"main_info" => $lng["main_info"] ?? "",
	"school_info" => $lng["school_info"] ?? "",
	"formation_info" => $lng["formation_info"] ?? "",
	"alternation_info" => $lng["alternation_info"] ?? "",
    ];

    if (($ret = @try_insert(
	"school",
	$codename,
	$fields,
	$icon,
	$Configuration->SchoolsDir($codename),
	["name"],
	$lng
    ))->is_error())
	return ($ret);

    if (($refresh = refresh_school($ret->value["id"]))->is_error())
	return ($refresh);

    return ($ret);
}

