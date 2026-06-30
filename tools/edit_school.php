<?php

function edit_school($id, $data)
{
    global $LanguageList;

    if ($id == -1)
        bad_request();

    if (($school = fetch_school($id)) instanceof Response)
        return ($school);

    $fields = [];

    foreach ($LanguageList as $lang => $label)
    {
        $field = $lang."_name";
        if (isset($data[$field]))
            $fields[$field] = $data[$field];
    }

    foreach ([
        "legal_name",
        "address",
        "phone",
        "mail",
        "main_info",
        "school_info",
        "formation_info",
        "alternation_info",
    ] as $field)
    {
        if (isset($data[$field]))
            $fields[$field] = $data[$field];
    }

    if (isset($fields["mail"]) && $fields["mail"] != "" && filter_var($fields["mail"], FILTER_VALIDATE_EMAIL) === false)
        return (new ErrorResponse("BadMail"));

    if (($logo_update = school_update_logos($school["codename"], $data))->is_error())
        return ($logo_update);
    $logo_changed = $logo_update->value;

    if (count($fields) == 0 && !$logo_changed)
        bad_request();

    if (count($fields) > 0)
    {
        if (($ret = update_table("school", $id, $fields))->is_error())
            return ($ret);
        $school = $ret->value;
    }

    if (($ret = refresh_school($school["id"]))->is_error())
        return ($ret);

    return (new Response);
}
