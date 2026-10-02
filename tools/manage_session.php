<?php

function session_resolve_optional_reference(array $fields, $field, $table, array &$out)
{
    if (!array_key_exists($field, $fields))
        return (new Response);

    $value = $fields[$field];
    if ($value === NULL || trim((string)$value) === "" || (is_numeric($value) && (int)$value <= 0))
        return (new Response);

    if (($id = resolve_codename($table, $value))->is_error())
        return ($id);
    $out["id_".$field] = (int)$id->value;
    return (new Response);
}

function session_validate_scope(array &$fields)
{
    $id_activity = (int)($fields["id_activity"] ?? -1);
    if ($id_activity > 0)
    {
        // Activity sessions keep their historical naming semantics. A stray
        // local name must never override the activity name in any renderer.
        $fields["name"] = NULL;
        return (new Response);
    }

    $id_user = (int)($fields["id_user"] ?? -1);
    $id_laboratory = (int)($fields["id_laboratory"] ?? -1);
    $school_ids = session_school_ids($fields);
    if (($id_user > 0 ? 1 : 0) + ($id_laboratory > 0 ? 1 : 0) + (count($school_ids) ? 1 : 0) != 1)
        return (new ErrorResponse("InvalidParameter", "session scope"));

    $name = trim((string)($fields["name"] ?? ""));
    if ($name == "")
        return (new ErrorResponse("MissingParameter", "name"));
    if (strlen($name) > 255)
        $name = substr($name, 0, 255);
    $fields["name"] = $name;

    // A standalone event without a bounded time has no useful calendar
    // meaning. Activity sessions retain the historical ability to be created
    // first and dated later.
    if (empty($fields["begin_date"]) || empty($fields["end_date"]))
        return (new ErrorResponse("MissingParameter", "begin_date/end_date"));
    return (new Response);
}

function check_session_field(&$fields)
{
    $tfields = [];

    $fields = convert_date($fields);

    foreach ([
        "activity" => "activity",
        "laboratory" => "laboratory",
        "team" => "team",
        "user" => "user",
    ] as $field => $table)
    {
        if (($ret = session_resolve_optional_reference($fields, $field, $table, $tfields))->is_error())
            return ($ret);
    }

    if (array_key_exists("name", $fields))
        $tfields["name"] = trim((string)$fields["name"]);
    if (isset($fields["_school_ids"]) && is_array($fields["_school_ids"]))
        $tfields["_school_ids"] = array_values(array_unique(array_filter(array_map("intval", $fields["_school_ids"]), function($id) { return ($id > 0); })));

    default_val($tfields, $fields, "maximum_subscription");

    if (strlen((string)($fields["begin_date"] ?? "")) && strlen((string)($fields["end_date"] ?? "")))
    {
        if (date_to_timestamp($fields["begin_date"]) > date_to_timestamp($fields["end_date"]))
            return (new ErrorResponse("InvalidDate"));
        if (datex("d/m/Y", $fields["begin_date"]) != datex("d/m/Y", $fields["end_date"]))
            return (new ErrorResponse("InvalidDate"));
        foreach (["begin_date", "end_date"] as $label)
            $tfields[$label] = db_form_date($fields[$label]);
    }
    return (new ValueResponse($tfields));
}

function edit_session($fields)
{
    if (($tfields = check_session_field($fields))->is_error())
        return ($tfields);
    $tfields = $tfields->value;
    $tfields["id"] = $fields["id"];

    if (($ret = resolve_codename("session", $tfields["id"]))->is_error())
        return ($ret);

    // EditSession supplies the current scope references, so validation here is
    // performed against the complete state rather than only changed fields.
    if (($ret = session_validate_scope($tfields))->is_error())
        return ($ret);

    if (($ret = try_update("session", $ret->value, $tfields))->is_error())
        return ($ret);
    return (new Response);
}

function add_session($fields, $dry = false)
{
    global $Database;

    if (($tfields = check_session_field($fields))->is_error())
        return ($tfields);
    $tfields = $tfields->value;

    foreach (["id_activity", "id_laboratory", "id_team", "id_user"] as $ids)
        if (!isset($tfields[$ids]))
            $tfields[$ids] = -1;

    if (($ret = session_validate_scope($tfields))->is_error())
        return ($ret);

    $sql = [];
    foreach (["id_activity", "id_laboratory", "id_team", "id_user"] as $field)
        $sql[$field] = (string)(int)$tfields[$field];

    foreach (["maximum_subscription", "begin_date", "end_date"] as $field)
    {
        if (!isset($tfields[$field]) || $tfields[$field] === NULL || $tfields[$field] === "")
            $sql[$field] = "NULL";
        else
            $sql[$field] = "'".$Database->real_escape_string((string)$tfields[$field])."'";
    }
    $sql["name"] = $tfields["name"] === NULL
        ? "NULL"
        : "'".$Database->real_escape_string((string)$tfields["name"])."'";

    if ($dry)
        return (new Response);

    if ($Database->query("
       INSERT INTO session
          (id_activity, id_laboratory, id_team, id_user, name, begin_date, end_date, maximum_subscription)
       VALUES
          ({$sql["id_activity"]},
           {$sql["id_laboratory"]},
           {$sql["id_team"]},
           {$sql["id_user"]},
           {$sql["name"]},
           {$sql["begin_date"]},
           {$sql["end_date"]},
           {$sql["maximum_subscription"]}
          )
    ") == NULL)
        return (new ErrorResponse("InvalidRequest"));
    return (new Response);
}
