<?php

define("SORT_BY_NAME", true);
define("DO_NOT_SORT", false);
define("USE_DELETE_FIELD", true);
define("NO_DELETE_FIELD", false);
define("PACK_DATA", true);
define("DONT_PACK_DATA", false);

function fetch_data(
    $table,
    $id = -1,
    $lng_fields = [],
    $codename_colum = "codename",
    $by_name = true,
    $delete_field = true,
    $pack = false,
    $additional_where = [],
    $order = [],
    $page_size = -1,
    $page = 50)
{
    if (is_array($table))
	return (fetch_data_array($table));

    if (!is_array($lng_fields))
	$lng_fields = [$lng_fields];
    $forge = "";
    if ($id != -1)
    {
	if (($ret = resolve_codename($table, $id, $codename_colum))->is_error())
	    return ($ret);
	$forge = " WHERE id = {$ret->value} ";
	if ($delete_field)
	    $forge .= " AND deleted IS NULL ";
    }
    else if ($delete_field)
	$forge = " WHERE deleted IS NULL ";

    if (($ret = unroll($additional_where, WHERE)) != "")
    {
	if ($forge == "")
	    $forge .= " WHERE $ret";
	else
	    $forge .= " AND $ret";
    }
    $order_by = "id ASC";
    if (count($order) && $by_name == false)
	$order_by = implode(",", $order);

    if (($str = forge_language_fields($lng_fields, true, true)) != "")
	$str = ", ".$str;
    if ($page_size != -1)
	$limit = " LIMIT ".($page_size * $page).", ".$page_size;
    else
	$limit = "";
    $forge = " * $str FROM $table $forge ORDER BY $order_by $limit";
    $ret = db_select_all($forge, $by_name ? $codename_colum : "");
    if ($id != -1 && $pack && count($ret) > 1)
	$ret = $ret[array_key_first($ret)];
    return (new ValueResponse($ret));
}

function fetch_data_array($arr)
{
    if (!isset($arr["table"]))
	return (new ErrorResponse("MissingParameter", "table name"));
    $default = [
	"id" => -1,
	"language_fields" => [],
	"codename_column" => "codename",
	"by_name" => true,
	"delete_field" => true,
	"pack" => false,
	"additional_where" => [],
	"order" => []
    ];
    foreach ($default as $i => $v)
	if (!isset($arr[$i]))
	    $arr[$i] = $v;
    return (@fetch_data(
	$arr["table"],
	$arr["id"],
	$arr["language_fields"],
	$arr["codename_column"],
	$arr["by_name"],
	$arr["delete_field"],
	$arr["pack"],
	$arr["additional_where"],
	$arr["order"]
    ));
}
