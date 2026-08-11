<?php

function db_escape($value)
{
    global $Database;

    return ($Database->real_escape_string((string)$value));
}

function organization_type($type)
{
    $type = trim((string)$type);
    if (in_array($type, ["school", "enterprise"], true))
        return ($type);
    return ("enterprise");
}

function organization_dir($organization = NULL)
{
    global $Configuration;

    if (!isset($Configuration))
        return ("dres/organization/".($organization === NULL ? "" : $organization."/"));
    return ($Configuration->OrganizationsDir($organization));
}

function organization_logo_candidate_names($kind)
{
    if ($kind == "document")
        return ([
            "document_logo.png", "document_logo.jpg", "document_logo.jpeg", "document_logo.pdf",
            "logo_document.png", "logo_document.jpg", "logo_document.jpeg", "logo_document.pdf",
            "print_logo.png", "print_logo.jpg", "print_logo.jpeg", "print_logo.pdf",
            "logo.png", "logo.jpg", "logo.jpeg", "logo.pdf",
            "icon.png", "icon.jpg", "icon.jpeg",
        ]);
    return ([
        "icon.png", "icon.jpg", "icon.jpeg",
        "site_logo.png", "site_logo.jpg", "site_logo.jpeg",
        "logo.png", "logo.jpg", "logo.jpeg",
        "document_logo.png", "document_logo.jpg", "document_logo.jpeg",
    ]);
}

function organization_logo_codename($organization)
{
    if (is_array($organization))
        return ($organization["codename"] ?? "");
    return ((string)$organization);
}

function organization_logo_path($organization, $kind = "site", $absolute = false, $with_fallback = true)
{
    $root = dirname(__DIR__);
    $codename = organization_logo_codename($organization);
    $candidates = [];
    if ($codename != "")
        foreach (organization_logo_candidate_names($kind) as $name)
            $candidates[] = organization_dir($codename).$name;
    if ($with_fallback)
        foreach (["res/logo.png", "res/logo.jpg", "res/no_avatar_lab.png"] as $name)
            $candidates[] = $name;

    foreach ($candidates as $candidate)
    {
        $candidate_absolute = $candidate;
        if ($candidate_absolute != "" && $candidate_absolute[0] != "/")
            $candidate_absolute = $root."/".$candidate_absolute;
        if (file_exists($candidate_absolute) && !is_dir($candidate_absolute))
            return ($absolute ? $candidate_absolute : $candidate);
    }
    return ("");
}

function organization_site_logo_path($organization, $absolute = false, $with_fallback = true)
{
    return (organization_logo_path($organization, "site", $absolute, $with_fallback));
}

function organization_document_logo_path($organization, $absolute = false, $with_fallback = true)
{
    return (organization_logo_path($organization, "document", $absolute, $with_fallback));
}

function organization_logo_payload_empty($payload)
{
    if ($payload === NULL || $payload === "")
        return (true);
    if (is_array($payload))
    {
        if (!count($payload))
            return (true);
        if (isset($payload[0]["content"]) && $payload[0]["content"] != "")
            return (false);
        if (isset($payload["content"]) && $payload["content"] != "")
            return (false);
        return (true);
    }
    return (false);
}

function organization_write_logo_binary($raw, $target)
{
    if ($raw === false || $raw === NULL || $raw === "")
        return (new ErrorResponse("BadFileFormat"));
    if (($img = @imagecreatefromstring($raw)) == false)
        return (new ErrorResponse("BadFileFormat"));
    $size = @getimagesizefromstring($raw);
    if (!is_array($size) || $size[0] < 100 || $size[1] < 100)
    {
        imagedestroy($img);
        return (new ErrorResponse("InvalidPictureSize"));
    }
    if (($ret = new_directory($target))->is_error())
    {
        imagedestroy($img);
        return ($ret);
    }
    imagesavealpha($img, true);
    if (imagepng($img, $target) == false)
    {
        imagedestroy($img);
        return (new ErrorResponse("CannotWritePngFile"));
    }
    imagedestroy($img);
    return (new Response);
}

function organization_upload_logo_payload($payload, $target)
{
    if (organization_logo_payload_empty($payload))
        return (new ValueResponse(false));

    if (is_string($payload) && @file_exists($payload))
    {
        if (($ret = new_directory($target))->is_error())
            return ($ret);
        if (($ret = upload_png($payload, $target, [100, 100], MINIMUM_PICTURE_SIZE))->is_error())
            return ($ret);
        return (new ValueResponse(true));
    }

    if (is_array($payload))
    {
        if (isset($payload[0]["content"]))
            $payload = $payload[0]["content"];
        else if (isset($payload["content"]))
            $payload = $payload["content"];
        else
            return (new ValueResponse(false));
    }

    $raw = base64_decode((string)$payload, true);
    if ($raw === false)
        return (new ErrorResponse("BadFileFormat"));
    if (($ret = organization_write_logo_binary($raw, $target))->is_error())
        return ($ret);
    return (new ValueResponse(true));
}

