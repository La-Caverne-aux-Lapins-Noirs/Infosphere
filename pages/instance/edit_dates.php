<?php

function edit_dates($activity, $session, $post)
{
    global $Database;

    $list = [
	"emergence_date",
	"done_date",
	"registration_date",
	"close_date",
	"subject_appeir_date",
	"pickup_date",
	"subject_disappeir_date"
    ];

    $fields = [];
    foreach ($list as $l)
    {
	if (isset($post[$l]) && $post[$l] != NULL)
	    $fields[] = " $l = '".db_form_date(date_to_timestamp($post[$l]))."' ";
	else
	    $fields[] = " $l = NULL ";
    }
    $fields = implode(", ", $fields);

    if ($fields != "")
	$Database->query("UPDATE activity SET $fields WHERE id = ".$activity->id);

    if ($session == NULL)
	return ;

    $list = [
	"begin_date",
	"end_date"
    ];

    $fields = [];
    foreach ($list as $l)
    {
	if (isset($post[$l]) && $post[$l] != NULL)
	    $fields[] = " $l = '".db_form_date(date_to_timestamp($post[$l]))."' ";
	else
	    $fields[] = " $l = NULL ";
    }
    $fields = implode(", ", $fields);

    if ($fields != "")
	$Database->query("UPDATE session SET $fields WHERE id = ".$session->id);
}

