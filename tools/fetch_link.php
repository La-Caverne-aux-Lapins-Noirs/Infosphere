<?php

// L'id est rattaché au membre de droite
// Les informations remontés sont sur le membre de gauche
function fetch_linkr($a, $b, $id, $by_name = false, $lng = [], $addsql = "", $table_name = "")
{
    global $Language;

    if (!is_symbol($a))
	return (new ErrorResponse("InvalidTableName", $b));
    if (($id = resolve_codename($b, $id))->is_error())
	return ($id);
    $id = $id->value;

    if (($str = forge_language_fields($lng, true, true)) != "")
	$str = ", $str";

    if ($table_name == "")
	$table_name = "{$a}_{$b}";

    $sql = "
	{$a}.*, {$a}.id as id_{$a}, $table_name.id_{$b} $str
        FROM $table_name
        LEFT JOIN $a ON $table_name.id_{$a} = {$a}.id
        WHERE $table_name.id_{$b} = $id $addsql
    ";
    return (new ValueResponse(db_select_all($sql, $by_name ? "codename" : "")));
}

// L'id est rattaché au membre de gauche.
// La requete remonte des informations sur l'element de droite
function fetch_link($a, $b, $id, $by_name = false, $lng = [], $addsql = "", $table_name = "")
{
    global $Language;

    if (!is_symbol($b))
	return (new ErrorResponse("InvalidTableName", $b));
    if (($id = resolve_codename($a, $id))->is_error())
	return ($id);
    $id = $id->value;

    if (($str = forge_language_fields($lng, true, true)) != "")
	$str = ", $str";

    if ($table_name == "")
	$table_name = "{$a}_{$b}";

    $sql = "
	{$b}.*, {$b}.id as id_{$b}, $table_name.id_{$a} $str
        FROM $table_name
        LEFT JOIN $b ON $table_name.id_{$b} = {$b}.id
        WHERE $table_name.id_{$a} = $id $addsql
    ";
    return (new ValueResponse(db_select_all($sql, $by_name ? "codename" : "")));
}

function fetch_linkf(array $data, $right = false)
{
    $by_name = false;
    $language = [];
    $added_sql = "";
    extract($data);
    if ($right == false)
	return (fetch_link($left_field, $right_field, $id, $by_name, $language, $added_sql, $table_name));
    return (fetch_linkr($left_field, $right_field, $id, $by_name, $language, $added_sql, $table_name));
}

