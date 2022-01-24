<?php

function get_user_children(&$User)
{
    if (isset($User["children"]))
	return ($User);
    $User["children"] = db_select_all("
       user.codename as codename,
       parent_child.id_child as id
       FROM parent_child
       LEFT JOIN user ON parent_child.id_child = user.id
       WHERE parent_child.id_parent = ".$User["id"]."
    ");
    return ($User);
}
