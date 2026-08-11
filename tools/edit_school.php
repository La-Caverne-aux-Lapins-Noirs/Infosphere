<?php

function edit_school($id, $data)
{
    global $Database;

    if ($id == -1)
        bad_request();

    if (($school = fetch_school($id)) instanceof Response)
        return ($school);

    $school_fields = [];
    if (isset($data["base_url"]))
    {
        $base_url = school_base_url_normalize($data["base_url"]);
        if ($base_url === false)
            return (new ErrorResponse("InvalidSchoolBaseUrl"));
        if ($base_url != "" && !school_base_url_is_available($base_url, $id))
            return (new ErrorResponse("SchoolBaseUrlAlreadyUsed"));
        $school_fields["base_url"] = $base_url == "" ? NULL : $base_url;
    }

    foreach ([
        "address",
        "uai",
        "cfa_name",
        "executing_establishment_name",
        "phone",
        "mail",
        "school_registration_number",
        "school_registration_academy",
        "formation_activity_number",
        "formation_activity_region",
        "alternation_registration_number",
        "alternation_registration_academy",
    ] as $field)
    {
        if (isset($data[$field]))
            $school_fields[$field] = trim((string)$data[$field]);
    }

    if (isset($school_fields["mail"]) && $school_fields["mail"] != "" && filter_var($school_fields["mail"], FILTER_VALIDATE_EMAIL) === false)
        return (new ErrorResponse("BadMail"));

    $organization_link_changed = false;
    if (isset($data["organization_codename"]))
    {
        $organization_codename = trim((string)$data["organization_codename"]);
        if ($organization_codename == "")
            return (new ErrorResponse("MissingCodeName", "organization"));
        if (($organization = resolve_codename("organization", $organization_codename, "codename", true))->is_error())
            return ($organization);
        $organization = $organization->value;
        $school_fields["id_organization"] = (int)$organization["id"];
        $organization_link_changed = true;
    }

    if (($logo_update = school_update_logos($school["codename"], $data))->is_error())
        return ($logo_update);
    $logo_changed = $logo_update->value;

    if (count($school_fields) == 0 && !$logo_changed)
        bad_request();

    if (count($school_fields) > 0)
    {
        if (($ret = update_table("school", $id, $school_fields))->is_error())
            return ($ret);
    }

    if ($organization_link_changed && isset($school_fields["id_organization"]))
        $Database->query("UPDATE organization SET type = 'school' WHERE id = ".(int)$school_fields["id_organization"]);

    if (($ret = refresh_school($id))->is_error())
        return ($ret);

    return (new Response);
}
