<?php

function check_session_field(&$fields)
{
    $tfields = [];

    $fields = convert_date($fields);

    if (isset($fields["activity"]))
    {
	if (($id = resolve_codename("activity", $fields["activity"]))->is_error())
	    return ($id);
	$tfields["id_activity"] = $id->value;
    }
    else
	$tfields["id_activity"] = -1;

    if (isset($fields["laboratory"]))
    {
	if (($id = resolve_codename("laboratory", $fields["laboratory"]))->is_error())
	    return ($id);
	$tfields["id_laboratory"] = $id->value;
    }
    else
	$tfields["id_laboratory"] = -1;

    if (isset($fields["team"]))
    {
	if (($id = resolve_codename("team", $fields["team"]))->is_error())
	    return ($id);
	$tfields["id_team"] = $id->value;
    }
    else
	$tfields["id_team"] = -1;

    if (isset($fields["user"]))
    {
	if (($id = resolve_codename("user", $fields["user"]))->is_error())
	    return ($id);
	$tfields["id_user"] = $id->value;
    }
    else
	$tfields["id_user"] = -1;

    default_int_val($tfields, $fields, "maximum_subscription", -1);

    if (strlen(@$fields["begin_date"]) && strlen(@$fields["end_date"]))
    {
	if (date_to_timestamp($fields["begin_date"]) > date_to_timestamp($fields["end_date"]))
	    return (new ErrorResponse("InvalidDate"));
	if (datex("d/m/Y", $fields["begin_date"]) != datex("d/m/Y", $fields["end_date"]))
	    return (new ErrorResponse("InvalidDate"));
	foreach (["begin_date", "end_date"] as $label)
	    $tfields[$label] = db_form_date($fields[$label]);
    }
    else
    {
	$tfields["begin_date"] = "NULL";
	$tfields["end_date"] = "NULL";
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
          (id_activity, id_laboratory, id_team, id_user, begin_date, end_date, maximum_subscription)
       VALUES
          ({$tfields["id_activity"]},
           {$tfields["id_laboratory"]},
           {$tfields["id_team"]},
           {$tfields["id_user"]},
           {$tfields["begin_date"]},
           {$tfields["end_date"]},
           {$tfields["maximum_subscription"]}
          )
	   ") == NULL)
    {
	return (new ErrorResponse("InvalidRequest"));
    }
    return (new Response);
}
