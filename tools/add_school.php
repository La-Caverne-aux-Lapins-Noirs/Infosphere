<?php

require_once (__DIR__."/school_activity.php");

function add_school($codename, $icon, $lng)
{
    global $Configuration;
    global $Database;

    $organization = trim((string)($lng["organization_codename"] ?? ($lng["organization"] ?? "")));
    if ($organization == "")
        return (new ErrorResponse("MissingCodeName", "organization"));
    if (($organization = resolve_codename("organization", $organization, "codename", true))->is_error())
        return ($organization);
    $organization = $organization->value;
    $id_organization = (int)$organization["id"];

    $school_mail = trim((string)($lng["mail"] ?? ($lng["school_mail"] ?? "")));
    if ($school_mail != "" && filter_var($school_mail, FILTER_VALIDATE_EMAIL) === false)
        return (new ErrorResponse("BadMail"));

    $base_url = school_base_url_normalize($lng["base_url"] ?? "");
    if ($base_url === false)
        return (new ErrorResponse("InvalidSchoolBaseUrl"));
    if ($base_url != "" && !school_base_url_is_available($base_url))
        return (new ErrorResponse("SchoolBaseUrlAlreadyUsed"));

    $activity = school_activity_updates($lng);
    if ($activity->is_error())
        return $activity;
    $fields = [
        "id_organization" => $id_organization,
        "base_url" => $base_url == "" ? NULL : $base_url,
        "address" => trim((string)($lng["address"] ?? ($lng["school_address"] ?? ""))),
        "uai" => trim((string)($lng["uai"] ?? "")),
        "cfa_name" => trim((string)($lng["cfa_name"] ?? "")),
        "executing_establishment_name" => trim((string)($lng["executing_establishment_name"] ?? "")),
        "phone" => trim((string)($lng["phone"] ?? ($lng["school_phone"] ?? ""))),
        "mail" => $school_mail,
        "school_registration_number" => trim((string)($lng["school_registration_number"] ?? "")),
        "school_registration_academy" => trim((string)($lng["school_registration_academy"] ?? "")),
        "formation_activity_number" => trim((string)($lng["formation_activity_number"] ?? "")),
        "formation_activity_region" => trim((string)($lng["formation_activity_region"] ?? "")),
        "alternation_registration_number" => trim((string)($lng["alternation_registration_number"] ?? "")),
        "alternation_registration_academy" => trim((string)($lng["alternation_registration_academy"] ?? "")),
    ];

    $fields = array_merge($fields, school_activity_flags($fields), $activity->value);

    if (($ret = @try_insert(
        "school",
        $codename,
        $fields,
        $icon,
        $Configuration->SchoolsDir($codename),
        [],
        [],
        "codename",
        true,
        true
    ))->is_error())
        return ($ret);

    $document_logo = $lng;
    unset($document_logo["icon"]);
    unset($document_logo["site_logo"]);
    if (($logo_update = school_update_logos($codename, $document_logo))->is_error())
        return ($logo_update);

    $Database->query("UPDATE organization SET type = 'school' WHERE id = $id_organization");

    if (($refresh = refresh_school($ret->value["id"]))->is_error())
        return ($refresh);

    return ($ret);
}
