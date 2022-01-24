<?php

function move_activity_date($activity, $move)
{
    global $Database;

    if (($activity = resolve_codename("activity", $activity, "codename", true))->is_error())
	return ($activity);
    $activity = $activity->value;
    if ($activity["id"] != -1)
    {
	$sub = db_select_all("id FROM activity WHERE parent_activity = {$activity["id"]}");
	foreach ($sub as $s)
	{
	    move_activity_date($s["id"], $move);
	}
    }
    $edit = [];
    $fields = [
	"emergence_date", "registration_date", "close_date", "subject_appeir_date",
	"subject_disappeir_date", "pickup_date", "done_date"
    ];
    foreach ($fields as $f)
    {
	if ($activity[$f] != NULL)
	{
	    $x = date_to_timestamp($activity[$f]) + $move;
	    $edit[$f] = "$f = '".db_form_date($x)."'";
	}
    }
    $edit = implode(" , ", $edit);
    $Database->query("UPDATE activity SET $edit WHERE id = {$activity["id"]}");

    $sessions = db_select_all("* FROM session WHERE id_activity = {$activity["id"]}");
    foreach ($sessions as $s)
    {
	$a = date_to_timestamp($s["begin_date"]) + $move;
	$a = db_form_date($a);

	$b = date_to_timestamp($s["end_date"]) + $move;
	$b = db_form_date($b);

	$Database->query("UPDATE session SET begin_date = '$a', end_date = '$b' WHERE id = {$s["id"]}");
    }
    return (new ValueResponse($activity["id"]));
}
