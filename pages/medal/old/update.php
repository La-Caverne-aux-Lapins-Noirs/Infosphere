<?php

function update_medal($id, $cd, $lg, $nn = NULL, $nd = NULL, $ni = NULL)
{
    global $Configuration;
    global $Database;

    $lst = array_merge(forge_language_fields(["name", "description"]), ["icon"]);
    if (($ret = update_table("medal", $id, $val, [], $lst))->is_error())
	return ($ret); // @codeCoverageIgnore
    $ret = $ret->value;
    if (isset($val["icon"]))
    {
	$icon_file = $Configuration->MedalsDir."/$cd.png";
	if (($msg = upload_png($ni, $icon_file, [100, 100], EXACT_PICTURE_SIZE, true)) != "")
            return ($msg);
    }
    return ("");
}
