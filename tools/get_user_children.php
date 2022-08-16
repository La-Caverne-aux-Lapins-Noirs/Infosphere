<?php

function get_user_children(&$usr, $by_name = false)
{
    if (isset($usrr["children"]))
	return ($usr["children"]);
    $usr["children"] = db_select_all("
       user.codename as codename,
       parent_child.id as id,
       parent_child.id_child as id_user
       FROM parent_child
       LEFT JOIN user ON parent_child.id_child = user.id
       WHERE parent_child.id_parent = ".$usr["id"]."
       ", $by_name ? "codename" : "");
    return ($usr["children"]);
}
