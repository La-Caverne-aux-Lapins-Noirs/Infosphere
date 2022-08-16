<?php

function add_link(
    $idleft,
    $idright,
    $table_left,
    $table_right,
    $strict = false,
    $props = [],
    $table_name = "",
    $allow_duplicate = false,
    $fleft = "",
    $fright = "",
    $no_deleted = false
)
{
    global $Database;

    if (($idleft = resolve_codename($table_left, $idleft, "codename", true))->is_error())
	return ($idleft);
    if (isset($idleft->value["deleted"]) && $idleft->value["deleted"] != NULL)
	return (new ErrorResponse("Deleted", $idleft->value["codename"]));
    $idleft = $idleft->value["id"];

    if (($idright = resolve_codename($table_right, $idright, "codename", true))->is_error())
	return ($idright);
    if (isset($idright->value["deleted"]) && $idright->value["deleted"] != NULL)
	return (new ErrorResponse("Deleted", $idright->value["codename"]));
    $idright = $idright->value["id"];

    if ($table_name == "")
	$table_name = "{$table_left}_{$table_right}";
    else if (!is_symbol($table_name))
	return (new ErrorResponse("InvalidTableName", $table_name));

    if ($fleft == "")
	$fleft = $table_left;
    if ($fright == "")
	$fright = $table_right;

    if ($allow_duplicate == false)
    {
	if (count($props_name_tab = array_keys($props)))
	    $props_name = ", ".implode(",", $props_name_tab);
	else
	    $props_name = "";
	$check = $Database->query("
          SELECT id $props_name FROM $table_name
          WHERE id_$fleft = $idleft AND id_$fright = $idright
	");
	if (($check = $check->fetch_assoc()) && isset($check["id"]))
	{
	    // On regarde si il y a un changement de prévu dans les propriétés du lien.
	    $edited = false;
	    $new_fields = [];
	    foreach ($props as $k => $v)
	    {
		// Un changement ?
		if ($v != $check[$k])
		{
		    $edited = true;
		    $new_fields[] = " `$k` = '$v' ";
		}
	    }
	    if ($edited)
	    {
		$props_name = substr($props_name, 2);
		$Database->query("
                   UPDATE $table_name SET ".implode(" , ", $new_fields)." WHERE id = ".$check["id"]
		);
		return (new ValueResponse(array_merge(["id" => $check["id"]], $props)));
	    }
	    if ($strict)
		return (new ErrorResponse("AlreadyAssociated"));
	    return (new ValueResponse(array_merge(["id" => $check["id"]], $props)));
	}
    }

    $props_name = [];
    $props_value = [];
    foreach ($props as $k => $v)
    {
	if (!is_symbol($k))
	    return (new ErrorResponse("InvalidParameter", $k));
	$props_name[] = "`$k`";
	$props_value[] = "'".$Database->real_escape_string($v)."'";
    }
    if (($props_name = implode(",", $props_name)) != "")
	$props_name  = ", ".$props_name;
    if (($props_value = implode(",", $props_value)) != "")
	$props_value  = ", ".$props_value;

    if ($Database->query("
      INSERT INTO $table_name (id_$fleft, id_$fright $props_name)
      VALUES ($idleft, $idright $props_value)
    ") == false)
    {
	return (new ErrorResponse("CannotEdit")); // @codeCoverageIgnore
    }
    $last_id = $Database->insert_id;
    global $User;

    add_log(CREATIVE_OPERATION, "Link {$table_name}: $idleft-$idright", $User["id"]);
    return (new ValueResponse(array_merge(["id" => $last_id], $props)));
}

function add_links($left, $right, $tleft, $tright, $strict = false, $props = [], $table_name = "", $allow_duplicate = false, $fleft = "", $fright = "")
{
    if (!isset($left))
	return (new ErrorResponse("MissingId"));
    if (!isset($right))
	return (new ErrorResponse("MissingId"));

    if (is_number($left))
	$left = [$left];
    else if (!is_array($left))
    {
	if (($left = split_symbols($left))->is_error())
	    return ($left); // @codeCoverageIgnore
	$left = $left->value;
    }

    if (is_number($right))
	$right = [$right];
    else if (!is_array($right))
    {
	if (($right = split_symbols($right))->is_error())
	    return ($right); // @codeCoverageIgnore
	$right = $right->value;
    }

    $val = [];
    foreach ($left as $l)
    {
	foreach ($right as $r)
	{
	    if (($msg = add_link($l, $r, $tleft, $tright, $strict, $props, $table_name, $allow_duplicate, $fleft, $fright))->is_error())
		return ($msg);
	    $val[] = $msg->value;
	}
    }
    return (new ValueResponse($val));
}

