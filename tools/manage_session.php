<?php

function check_session_field(&$fields)
{
    $tfields = [];

    $fields = convert_date($fields);

    if (($id = resolve_codename("activity", $fields["activity"]))->is_error())
	return ($id);
    $tfields["id_activity"] = $id->value;

    default_int_val($tfields, $fields, "maximum_subscription", -1);

    $date_fields = [
	"begin_date", "end_date"
    ];
    foreach ($date_fields as $dat)
    {
	if ($fields[$dat] == NULL)
	    return (new ErrorResponse("MissingField", $dat));
	$tfields[$dat] = date_to_timestamp($fields[$dat]);
    }
    if ($tfields["begin_date"] > $tfields["end_date"])
	return (new ErrorResponse("InvalidDate"));
    if (datex("d/m/Y", $tfields["begin_date"]) != datex("d/m/Y", $tfields["end_date"]))
	return (new ErrorResponse("InvalidDate"));
    foreach ($date_fields as $dat)
    {
	$tfields[$dat] = db_form_date($fields[$dat]);
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

    if ($dry)
	return (new Response);

    if ($Database->query("
       INSERT INTO session
          (id_activity, begin_date, end_date)
       VALUES
          ({$tfields["id_activity"]}, '{$tfields["begin_date"]}', '{$tfields["end_date"]}')
	  ") == NULL)
    {
	return (new ErrorResponse("InvalidRequest"));
    }
    return (new Response);
}
