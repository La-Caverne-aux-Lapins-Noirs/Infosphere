<?php

function add_room($codename, $capacity, $map, $conf, $language)
{
    global $DeskTypes;
    global $Database;
    global $LanguageList;

    if (($ret = resolve_codename("room", $codename))->is_error() == false)
	return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
    else if ($ret->label != "BadCodeName")
	return ($ret);

    if (!isset($capacity) || strlen($capacity) == 0)
	return (new ErrorResponse("MissingCapacity"));
    if (!is_number($capacity))
	return (new ErrorResponse("InvalidCapacity", $capacity));

    if (!isset($map))
	$map = "";
    else if (filesize($map) > 1024 * 1024 * 2)
	return (new ErrorResponse("FileTooBig", "$map: ".filesize($map))); // @codeCoverageIgnore
    else
	$map = $Database->real_escape_string(file_get_contents($map));

    if (!isset($conf) || $conf == "")
	$conf = [];
    else if (filesize($conf) > 1024 * 64 - 1)
	return (new ErrorResponse("FileTooBig", "$conf: ".filesize($conf))); // @codeCoverageIgnore
    else
    {
	$cnffile = $conf;
	if (($conf = load_configuration($conf))->is_error())
	    return ($conf); // @codeCoverageIgnore
	$conf = $conf->value;
	foreach ($conf as $i => $desk)
	{
	    if (!isset($desk["Position"]) || count($desk["Position"]) != 4)
		return (new ErrorResponse("BadFileFormat", "$cnffile: [$i].Position")); // @codeCoverageIgnore
	    foreach ($desk["Position"] as $j => $v)
	    {
		if (!is_number($v))
		    return (new ErrorResponse("BadFileFormat", "$cnffile: [$i].Position[$j]")); // @codeCoverageIgnore
	    }
	    if (!isset($desk["Type"]))
		return (new ErrorResponse("BadFileFormat", "$cnffile: [$i].Type")); // @codeCoverageIgnore
	    if (($idx = array_search($desk["Type"], $DeskTypes, true)) === false)
		return (new ErrorResponse("BadFileFormat", "$cnffile: [$i].Type")); // @codeCoverageIgnore
	    if (@!is_symbol($desk["Name"]))
		return (new ErrorResonse("BadFileFormat", "$cnffile: [$i].Name")); // @codeCoverageIgnore
	    if (!isset($desk["Mac"]))
		return (new ErrorResponse("BadFileFormat", "$cnffile: [$i].Mac")); // @codeCoverageIgnore
	    if (!isset($desk["Ip"]))
		return (new ErrorResponse("BadFileFormat", "$cnffile: [$i].Ip")); // @codeCoverageIgnore
	}
    }

    if (($ret = forge_language_insert("name", $language, true))->is_error())
	return ($ret);
    $ret = $ret->value;

    $forge = "
      INSERT INTO room (codename, ".$ret["Labels"].", capacity, map)
      VALUES ('$codename', ".$ret["Texts"].", $capacity, '$map')
      ";
    if ($Database->query($forge) == false)
	return (new ErrorResponse("CannotAdd")); // @codeCoverageIgnore
    $last_id = $Database->insert_id;

    foreach ($conf as $desk)
    {
	$forge = "
           INSERT INTO room_desk (id_room, codename, mac, ip, type, x, y, w, h)
           VALUES ($last_id, '{$desk['Name']}', '{$desk['Mac']}', '{$desk['Ip']}',
                ".array_search($desk["Type"], $DeskTypes, true).",
                {$desk['Position'][0]},
                {$desk['Position'][1]},
                {$desk['Position'][2]},
                {$desk['Position'][3]}
		)";
	if ($Database->query($forge) == false)
	{ // @codeCoverageIgnoreStart
	    $Database->query("DELETE FROM room_desk WHERE id_room = $last_id");
	    $Database->query("DELETE FROM room WHERE id = $last_id");
	    return (new ErrorResponse("CannotAdd"));
	} // @codeCoverageIgnoreEnd
    }

    add_log(CREATIVE_OPERATION, "Room $codename");
    return (new Response);
}
