<?php

function fetch_prospect($id = -1)
{
    global $Database;

    if (($id = resolve_codenamef("user", $id))->is_error())
	return ($id);
    $id = $id->value;
    if (strlen($add_fields = implode(",", $add_fields)))
	$add_fields = ", ".$add_fields;
    if (!is_array($id))
	$id = [$id];
    
    $u = db_select_one("
              user.id as id,
              user.codename as codename,
              user.first_name as first_name,
              user.family_name as family_name,
              user.mail as mail,
              user.registration_date as registration_date,
	      user.phone as phone,
	      user.postal_code as postal_code,
	      user.current_class as current_class,
	      user.target_class as target_class,
	      user.target_entry as target_entry
        FROM  user
        WHERE user.id IN (".implode(",", $id).") && password = ''
    ");
    if ($u == NULL)
	return (new ErrorResponse("UserNotFound"));
    get_user_promotions($u);
    get_user_school($u);
    return (new ValueResponse($u));
}
