<?php

function insert_activity($act, $parent, $codename, $sdate, $template = false)
{
    global $Database;

    $dates = [
	"emergence_date",
	"registration_date",
	"close_date",
	"subject_appeir_date",
	"subject_disappeir_date",
	"pickup_date",
	"done_date"
    ];

    $keys = [];
    $vals = [];
    // On instancie, donc tous les champs sont a NULL pour faire reference
    // au template
    if ($template == false)
     {
	foreach ($dates as $d)
	{
	    if ($act->$d != NULL)
	    {
		$keys[] = $d;
		$vals[] = "'".db_form_date($act->$d + $sdate)."'";
	    }
	}
    }
    // On copie, donc on copie TOUT - sauf ce qui depend des paramètres
    else
    {
	$rowsx = db_select_all("
	    `COLUMN_NAME`
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA`='infosphere'
            AND `TABLE_NAME`='activity'
	");
	$rows = [];
	foreach ($rowsx as $x)
	{
	    $rows[] = $x["COLUMN_NAME"];
	}
	foreach (
	    ["id", "is_template", "id_template", "template_link",
	     "medal_template", "class_template", "type", "parent_activity",
	     "codename", "enabled"
	    ] as $r)
	{
	    unset($rows[array_search($r, $rows)]);
	}
	foreach (array_values($rows) as $x)
	{
	    if (array_search($x, $dates) !== false)
	    {
		if ($act->$x === NULL)
		    $tmp = "NULL";
		else
		    $tmp = "'".db_form_date($act->$x)."'";
	    }
	    else
	    {
		$tmp = $act->$x;
		if ($tmp === false)
		    $tmp = 0;
		else if (is_number($tmp))
		    $tmp = (int)$tmp;
		else if ($tmp === true)
		    $tmp = 1;
		else if ($tmp === NULL)
		    $tmp = "NULL";
		else
		    $tmp = "'".$Database->real_escape_string($tmp)."'";
	    }
	    if ($tmp != "''")
	    {
		$keys[] = $x;
		$vals[] = $tmp;
	    }
	}
    }

    if ($act->emergence_date === NULL)
	$act->emergence_date = $sdate;
    if ($act->registration_date === NULL)
	$act->registration_date = $act->emergence_date;

    if (count($keys))
    {
	$keys = ", ".implode($keys, ",");
	$valss = "";
	foreach ($vals as $v)
	{
	    $valss .= ", ".$v;
	}
	$vals = $valss;
    }
    else
    {
	$vals = "";
	$keys = "";
    }

    if ($template == false)
	$template = "0, $act->id, 1, 1, 1";
    else
	$template = "1, $act->id, 1, 1, 1";

    if ($Database->query("
      INSERT INTO activity
        (is_template,
         id_template,
         template_link,
         medal_template,
         class_template,
         type,
         parent_activity,
         codename,
         enabled $keys
	)
      VALUES
        ($template, $act->type, $parent, '$codename', 1 $vals)
    ") == NULL)
        return (-1);
    $actid = $Database->insert_id;
    foreach ($act->session as $sess)
    {
	if ($sess->deleted)
	    continue ;
	$begin_date = db_form_date($sess->begin_date + $sdate);
	$end_date = db_form_date($sess->end_date + $sdate);
	$maximum_subscription = $sess->maximum_subscription;
	$Database->query("
           INSERT INTO session
             (id_activity, begin_date, end_date, maximum_subscription)
           VALUES
             ($actid, '$begin_date', '$end_date', $maximum_subscription)
	");
	if ($act->slot_duration != -1)
	    @generate_slots($Database->last_id, datex("H:i", $act->slot_duration), 1);
    }
    return ($actid);
}

function instantiate_template($activity, $sdate, $suffix = "", $parent = -1)
{
    global $Database;

    if ($activity->is_template == false)
	return (false);
    $sdate = first_second_of_day($sdate);
    if ($suffix == "")
	$suffix = datex("d_m_Y", date_to_timestamp($sdate))."_".$activity->id;

    $codename = $activity->codename."_".$suffix;
    if (($id = insert_activity($activity, $parent, $codename, $sdate)) == -1)
	return (false);
    foreach ($activity->subactivities as $sub)
    {
	$codename = $sub->codename."_".$suffix;
	if (($new_id = insert_activity($sub, $id, $codename, $sdate)) == -1)
	    return (false);

	$ref_switch[$sub->id]["newid"] = $new_id;
	$ref_switch[$sub->id]["ref"] = $sub->reference_activity;
    }
    if (isset($ref_switch))
    {
	foreach ($ref_switch as $ref)
	{
	    if ($ref["ref"] == -1)
		continue ;
	    if (!isset($ref_switch[$ref["ref"]]))
		continue ;
	    $nw = $ref_switch[$ref["ref"]]["newid"];
	    $Database->query("
                UPDATE activity
                SET reference_activity = $nw
                WHERE id = {$ref["newid"]}
	    ");
	}
    }
    return (true);
}

