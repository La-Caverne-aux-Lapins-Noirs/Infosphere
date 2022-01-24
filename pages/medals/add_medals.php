<?php

function add_medals($codename, $icon, $language)
{
    global $Configuration;
    global $Database;
    global $LanguageList;
    global $Dictionnary;

    if (!isset($codename))
	return ("MissingCodeName");
    else if (!is_symbol($codename))
	return ("BadCodeName");
    if (!isset($icon))
	return ("MissingFile");

    $check = $Database->query("SELECT codename FROM medals WHERE codename = '$codename'");
    if (($check = $check->fetch_assoc()) != NULL)
	return ("CodeNameAlreadyUsed");

    $lng = [];
    $txts = [];
    foreach ($LanguageList as $k => $v)
    {
	if (!isset($language[$k."_name"]) || $language[$k."_name"] == "")
	    return ("MissingName");
	if (!isset($language[$k."_description"]) || $language[$k."_description"] == "")
	    return ("MissingDescription");
	$txts[] = "'".$Database->real_escape_string($language[$k."_name"])."'";
	$txts[] = "'".$Database->real_escape_string($language[$k."_description"])."'";
	$lng[] = $k."_name";
	$lng[] = $k."_description";
    }

    $icon_file = $Configuration->MedalsDir."/$codename.png";
    if (($msg = upload_png($icon, $icon_file, [100, 100], EXACT_PICTURE_SIZE, true)) != "")
        return ($msg);

    $forge = "
      INSERT INTO medal (codename, ".implode(",", $lng).", icon)
      VALUES ('$codename', ".implode(",", $txts).", '$icon_file')
      ";
    if ($Database->query($forge) == false)
	return ("CannotAdd");
    add_log(CREATIVE_OPERATION, "Medal $codename");
    return ("");
}