function organization_update_logos($codename, array $data)
{
    $dir = organization_dir($codename);
    $changed = false;
    $logos = [
        "icon" => "icon.png",
        "site_logo" => "icon.png",
        "document_logo" => "document_logo.png",
        "document_icon" => "document_logo.png",
    ];
    foreach ($logos as $field => $filename)
    {
        if (!array_key_exists($field, $data) || organization_logo_payload_empty($data[$field]))
            continue ;
        if (($ret = organization_upload_logo_payload($data[$field], $dir.$filename))->is_error())
            return ($ret);
        $changed = $changed || ($ret instanceof ValueResponse && $ret->value);
    }
    return (new ValueResponse($changed));
}

function organization_person_fields(array $user, $role = "")
{
    $fields = refresh_user_fields($user);
    return ([
        "id" => $user["id"] ?? -1,
        "codename" => $user["codename"] ?? "",
        "first_name" => $fields["first_name"] ?? ($user["first_name"] ?? ""),
        "family_name" => $fields["family_name"] ?? ($user["family_name"] ?? ""),
        "identity" => document_builder_name($user),
        "mail" => $fields["mail"] ?? ($user["mail"] ?? ""),
        "courriel" => $fields["mail"] ?? ($user["mail"] ?? ""),
        "phone" => $fields["phone"] ?? ($user["phone"] ?? ""),
        "role" => $role,
    ]);
}

function fetch_organization_users($id_organization, $document_role = NULL)
{
    $id_organization = (int)$id_organization;
    $where_role = "";
    if ($document_role !== NULL)
        $where_role = " AND organization_user.document_role = '".db_escape($document_role)."' ";
    return (db_select_all("
        organization_user.id as id_link,
        organization_user.id_user as id_user,
        organization_user.position as position,
        organization_user.document_role as document_role,
        user.id as id,
        user.codename as codename,
        user.first_name as first_name,
        user.family_name as family_name,
        user.mail as mail,
        user.phone as phone,
        user.profile_status as profile_status
        FROM organization_user
        LEFT JOIN user ON user.id = organization_user.id_user
        WHERE organization_user.id_organization = $id_organization
        AND user.id IS NOT NULL
        AND user.authority != -1
        $where_role
        ORDER BY
            CASE organization_user.document_role
                WHEN 'representative' THEN 0
                WHEN 'tutor' THEN 1
                WHEN 'contact' THEN 2
                ELSE 3
            END,
            organization_user.id ASC
    "));
}

function first_organization_user_by_role(array $organization, $role)
{
    if (!isset($organization["contacts"]))
        return ([]);
    foreach ($organization["contacts"] as $contact)
        if (($contact["document_role"] ?? "") == $role)
            return ($contact);
    return ([]);
}

function organization_pick(array $data, array $names, $default = "")
{
    foreach ($names as $name)
        if (isset($data[$name]) && trim((string)$data[$name]) != "")
            return (trim((string)$data[$name]));
    return ($default);
}

function organization_effective_school_field(array $school, $field)
{
    return (organization_pick($school, [
        $field,
        "school_".$field,
        "organization_".$field,
    ]));
}

function sync_school_organization(array $school)
{
    global $Database;

    if (!isset($school["id"]) || !isset($school["codename"]))
        return (new ErrorResponse("MissingCodeName"));

    $id_school = (int)$school["id"];
    $id_organization = isset($school["id_organization"]) ? (int)$school["id_organization"] : 0;
    $fr_name = organization_pick($school, ["fr_name", "name", "organization_name"], $school["codename"]);
    $en_name = organization_pick($school, ["en_name"], $fr_name);
    $legal_name = organization_pick($school, ["legal_name"], $fr_name);
    $fields = [
        "codename" => organization_pick($school, ["organization_codename", "codename"], $school["codename"]),
        "type" => "school",
        "name" => organization_pick($school, ["organization_name", "name", "fr_name"], $fr_name),
        "fr_name" => $fr_name,
        "en_name" => $en_name,
        "legal_name" => $legal_name,
        "address" => organization_pick($school, ["organization_address", "legal_address", "head_office_address", "address"]),
        "phone" => organization_pick($school, ["organization_phone", "legal_phone", "phone"]),
        "mail" => organization_pick($school, ["organization_mail", "legal_mail", "mail"]),
        "website" => organization_pick($school, ["website"]),
        "siret" => organization_pick($school, ["siret"]),
        "registration_registry" => organization_pick($school, ["registration_registry"]),
        "registration_number" => organization_pick($school, ["registration_number"]),
    ];

    $escaped = [];
    foreach ($fields as $field => $value)
        $escaped[$field] = "'".db_escape($value)."'";

    if ($id_organization > 0)
    {
        $set = [];
        foreach ($escaped as $field => $value)
            $set[] = "$field = $value";
        if ($Database->query("UPDATE organization SET ".implode(", ", $set)." WHERE id = $id_organization") == false)
            return (new ErrorResponse("CannotEdit"));
    }
    else
    {
        $existing = db_select_one("id FROM organization WHERE codename = '".db_escape($fields["codename"])."'");
        if ($existing != NULL)
            $id_organization = (int)$existing["id"];
        else
        {
            if ($Database->query("
                INSERT INTO organization (".implode(", ", array_keys($fields)).")
                VALUES (".implode(", ", $escaped).")
            ") == false)
                return (new ErrorResponse("CannotAdd"));
            $id_organization = (int)$Database->insert_id;
        }
        if ($Database->query("UPDATE school SET id_organization = $id_organization WHERE id = $id_school") == false)
            return (new ErrorResponse("CannotEdit"));
    }

    return (new ValueResponse($id_organization));
}
