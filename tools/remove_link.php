<?php

function remove_link(
    $idleft,
    $idright,
    $table_left,
    $table_right,
    $strict = false,
    $table_name = "",
    $fleft = "",
    $fright = ""
)
{
    global $Database;

    if (!isset($idleft) || !isset($idright))
	return (new ErrorResponse("MissingId"));

    if (($idleft = resolve_codename($table_left, $idleft))->is_error())
	return ($idleft);
    $idleft = $idleft->value;

    if (($idright = resolve_codename($table_right, $idright))->is_error())
	return ($idright);
    $idright = $idright->value;

    if (!is_symbol($table_left) || !is_symbol($table_right))
	return (new ErrorResponse("BadCodeName"));

    if ($table_name == "")
	$table_name = "{$table_left}_{$table_right}";
    else if (!is_symbol($table_name))
	return (new ErrorResponse("InvalidTableName", $table_name));

    if ($fleft == "")
	$fleft = $table_left;
    if ($fright == "")
	$fright = $table_right;

    $check = $Database->query("
      SELECT id FROM $table_name
      WHERE id_$fleft = $idleft AND id_$fright = $idright
    ");
    if (!($check = $check->fetch_assoc()) || !isset($check["id"]))
    {
	if ($strict)
	    return (new ErrorResponse("AssociationDoesNotExist"));
	return (new Response);
    }

    if ($Database->query("
        DELETE FROM $table_name
        WHERE id_$fleft = $idleft AND id_$fright = $idright
    ") == false)
        return (new ErrorResponse("CannotEdit")); // @codeCoverageIgnore
    global $User;

    add_log(DESTRUCTIVE_OPERATION, "Link $table_name: $idleft-$idright", $User["id"]);
    return (new Response);
}

function remove_links($left, $right, $tleft, $tright, $strict = false, $table_name = "", $fleft = "", $fright = "")
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

    if (($tmp = check_codename($tleft, $left))->is_error())
	return ($tmp);
    if (count($tmp->value) != 0)
	return (new ErrorResponse("BadCodeName", $tmp->value));

    if (($tmp = check_codename($tright, $right))->is_error())
	return ($tmp);
    if (count($tmp->value) != 0)
	return (new ErrorResponse("BadCodeName", $tmp->value));

    $val = [];
    foreach ($left as $l)
    {
	foreach ($right as $r)
	{
	    if (($msg = remove_link($l, $r, $tleft, $tright, $strict, $table_name, $fleft, $fright))->is_error())
		return ($msg);
	    $val[] = $msg->value;
	}
    }
    return (new ValueResponse($val));
}

