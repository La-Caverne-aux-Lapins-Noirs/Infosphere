<?php

function try_insert(
    $table,
    $codename = "",
    $trusted_fields = [],
    $icon = "",
    $icon_dir = "",
    $language_fields = [],
    $language = [],
    $codename_column = "codename",
    $delete_field = true,
    $fetch = false)
{
    if (is_array($table))
	return (try_insert_array($table));
    if ($codename == "")
	return (new ErrorResponse("MissingCodeName"));
    global $Database;
    global $Language;

    if (($ret = resolve_codename($table, $codename, $codename_column))->is_error() == false)
	return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
    else if ($ret->label != "BadCodeName")
	return ($ret);

    $ret = [];
    if (count($language_fields))
    {
	if (($ret = forge_language_insert($language_fields, $language, true))->is_error())
	    return ($ret);
	$ret = $ret->value;
	$ret["Labels"] = ",".$ret["Labels"];
	$ret["Texts"] = ",".$ret["Texts"];
    }
    else
    {
	$ret["Labels"] = "";
	$ret["Texts"] = "";
    }

    $trusted_label = [];
    $trusted_value = [];
    foreach ($trusted_fields as $k => $v)
    {
	if (!is_symbol($k))
	    return (new ErrorResponse("InvalidParameter", $k));
	$trusted_label[] = $k;
	if ($v === NULL)
	    $trusted_value[] = "NULL";
	else
	    $trusted_value[] = "'".$Database->real_escape_string($v)."'";
    }
    if (count($trusted_label))
    {
	$trusted_label = ",".implode(",", $trusted_label);
	$trusted_value = ",".implode(",", $trusted_value);
    }
    else
    {
	$trusted_label = "";
	$trusted_value = "";
    }

    $icon_file = "";
    if ($icon != "" && $icon_dir != "")
    {
	$icon_file = $icon_dir."icon.png";
	if (($ndir = new_directory($icon_file))->is_error())
	    return ($ret);
	if (file_exists($icon))
	{
	    // Si le fichier existe: c'est certainement un upload via POST.
	    if (($msg = upload_png($icon, $icon_file, [100, 100], MINIMUM_PICTURE_SIZE))->is_error())
		return ($msg); // @codeCoverageIgnore
	}
	else
	{
	    // Si le fichier n'existe pas: c'est un upload via AJAX, avec le fichier b64.
	    if (isset($icon[0]["content"]))
		$icon = base64_decode($icon[0]["content"]);
	    else
		$icon = base64_decode($icon);
	    if (file_put_contents($icon_file, $icon) === false)
		return (new ErrorResponse("CannotWritePngFile"));
	}
    }
    $forge = "
      INSERT INTO `$table` ($codename_column $trusted_label {$ret["Labels"]})
      VALUES ('$codename' $trusted_value {$ret["Texts"]})
      ";
    if ($Database->query($forge) == false)
	return (new ErrorResponse("CannotAdd")); // @codeCoverageIgnore
    $last_id = $Database->insert_id;
    add_log(CREATIVE_OPERATION, "$table $codename");

    // Si on a demandé une récupération complete (avec valeur par defaut et tout et tout)
    if ($fetch)
    {
	if (($ret = fetch_data($table, $last_id, $language_fields, $codename_column, false, $delete_field))->is_error())
	    return ($ret); // @codeCoverageIgnore
	return (new ValueResponse($ret->value[0]));
    }

    // Sinon on renvoi ce qu'on a sous la main - en imitant le plus possible le format
    $assoc = [];
    foreach ($language_fields as $i => $v)
    {
	if (is_number($i))
	    $assoc[$v] = $language[$Language."_".$v];
	else if (isset($language[$Language."_".$i]))
	    $assoc[$i] = $language[$Language."_".$i];
    }
    $assoc = array_merge($assoc, $trusted_fields);

    return (new ValueResponse(array_merge($assoc, [
	"id" => $last_id,
	"codename" => $codename,
	"icon" => $icon_file
    ])));
}

function try_insert_array($arr)
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
	"delete_field" => true,
	"fetch" => false
    ];
    foreach ($default as $i => $v)
	if (!isset($arr[$i]))
	    $arr[$i] = $v;
    return (@try_insert(
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
