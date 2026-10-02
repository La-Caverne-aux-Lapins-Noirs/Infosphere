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

function refresh_user_mail($user)
{
    $mail = trim((string)refresh_user_value($user, "mail", ""));
    // `nomail` est uniquement un marqueur de saisie administratif. Une
    // ancienne ligne ayant conservé ce marqueur doit être vue partout comme
    // une absence d'adresse, notamment par les contextes DocBuilder.
    if (strcasecmp($mail, "nomail") == 0)
        return ("");
    return ($mail);
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
    $administrative = function_exists("user_identity_student_administrative_fields")
        ? user_identity_student_administrative_fields($user) : [];
    $source = is_array($administrative) ? array_merge($user, $administrative) : $user;
    $fields = [
	"first_name" => refresh_user_first_name($source),
	"use_name" => refresh_user_value($source, "use_name", ""),
	"family_name" => refresh_user_family_name($source),
	"gender" => refresh_user_value($source, ["gender", "sex"], ""),
	"mail" => refresh_user_mail($source),
	"courriel" => refresh_user_mail($source),
	"phone" => refresh_user_value($source, "phone", ""),
	"address" => refresh_user_value($source, ["address", "street_name"], ""),
	"city" => refresh_user_value($source, "city", ""),
	"postal_code" => refresh_user_value($source, "postal_code", ""),
	"birth_date" => refresh_user_date($source, "birth_date"),
	"birth_city" => refresh_user_value($source, "birth_city", ""),
	"birth_place" => refresh_user_value($source, ["birth_place", "birth_city"], ""),
	"birth_country" => refresh_user_value($source, "birth_country", ""),
	"nationality" => refresh_user_value($source, "nationality", ""),

	"ine" => refresh_user_value($source, ["ine", "ine"], ""),
	"nir" => refresh_user_value($source, "nir", ""),
	"handicap" => refresh_user_bool($source, "handicap", false),
	"handicap_kind" => refresh_user_value($source, "handicap_kind", ""),
	"resubscribe" => refresh_user_bool($source, "resubscribe", false),
	"last_class" => refresh_user_value($source, "last_class", ""),
	"last_class_success" => refresh_user_bool($source, "last_class_success", false),

	"school_period" => refresh_user_value($source, "school_period", ""),
	"chosen_class" => refresh_user_value($source, "chosen_class", ""),
	"month" => refresh_user_value($source, "month", ""),
	"other_month_day" => refresh_user_value($source, "other_month_day", ""),
	"day" => refresh_user_value($source, "day", ""),
	"chosen_specialty" => refresh_user_value($source, "chosen_specialty", ""),

	"is" => refresh_user_value($source, "is", ""),
	"send_school_report" => refresh_user_bool($source, "send_school_report", false),
	"intranet_access" => refresh_user_bool($source, "intranet_access", false),

	"is" => refresh_user_value($source, "is", ""),
	"first_name" => refresh_user_first_name($source),
	"family_name" => refresh_user_family_name($source),
	"mail" => refresh_user_mail($source),
	"courriel" => refresh_user_mail($source),
	"phone" => refresh_user_value($source, "phone", ""),
	"address" => refresh_user_value($source, ["address", "street_name"], ""),
	"jury" => (user_profile_status($user["profile_status"] ?? "") == "jury"),
    ];

    if (function_exists("jury_user_title_context") && isset($user["id"]))
	$fields = array_merge($fields, jury_user_title_context($user["id"]));
    return ($fields);
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
	$file = $Configuration->UsersDir($user["codename"])."admin/description.dab";
    }
    $fields = refresh_user_fields($user);
    $fields = array_merge($fields, $extra);
    $identity = trim(($fields["first_name"] ?? "")." ".($fields["family_name"] ?? ""));
    if ($identity == "")
	$identity = $user["codename"] ?? "";
    if (!isset($fields["identity"]) || $fields["identity"] == "")
	$fields["identity"] = $identity;
    if (!isset($fields["name"]) || $fields["name"] == "")
	$fields["name"] = $identity;
    if (!isset($fields["street"]) || $fields["street"] == "")
	$fields["street"] = $fields["address"] ?? "";
    if (!isset($fields["postal_city"]) || $fields["postal_city"] == "")
	$fields["postal_city"] = trim(($fields["postal_code"] ?? "")." ".($fields["city"] ?? ""));
    $fields = refresh_user_clean_dabsic_values($fields);
    return (generate_dabsic($fields, $file));
}

