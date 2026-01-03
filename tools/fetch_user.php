<?php

function fetch_user($id, $add_fields = [])
{
    global $Database;

    if (($id = resolve_codenamef("user", $id))->is_error())
	return ($id);
    $id = $id->value;
    if (strlen($add_fields = implode(",", $add_fields)))
	$add_fields = ", ".$add_fields;
    if (!is_array($id))
	$id = [$id];

    if (is_director_for_student($id, /* false */ true) || is_me($id))
    {
	$add_fields =
	    ",".
	    "user.ine as ine,\n".
	    "user.address_name as address_name,\n".
	    "user.street_name as street_name,\n".
	    "user.postal_code as postal_code,\n".
	    "user.city as city,\n".
            "user.country as country\n"
	    ;
    }
    
    $u = db_select_one("
              user.id as id,
              user.codename as codename,
              user.nickname as nickname,
              user.first_name as first_name,
              user.family_name as family_name,
	      user.cache as cache,
              user.mail as mail,
              user.money as money,
              user.registration_date as registration_date,
              user.birth_date as birth_date,
              user.authority as authority,
	      user.phone as phone,
              user.visibility as visibility,
	      user.source as source,
	      user.step as step,
	      user.last_contact as last_contact,
	      user.last_try as last_try
              $add_fields
        FROM  user
        WHERE user.id IN (".implode(",", $id).") && user.authority != -1
    ");
    if ($u == NULL)
	return (new ErrorResponse("UserNotFound"));
    get_user_promotions($u);
    get_user_school($u);
    return (new ValueResponse($u));
}
