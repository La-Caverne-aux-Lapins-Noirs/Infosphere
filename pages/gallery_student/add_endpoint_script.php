<?php

function add_endpoint($id, $codename, $valrange) {
    require_once("fetch_category.php");
    global $Database;
    
    $category = fetch_category($id);

    if (!isset($codename))
	return ("MissingCodeName");
    else if (!is_symbol($codename))
	return ("BadCodeName");
    
    $forge = "
      INSERT INTO stud_endpoint (codename, valrange, id_stud_rate)
      VALUES ('$codename', '$valrange', '".$category["id_rate"]."')
      ";
    if ($Database->query($forge) == false)
	return ("CannotAdd");
    add_log(CREATIVE_OPERATION, "Student Gallery: \"$codename\" Endpoint added in \"".$category["id_rate"]."\" for \"".$category["codename"]."\" category");
    return ("");
}
