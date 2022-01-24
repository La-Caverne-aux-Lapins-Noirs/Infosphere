<?php

function add_category($codename, $rate, $select_rate, $language) {
    global $Configuration;
    global $Database;
    global $LanguageList;
    global $Dictionnary;

    if (!isset($codename))
	return ("MissingCodeName");
    else if (!is_symbol($codename))
	return ("BadCodeName");

    $check = $Database->query("SELECT codename FROM category WHERE codename = '$codename'");
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

    if($rate == "new" || $rate = "import_new") {
	if ($Database->query("INSERT INTO stud_rate (id) VALUES (NULL)") == false)
	    return("CannotAdd");
	$rate_query = $Database->query("SELECT id FROM stud_rate ORDER BY id DESC LIMIT 1");
	while($select_rates = $rate_query->fetch_assoc())
	    $select_rate = $select_rates["id"];
    }
    $forge = "
      INSERT INTO category (codename, ".implode(",", $lng).", id_stud_rate)
      VALUES ('$codename', ".implode(",", $txts).", '$select_rate')
      ";
    if ($Database->query($forge) == false)
	return ("CannotAdd");
    add_log(CREATIVE_OPERATION, "Student Gallery: \"$codename\" Category added");
    return ("");
}
