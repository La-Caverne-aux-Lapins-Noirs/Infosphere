<?php

function update_link($idleft, $idright, $table_left, $table_right, $props = [], $table_name = "")
{
    global $Database;

    if (($idleft = resolve_codename($table_left, $idleft))->is_error())
	return ($idleft);
    $idleft = $idleft->value;
    if (($idright = resolve_codename($table_right, $idright))->is_error())
	return ($idright);
    $idright = $idright->value;

    if ($table_name == "")
	$table_name = "{$table_left}_{$table_right}";
    else if (!is_symbol($table_name))
	return (new ErrorResponse("InvalidTableName", $table_name));

    $check = $Database->query("
      SELECT id FROM $table_name
      WHERE id_$table_left = $idleft AND id_$table_right = $idright
    ");
    if (!($check = $check->fetch_assoc()) && !isset($check["id"]))
	return (new ErrorResponse("AssociationDoesNotExist"));

    $nprops = [];
    foreach ($props as $k => $v)
    {
	if (!is_symbol($k))
	    return (new ErrorResponse("InvalidParameter", $k));
	$nprops[] = "`$k` = '".$Database->real_escape_string($v)."'";
    }
    if (($mprops = implode(", ", $nprops)) == "")
	return (new ValueResponse(array_merge(["id" => $check["id"]], $nprops)));

    if ($Database->query("UPDATE $table_name SET $mprops WHERE id = ".$check["id"]) == false)
	return (new ErrorResponse("CannotEdit")); // @codeCoverageIgnore
    $last_id = $check["id"];
    global $User;

    add_log(EDITING_OPERATION, "Link $table_name: $idleft-$idright", $User["id"]);
    return (new ValueResponse(array_merge(["id" => $last_id], $props)));
}
