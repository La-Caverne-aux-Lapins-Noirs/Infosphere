<?php

function get_user_parents(&$usr, $by_name = false)
{
    if (isset($usr["parents"]))
	return ($usr["parents"]);
    $usr["parents"] = db_select_all("
       user.codename as codename,
       parent_child.id as id,
       parent_child.id_parent as id_user
       FROM parent_child
       LEFT JOIN user ON parent_child.id_parent = user.id
       WHERE parent_child.id_child = ".$usr["id"]."
       ", $by_name ? "codename" : "");
    return ($usr["parents"]);
}
