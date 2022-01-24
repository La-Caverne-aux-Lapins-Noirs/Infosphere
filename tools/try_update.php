<?php

function try_update(
    $table,
    $codename = "",
    $trusted_fields = [],
    $icon = "",
    $icon_dir = "",
    $language_fields = [],
    $language = [],
    $codename_column = "codename",
    $fetch = false)
{
    if (is_array($table))
	return (try_update_array($table));
    if ($codename == "")
	return (new ErrorResponse("MissingCodeName"));
    global $Database;
    global $Language;

    if (($ret = resolve_codename($table, $codename, $codename_column))->is_error())
	return ($ret);

    $id = $ret->value;
    $updated_fields = [];

    $lng = [];
    if (count($language_fields))
    {
	if (($ret = forge_language_update($language_fields, $language, false))->is_error())
	    return ($ret); // @codeCoverageIgnore
	$updated_fields = array_merge($updated_fields, $ret->value);
    }

    $icon_file = "";
    if ($icon != "" && $icon_dir != "")
    {
	$icon_file = $icon_dir.$codename.".png";
	if (($msg = upload_png($icon, $icon_file, [100, 100], MINIMUM_PICTURE_SIZE))->is_error())
	    return ($msg); // @codeCoverageIgnore
	$updated_fields[] = "`icon` = '$icon_file'";
    }

    foreach ($trusted_fields as $i => $v)
    {
	if (!is_symbol($i))
	    return (new ErrorResponse("InvalidParameter", $i));
	if ($v == NULL)
	    $updated_fields[] = "`$i` = NULL";
	else
	    $updated_fields[] = "`$i` = '".$Database->real_escape_string($v)."'";
    }

    if (count($updated_fields))
    {
	$updated_fields = implode(", ", $updated_fields);
	$forge = "
           UPDATE `$table`
           SET $updated_fields
           WHERE id = $id
	";
	if ($Database->query($forge) == false)
	    return (new ErrorResponse("CannotAdd")); // @codeCoverageIgnore
	add_log(EDITING_OPERATION, "$table $codename");
    }
    $id = $id;

    // Si on a demandé une récupération complete (avec valeur par defaut et tout et tout)
    if ($fetch)
    {
	if (($ret = fetch_data($table, $id, $language_fields, $codename_column, false, false, false, []))->is_error())
	    return ($ret); // @codeCoverageIgnore
	return (new ValueResponse($ret->value[0]));
    }

    // Sinon on renvoi ce qu'on a sous la main, en imitant le format le plus possible
    $assoc = [];
    foreach ($language_fields as $i => $v)
	if (isset($language[$Language."_".$v]))
	    $assoc[$v] = $language[$Language."_".$v];
    $assoc = array_merge($assoc, $trusted_fields);

    return (new ValueResponse(array_merge($assoc, [
	"id" => $id,
	"codename" => $codename,
	"icon" => $icon_file
    ])));
}

function try_update_array($arr)
{
    if (!isset($arr["table"]))
	return (new ErrorResponse("MissingParameter", "table name"));
    if (!isset($arr["codename"]))
	return (new ErrorResponse("MissingCodeName"));
    $default = [
	"trusted_fields" => [],
	"icon" => "",
	"icon_dir" => "",
	"language_fields" => [],
	"language" => [],
	"codename_column" => "codename",
	"fetch" => false
    ];
    foreach ($default as $i => $v)
	if (!isset($arr[$i]))
	    $arr[$i] = $v;
    return (@try_update(
	$arr["table"],
	$arr["codename"],
	$arr["trusted_fields"],
	$arr["icon"],
	$arr["icon_dir"],
	$arr["language_fields"],
	$arr["language"],
	$arr["codename_column"],
	$arr["delete_field"],
	$arr["fetch"]
    ));
}
