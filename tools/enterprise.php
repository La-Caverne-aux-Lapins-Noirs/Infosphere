<?php

function enterprise_document_roles()
{
    return (["contact", "representative", "tutor"]);
}

function enterprise_document_role_list($roles)
{
    if (!is_array($roles))
        $roles = preg_split('/\s*[,;]\s*/', trim((string)$roles), -1, PREG_SPLIT_NO_EMPTY);

    $selected = [];
    foreach (enterprise_document_roles() as $role)
        if (in_array($role, $roles, true))
            $selected[] = $role;
    if (!count($selected))
        $selected[] = "contact";
    return ($selected);
}

function enterprise_document_role($roles)
{
    return (implode(",", enterprise_document_role_list($roles)));
}

function enterprise_has_document_role($roles, $role)
{
    return (in_array($role, enterprise_document_role_list($roles), true));
}

function enterprise_document_roles_from_data(array $data)
{
    $roles = [];
    $explicit = false;

    foreach (enterprise_document_roles() as $role)
    {
        $field = "document_role_".$role;
        if (!array_key_exists($field, $data))
            continue ;
        $explicit = true;
        if (!empty($data[$field]))
            $roles[] = $role;
    }

    if (!$explicit && isset($data["document_roles"]))
    {
        $explicit = true;
        $roles = is_array($data["document_roles"]) ? $data["document_roles"] : [$data["document_roles"]];
    }
    if (!$explicit && isset($data["document_role"]))
        $roles = enterprise_document_role_list($data["document_role"]);

    return (enterprise_document_role($roles));
}

