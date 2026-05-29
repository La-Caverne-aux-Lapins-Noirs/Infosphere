<?php

function refresh_user_value($user, $fields, $default = "")
{
    if (!is_array($fields))
	$fields = [$fields];
    foreach ($fields as $field)
	if (array_key_exists($field, $user) && $user[$field] !== NULL)
	    return ($user[$field]);
    return ($default);
}

function refresh_user_bool($user, $fields, $default = false)
{
    $value = refresh_user_value($user, $fields, $default);

    if (is_bool($value))
	return ($value);
    if (is_numeric($value))
	return ((int)$value != 0);
    if (is_string($value))
    {
	$value = strtolower(trim($value));
	return (!in_array($value, ["", "0", "false", "no", "off", "non"]));
    }
    return (!!$value);
}

function refresh_user_date($user, $fields)
{
    $value = refresh_user_value($user, $fields, "");
    if ($value === "" || $value === NULL)
	return ("");
    return (human_date($value, true));
}

function refresh_user_first_name($user)
{
    $name = trim(refresh_user_value($user, "first_name", ""));
    if ($name == "")
	return ("");
    if (function_exists("mb_convert_case"))
	return (mb_convert_case($name, MB_CASE_TITLE, "UTF-8"));
    return (ucwords(strtolower($name)));
}

function refresh_user_family_name($user)
{
    $name = trim(refresh_user_value($user, "family_name", ""));
    if ($name == "")
	return ("");
    if (function_exists("mb_strtoupper"))
	return (mb_strtoupper($name, "UTF-8"));
    return (strtoupper($name));
}

function refresh_user_clean_dabsic_values($data)
{
    foreach ($data as $key => $value)
    {
	if (is_array($value))
	    $data[$key] = refresh_user_clean_dabsic_values($value);
	else if ($value === NULL)
	    $data[$key] = "";
    }
    return ($data);
}

function refresh_user_fields($user)
{
    return ([
	"first_name" => refresh_user_first_name($user),
	"use_name" => refresh_user_value($user, "use_name", ""),
	"family_name" => refresh_user_family_name($user),
	"gender" => refresh_user_value($user, ["gender", "sex"], ""),
	"mail" => refresh_user_value($user, "mail", ""),
	"phone" => refresh_user_value($user, "phone", ""),
	"address" => refresh_user_value($user, ["address", "street_name"], ""),
	"city" => refresh_user_value($user, "city", ""),
	"postal_code" => refresh_user_value($user, "postal_code", ""),
	"birth_date" => refresh_user_date($user, "birth_date"),
	"birth_city" => refresh_user_value($user, "birth_city", ""),
	"birth_country" => refresh_user_value($user, "birth_country", ""),
	"nationality" => refresh_user_value($user, "nationality", ""),

	"ine" => refresh_user_value($user, ["ine", "ìne"], ""),
	"nir" => refresh_user_value($user, "nir", ""),
	"handicap" => refresh_user_bool($user, "handicap", false),
	"handicap_kind" => refresh_user_value($user, "handicap_kind", ""),
	"resubscribe" => refresh_user_bool($user, "resubscribe", false),
	"last_class" => refresh_user_value($user, "last_class", ""),
	"last_class_success" => refresh_user_bool($user, "last_class_success", false),

	"school_period" => refresh_user_value($user, "school_period", ""),
	"chosen_class" => refresh_user_value($user, "chosen_class", ""),
	"month" => refresh_user_value($user, "month", ""),
	"other_month_day" => refresh_user_value($user, "other_month_day", ""),
	"day" => refresh_user_value($user, "day", ""),
	"chosen_specialty" => refresh_user_value($user, "chosen_specialty", ""),

	"is" => refresh_user_value($user, "is", ""),
	"send_school_report" => refresh_user_bool($user, "send_school_report", false),
	"intranet_access" => refresh_user_bool($user, "intranet_access", false),

	"is" => refresh_user_value($user, "is", ""),
	"first_name" => refresh_user_first_name($user),
	"family_name" => refresh_user_family_name($user),
	"mail" => refresh_user_value($user, "mail", ""),
	"phone" => refresh_user_value($user, "phone", ""),
	"address" => refresh_user_value($user, ["address", "street_name"], ""),
    ]);
}

function refresh_user($user, $file = NULL, array $extra = [])
{
    global $Configuration;

    if (is_array($user))
	return (new ErrorResponse("InvalidParameter"));
    if (($ret = resolve_codename("user", $user, "codename", true))->is_error())
	return ($ret);
    $user = $ret->value;
    if ($file === NULL)
    {
	if (!isset($user["codename"]) || $user["codename"] == "")
	    return (new ErrorResponse("MissingCodeName"));
	$file = $Configuration->UsersDir($user["codename"])."admin/identity.dab";
    }
    $target = "user";
    $fields = refresh_user_fields($user);
    $fields = array_merge($fields, $extra);
    $fields = refresh_user_clean_dabsic_values($fields);
    return (generate_dabsic([
	$target => $fields
    ], $file));
}

