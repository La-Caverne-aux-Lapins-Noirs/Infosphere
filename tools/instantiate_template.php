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
	if (@$act->emergence_date == NULL)
	    $act->emergence_date = 60 * 60 * 9; // 9h le matin
	if (@$act->registration_date == NULL)
	    $act->registration_date = $act->emergence_date;

	foreach ($dates as $d)
	{
	    if (@$act->$d != NULL)
	    {
		$keys[] = $d;
		$vals[] = "'".db_form_date(
		    date_to_timestamp($act->$d) + date_to_timestamp($sdate)
		)."'";
	    }
	}
    }
    // On copie, donc on copie TOUT - sauf ce qui depend des paramètres
    else
    {
	$dbname = $Database->real_escape_string($Database->dbname);
	$rows = db_select_rows("activity", [
	    "id", "is_template", "id_template", "template_link",
	    "medal_template", "support_template", "type", "parent_activity",
	    "codename", "disabled"
	]);
	foreach ($rows as $x)
	{
	    if (array_search($x, $dates) !== false)
	    {
		if (@$act->$x === NULL)
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

    $ret = db_select_one("
      * FROM activity
      WHERE is_template = 0
      AND id_template = {$act->id}
      AND codename = '$codename'
    ");
    if ($ret != NULL)
	return (new ValueResponse($ret["id"]));
    $qr = "
      INSERT INTO activity
        (is_template,
         id_template,
         template_link,
         medal_template,
         support_template,
         type,
         parent_activity,
         codename,
         disabled $keys
	)
      VALUES
        ($template, $act->type, $parent, '$codename', NULL $vals)
    ";
    if ($Database->query($qr) == NULL)
	return (new ErrorResponse("CannotAdd"));
    $actid = $Database->insert_id;
    foreach ($act->session as $sess)
    {
	if ($sess->deleted)
	    continue ;
	$begin_date = db_form_date($sess->begin_date + $sdate);
	$end_date = db_form_date($sess->end_date + $sdate);
	if (($maximum_subscription = $sess->maximum_subscription) == NULL)
	    $maximum_subscription = "NULL";
	$Database->query("
           INSERT INTO session
             (id_activity, begin_date, end_date, maximum_subscription)
           VALUES
             ($actid, '$begin_date', '$end_date', $maximum_subscription)
	");
	if ($act->slot_duration != -1)
	    @generate_slots($Database->last_id, datex("H:i", $act->slot_duration), 1);
	$last_session = $Database->insert_id;
	foreach ($sess->room as $room)
	    handle_links("session", "room", $last_session, $room);
    }
    return (new ValueResponse($actid));
}

function instantiate_template($activity, $sdate, $prefix = "", $suffix = "", $parent = -1)
{
    global $Database;

    if ($parent === NULL)
	$parent = -1;
    if ($activity->is_template == false)
	return (new ErrorResponse("InvalidParameter"));
    $sdate = first_second_of_day($sdate);

    if ($suffix == "")
	$suffix = "_".datex("d_m_Y", date_to_timestamp($sdate))."_".$activity->id;

    $generated = [];
    $codename = $prefix.$activity->codename.$suffix;
    if (($id = insert_activity($activity, $parent, $codename, $sdate))->is_error())
	return ($id);
    $generated[] = $id = $id->value;
    foreach ($activity->subactivities as $sub)
    {
	if ($sub->enabled == false)
	    continue ;
	$codename = $sub->codename."_".$suffix;
	if (($new_id = insert_activity($sub, $id, $codename, $sdate))->is_error())
	    return ($new_id);
	$generated[] = $new_id = $new_id->value;

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
    // On ne retourne que le premier car actuellement
    // je n'ai pas le temps de faire un vrai ménage et la partie API
    // comporte une partie nettoyage en cas d'echec.
    // Idéalement, il faudrait qu'en cas d'echec, tout soit nettoyé avant de quitter
    // la fonction.
    return (new ValueResponse($generated[0]));
}