function enterprise_contact_candidates($id_organization)
{
    $id_organization = (int)$id_organization;
    return (db_select_all("
        user.id, user.codename, user.first_name, user.family_name, user.mail
        FROM user
        WHERE user.authority != -1
        AND NOT EXISTS (
            SELECT organization_user.id
            FROM organization_user
            WHERE organization_user.id_organization = $id_organization
            AND organization_user.id_user = user.id
        )
        ORDER BY
            COALESCE(NULLIF(user.family_name, ''), user.codename) ASC,
            COALESCE(NULLIF(user.first_name, ''), user.codename) ASC,
            user.codename ASC
    "));
}

function enterprise_fetch_id($id)
{
    if (($id = resolve_codename("organization", $id))->is_error())
        return ($id);
    return (new ValueResponse((int)$id->value));
}

function fetch_enterprises($id = -1)
{
    if ($id !== -1 && $id != "")
    {
        if (($id = enterprise_fetch_id($id))->is_error())
            return ($id);
        $id = " AND organization.id = ".(int)$id->value." ";
    }
    else
        $id = "";

    $out = db_select_all("
        organization.*,
        school.id as id_school,
        school.codename as school_codename
        FROM organization
        LEFT JOIN school ON school.id_organization = organization.id AND school.deleted IS NULL
        WHERE organization.deleted IS NULL
        $id
        ORDER BY
            CASE organization.type WHEN 'school' THEN 0 ELSE 1 END,
            COALESCE(NULLIF(organization.name, ''), NULLIF(organization.fr_name, ''), NULLIF(organization.legal_name, ''), organization.codename) ASC,
            organization.codename ASC
    ");

    foreach ($out as &$enterprise)
    {
        $enterprise["icon"] = organization_site_logo_path($enterprise, false, true);
        $enterprise["document_logo"] = organization_document_logo_path($enterprise, false, true);
        $enterprise["contacts"] = fetch_organization_users($enterprise["id"]);
        $enterprise["representative"] = first_organization_user_by_role($enterprise, "representative");
        $enterprise["tutor"] = first_organization_user_by_role($enterprise, "tutor");
    }
    unset($enterprise);

    if ($id != "")
        return (count($out) ? $out[0] : []);
    return ($out);
}

function enterprise_context_person(array $user, $role = "")
{
    if (!count($user))
        return ([
            "first_name" => "",
            "family_name" => "",
            "identity" => "",
            "mail" => "",
            "courriel" => "",
            "phone" => "",
            "role" => $role,
        ]);
    if ($role == "")
        $role = $user["position"] ?? "";
    return (organization_person_fields($user, $role));
}

function enterprise_digits($value)
{
    return (preg_replace('/[^0-9]/', '', (string)$value));
}

function enterprise_siret(array $enterprise)
{
    return (enterprise_digits($enterprise["siret"] ?? ""));
}

function enterprise_siren(array $enterprise)
{
    $siret = enterprise_siret($enterprise);
    if (strlen($siret) >= 9)
        return (substr($siret, 0, 9));
    return ("");
}

function enterprise_nic(array $enterprise)
{
    $siret = enterprise_siret($enterprise);
    if (strlen($siret) >= 14)
        return (substr($siret, 9, 5));
    return ("");
}

function enterprise_address_lines(array $enterprise)
{
    $lines = [];
    foreach (["head_office_address_line1", "head_office_address_line2"] as $field)
        if (trim((string)($enterprise[$field] ?? "")) != "")
            $lines[] = trim((string)$enterprise[$field]);

    $city_line = trim(implode(" ", array_filter([
        trim((string)($enterprise["head_office_zipcode"] ?? "")),
        trim((string)($enterprise["head_office_city"] ?? "")),
    ], function ($value) { return ($value != ""); })));
    if ($city_line != "")
        $lines[] = $city_line;

    if (trim((string)($enterprise["head_office_country"] ?? "")) != "")
        $lines[] = trim((string)$enterprise["head_office_country"]);

    // Compatibilité transitoire avec l'ancien champ libre. Il n'est plus écrit
    // par la page entreprise, mais il peut encore contenir des données existantes.
    if (!count($lines) && trim((string)($enterprise["address"] ?? "")) != "")
        $lines[] = trim((string)$enterprise["address"]);
    return ($lines);
}

function enterprise_legal_address(array $enterprise)
{
    return (implode("\n", enterprise_address_lines($enterprise)));
}

function enterprise_main_info_address(array $enterprise)
{
    $parts = [];
    foreach (["head_office_address_line1", "head_office_address_line2"] as $field)
    {
        $value = trim((string)($enterprise[$field] ?? ""));
        if ($value != "")
            $parts[] = $value;
    }
    $city = trim(implode(" ", array_filter([
        trim((string)($enterprise["head_office_zipcode"] ?? "")),
        trim((string)($enterprise["head_office_city"] ?? "")),
    ], function ($value) { return ($value != ""); })));
    if ($city != "")
        $parts[] = $city;

    $country = trim((string)($enterprise["head_office_country"] ?? ""));
    if ($country != "" && strcasecmp($country, "France") != 0)
        $parts[] = $country;

    if (!count($parts))
    {
        $legacy = trim(preg_replace('/\s*\R\s*/u', ', ', (string)($enterprise["address"] ?? "")));
        if ($legacy != "")
            $parts[] = $legacy;
    }
    return (implode(", ", $parts));
}

function enterprise_main_info(array $enterprise)
{
    $legal_name = trim((string)($enterprise["legal_name"] ?? ($enterprise["name"] ?? "")));
    $address = enterprise_main_info_address($enterprise);
    $registry = trim((string)($enterprise["registration_registry"] ?? ""));
    $registration_number = trim((string)($enterprise["registration_number"] ?? ""));
    $out = $legal_name;

    if ($address != "")
        $out .= ($out == "" ? "" : " : ")."Siège social : ".$address;
    if ($registration_number != "")
    {
        $out .= ($out == "" ? "" : ", ")."immatriculée";
        if ($registry != "")
            $out .= " au ".$registry;
        $out .= " sous le numéro ".$registration_number;
    }
    if ($out != "" && !preg_match('/[.!?]$/u', $out))
        $out .= ".";
    return ($out);
}

function enterprise_dabsic_context(array $enterprise)
{
    $representative = enterprise_context_person($enterprise["representative"] ?? [], $enterprise["representative"]["position"] ?? "");
    $tutor = enterprise_context_person($enterprise["tutor"] ?? [], $enterprise["tutor"]["position"] ?? "");
    $legal_address = enterprise_legal_address($enterprise);
    $siret = enterprise_siret($enterprise);

    return ([
        "company" => [
            "id" => $enterprise["id"] ?? -1,
            "codename" => $enterprise["codename"] ?? "",
            "name" => $enterprise["name"] ?? "",
            "legal_name" => $enterprise["legal_name"] ?? ($enterprise["name"] ?? ""),
            "SIRET" => $siret,
            "SIREN" => enterprise_siren($enterprise),
            "NIC" => enterprise_nic($enterprise),
            "address" => $legal_address,
            "legal_address" => $legal_address,
            "head_office" => [
                "address_line1" => $enterprise["head_office_address_line1"] ?? "",
                "address_line2" => $enterprise["head_office_address_line2"] ?? "",
                "zipcode" => $enterprise["head_office_zipcode"] ?? "",
                "city" => $enterprise["head_office_city"] ?? "",
                "country" => $enterprise["head_office_country"] ?? "",
            ],
            "main_info" => enterprise_main_info($enterprise),
            "mail" => $enterprise["mail"] ?? "",
            "courriel" => $enterprise["mail"] ?? "",
            "phone" => $enterprise["phone"] ?? "",
            "website" => $enterprise["website"] ?? "",
            "activity" => $enterprise["activity"] ?? "",
            "billing_information" => $enterprise["billing_information"] ?? "",
            "RIB" => $enterprise["billing_information"] ?? "",
            "notes" => $enterprise["notes"] ?? "",
            "representative" => $representative["identity"] ?? "",
            "role" => $representative["role"] ?? "",
            "tutor" => $tutor,
            // Les logos d'entreprise restent optionnels. Les logos nécessaires au
            // fonctionnement documentaire de l'école restent dans school.
            "logo" => organization_document_logo_path($enterprise, true, false),
            "document_logo" => organization_document_logo_path($enterprise, true, false),
            "site_logo" => organization_site_logo_path($enterprise, true, false),
            "logo_width" => "3cm",
            "logo_height" => "2cm",
        ],
        "signature_sources" => [
            "company" => document_builder_signatory_context($representative, "Company"),
            "company_tutor" => document_builder_signatory_context($tutor, "CompanyTutor"),
        ],
    ]);
}

function refresh_organization($organization)
{
    if (!is_array($organization))
    {
        $organization = fetch_enterprises($organization);
        if (is_object($organization) && $organization->is_error())
            return ($organization);
    }
    if (!isset($organization["codename"]))
        return (new ErrorResponse("MissingCodeName"));

    $context = enterprise_dabsic_context($organization);
    $identity = $context["company"] ?? [];
    return (generate_dabsic(
        $identity,
        organization_dir($organization["codename"])."identity.dab"
    ));
}

function refresh_enterprise($enterprise)
{
    if (!is_array($enterprise))
    {
        $enterprise = fetch_enterprises($enterprise);
        if (is_object($enterprise) && $enterprise->is_error())
            return ($enterprise);
    }
    if (!isset($enterprise["codename"]))
        return (new ErrorResponse("MissingCodeName"));

    // Fichier propre et non scopé représentant l'organisation elle-même.
    // description.dab reste généré ci-dessous pour les documents historiques.
    $ret = refresh_organization($enterprise);
    if (is_object($ret) && $ret->is_error())
        return ($ret);

    return (generate_dabsic(
        enterprise_dabsic_context($enterprise),
        organization_dir($enterprise["codename"])."description.dab"
    ));
}

function add_enterprise(array $data)
{
    global $Database;

    $codename = trim((string)($data["codename"] ?? ""));
    if ($codename == "")
        return (new ErrorResponse("MissingCodeName"));

    if (($ret = resolve_codename("organization", $codename))->is_error() == false)
        return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
    else if ($ret->label != "BadCodeName")
        return ($ret);

    $mail = trim((string)($data["mail"] ?? ""));
    if ($mail != "" && filter_var($mail, FILTER_VALIDATE_EMAIL) === false)
        return (new ErrorResponse("BadMail"));

    $fields = [
        "codename" => $codename,
        "type" => "enterprise",
        "name" => trim((string)($data["name"] ?? $codename)),
        "legal_name" => trim((string)($data["legal_name"] ?? ($data["name"] ?? $codename))),
        "head_office_address_line1" => trim((string)($data["head_office_address_line1"] ?? "")),
        "head_office_address_line2" => trim((string)($data["head_office_address_line2"] ?? "")),
        "head_office_zipcode" => trim((string)($data["head_office_zipcode"] ?? "")),
        "head_office_city" => trim((string)($data["head_office_city"] ?? "")),
        "head_office_country" => trim((string)($data["head_office_country"] ?? "France")),
        "phone" => trim((string)($data["phone"] ?? "")),
        "mail" => $mail,
        "website" => trim((string)($data["website"] ?? "")),
        "siret" => enterprise_digits($data["siret"] ?? ""),
        "activity" => trim((string)($data["activity"] ?? "")),
        "billing_information" => trim((string)($data["billing_information"] ?? "")),
        "notes" => trim((string)($data["notes"] ?? "")),
    ];

    $cols = [];
    $vals = [];
    foreach ($fields as $field => $value)
    {
        $cols[] = $field;
        $vals[] = "'".db_escape($value)."'";
    }

    if ($Database->query("INSERT INTO organization (".implode(", ", $cols).") VALUES (".implode(", ", $vals).")") == false)
        return (new ErrorResponse("CannotAdd"));
    $id_organization = (int)$Database->insert_id;

    if (($logo_update = organization_update_logos($codename, $data))->is_error())
        return ($logo_update);

    $enterprise = fetch_enterprises($id_organization);
    if (($refresh = refresh_enterprise($enterprise))->is_error())
        return ($refresh);
    add_log(CREATIVE_OPERATION, "enterprise $codename", $id_organization);
    return (new ValueResponse($enterprise));
}

function edit_enterprise($id, array $data)
{
    global $Database;

    if (($ret = enterprise_fetch_id($id))->is_error())
        return ($ret);
    $id = (int)$ret->value;
    $enterprise = fetch_enterprises($id);
    if (!is_array($enterprise) || !count($enterprise))
        return (new ErrorResponse("CannotRetrieveContent"));

    $fields = [];
    foreach ([
        "name", "legal_name",
        "head_office_address_line1", "head_office_address_line2",
        "head_office_zipcode", "head_office_city", "head_office_country",
        "phone", "mail", "website", "activity", "billing_information", "notes"
    ] as $field)
        if (isset($data[$field]))
            $fields[$field] = trim((string)$data[$field]);
    if (isset($data["siret"]))
        $fields["siret"] = enterprise_digits($data["siret"]);
    if (isset($fields["mail"]) && $fields["mail"] != "" && filter_var($fields["mail"], FILTER_VALIDATE_EMAIL) === false)
        return (new ErrorResponse("BadMail"));

    if (count($fields))
    {
        $set = [];
        foreach ($fields as $field => $value)
            $set[] = "$field = '".db_escape($value)."'";
        if ($Database->query("UPDATE organization SET ".implode(", ", $set)." WHERE id = $id") == false)
            return (new ErrorResponse("CannotEdit"));
    }


    if (($logo_update = organization_update_logos($enterprise["codename"], $data))->is_error())
        return ($logo_update);

    $enterprise = fetch_enterprises($id);
    if (($refresh = refresh_enterprise($enterprise))->is_error())
        return ($refresh);
    add_log(EDITING_OPERATION, "enterprise {$enterprise["codename"]}", $id);
    return (new ValueResponse($enterprise));
}

function enterprise_unique_contact_codename($base)
{
    $base = convert_to_codename($base);
    if ($base == "")
        $base = "contact";
    $candidate = $base;
    $i = 2;
    while (db_select_one("id FROM user WHERE codename = '".db_escape($candidate)."'") != NULL)
    {
        $candidate = $base.".".$i;
        $i += 1;
    }
    return ($candidate);
}

function enterprise_contact_user_id(array $data)
{
    if (isset($data["user"]) && trim((string)$data["user"]) != "")
    {
        if (($ret = resolve_codename("user", $data["user"]))->is_error())
            return ($ret);
        return (new ValueResponse((int)$ret->value));
    }

    $mail = trim((string)($data["mail"] ?? ""));
    if ($mail != "" && ($existing = db_select_one("id FROM user WHERE mail = '".db_escape($mail)."'")) != NULL)
        return (new ValueResponse((int)$existing["id"]));

    $first = trim((string)($data["first_name"] ?? ""));
    $family = trim((string)($data["family_name"] ?? ""));
    if ($first == "" && $family == "")
        return (new ErrorResponse("MissingField", "user"));
    if ($mail == "")
        return (new ErrorResponse("MissingField", "mail"));

    $base = trim($first.".".$family, ".");
    $codename = enterprise_unique_contact_codename($base);
    if (($ret = subscribe($codename, $mail, NULL, false, true, "enterprise_contact"))->is_error())
        return ($ret);

    $id_user = (int)$ret->value["id"];
    $fields = [
        "first_name" => strtolower($first),
        "family_name" => strtolower($family),
        "phone" => trim((string)($data["phone"] ?? "")),
    ];
    if (($ret = set_user_data($id_user, $fields))->is_error())
        return ($ret);
    return (new ValueResponse($id_user));
}

function set_enterprise_contact($id_organization, array $data)
{
    global $Database;

    if (($ret = enterprise_fetch_id($id_organization))->is_error())
        return ($ret);
    $id_organization = (int)$ret->value;
    if (($ret = enterprise_contact_user_id($data))->is_error())
        return ($ret);
    $id_user = (int)$ret->value;

    $position = trim((string)($data["position"] ?? ""));
    $document_role = enterprise_document_roles_from_data($data);

    if ($Database->query("
        INSERT INTO organization_user (id_organization, id_user, position, document_role)
        VALUES ($id_organization, $id_user, '".db_escape($position)."', '".db_escape($document_role)."')
        ON DUPLICATE KEY UPDATE
            position = VALUES(position),
            document_role = VALUES(document_role)
    ") == false)
        return (new ErrorResponse("CannotEdit"));

    $enterprise = fetch_enterprises($id_organization);
    if (($refresh = refresh_enterprise($enterprise))->is_error())
        return ($refresh);
    return (new ValueResponse($enterprise));
}

function edit_enterprise_contact($id_organization, $id_link, array $data)
{
    global $Database;

    if (($ret = enterprise_fetch_id($id_organization))->is_error())
        return ($ret);
    $id_organization = (int)$ret->value;
    $id_link = (int)$id_link;
    $position = trim((string)($data["position"] ?? ""));
    $document_role = enterprise_document_roles_from_data($data);

    if ($Database->query("
        UPDATE organization_user
        SET position = '".db_escape($position)."', document_role = '".db_escape($document_role)."'
        WHERE id = $id_link
        AND id_organization = $id_organization
    ") == false)
        return (new ErrorResponse("CannotEdit"));

    $enterprise = fetch_enterprises($id_organization);
    if (($refresh = refresh_enterprise($enterprise))->is_error())
        return ($refresh);
    return (new ValueResponse($enterprise));
}

function delete_enterprise_contact($id_organization, $id_link)
{
    global $Database;

    if (($ret = enterprise_fetch_id($id_organization))->is_error())
        return ($ret);
    $id_organization = (int)$ret->value;
    $id_link = abs((int)$id_link);

    if ($Database->query("DELETE FROM organization_user WHERE id = $id_link AND id_organization = $id_organization") == false)
        return (new ErrorResponse("CannotEdit"));

    $enterprise = fetch_enterprises($id_organization);
    if (($refresh = refresh_enterprise($enterprise))->is_error())
        return ($refresh);
    return (new ValueResponse($enterprise));
}

function delete_enterprise($id)
{
    global $Database;

    if (($ret = enterprise_fetch_id($id))->is_error())
        return ($ret);
    $id = (int)$ret->value;
    if ($Database->query("UPDATE organization SET deleted = NOW() WHERE id = $id AND type = 'enterprise'") == false)
        return (new ErrorResponse("CannotEdit"));
    add_log(EDITING_OPERATION, "enterprise deleted", $id);
    return (new Response);
}
