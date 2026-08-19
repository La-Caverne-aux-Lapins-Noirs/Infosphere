<?php

function get_user_children(&$usr, $by_name = false)
{
    if (isset($usr["children"]))
	return ($usr["children"]);
    $usr["children"] = array_values(array_filter(db_select_all("
       user.codename as codename,
       parent_child.id as id_parent_child,
       parent_child.id_child as id,
       parent_child.id_child as id_user,
       parent_child.relation as relation
       FROM parent_child
       LEFT JOIN user ON parent_child.id_child = user.id
       WHERE parent_child.id_parent = ".$usr["id"]."
       ", $by_name ? "codename" : ""), function($child) {
        return (user_relation_has($child["relation"] ?? "", "log_as"));
    }));
    return ($usr["children"]);
}
