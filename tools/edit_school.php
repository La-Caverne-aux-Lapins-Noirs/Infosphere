<?php

function edit_school($id, $data)
{
    global $LanguageList;

    if ($id == -1)
	bad_request();

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

    if (count($fields) == 0)
	bad_request();

    if (($ret = update_table("school", $id, $fields))->is_error())
	return ($ret);

    if (($ret = refresh_school($ret->value))->is_error())
	return ($ret);

    return (new Response);
}

