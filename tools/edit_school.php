<?php

require_once (__DIR__."/school_activity.php");

function edit_school($id, $data)
{
    global $Database;

    if ($id == -1)
        bad_request();

    if (($school = fetch_school($id)) instanceof Response)
        return ($school);

    $activity = school_activity_updates($data);
    if ($activity->is_error())
        return $activity;
    $school_fields = $activity->value;
    $mailbox_updates = [];
    if (function_exists("school_mailbox_purposes"))
        foreach (school_mailbox_purposes() as $purpose => $label)
        {
            $key = "mailbox_".$purpose;
            if (!array_key_exists($key, $data))
                continue ;
            $mailbox_updates[$purpose] = trim((string)$data[$key]);
            if ($mailbox_updates[$purpose] != "" && filter_var($mailbox_updates[$purpose], FILTER_VALIDATE_EMAIL) === false)
                return (new ErrorResponse("BadMail"));
        }
    if (count($mailbox_updates) && function_exists("school_mailbox_table_available") && !school_mailbox_table_available())
    {
        foreach ($mailbox_updates as $mail)
            if ($mail != "")
                return (new ErrorResponse("CannotEdit", "school_mailbox table is missing"));
        $mailbox_updates = [];
    }
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

    if (count($school_fields) == 0 && !$logo_changed && count($mailbox_updates) == 0)
        bad_request();

    if (count($school_fields) > 0)
    {
        if (($ret = update_table("school", $id, $school_fields))->is_error())
            return ($ret);
    }

    if ($organization_link_changed && isset($school_fields["id_organization"]))
        $Database->query("UPDATE organization SET type = 'school' WHERE id = ".(int)$school_fields["id_organization"]);

    foreach ($mailbox_updates as $purpose => $mail)
    {
        $mailbox = school_mailbox_set((int)$school["id"], $purpose, $mail);
        if ($mailbox->is_error())
            return ($mailbox);
    }

    if (($ret = refresh_school($id))->is_error())
        return ($ret);

    return (new Response);
}
