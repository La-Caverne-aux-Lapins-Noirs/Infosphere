<?php

function fetch_user($id, $add_fields = [])
{
    global $Database;

    if (($id = resolve_codename("user", $id))->is_error())
	return ($id);
    $id = $id->value;
    if (strlen($add_fields = implode(",", $add_fields)))
	$add_fields = ", ".$add_fields;
    if (!is_array($id))
	$id = [$id]; 
    $u = db_select_one("
              user.id as id,
              user.codename as codename,
              user.nickname as nickname,
              user.first_name as first_name,
              user.family_name as family_name,
              user.mail as mail,
              user.money as money,
              user.registration_date as registration_date,
              user.birth_date as birth_date,
	      user.phone as phone,
              user.address_name as address_name,
              user.street_name as street_name,
              user.postal_code as postal_code,
              user.city as city,
              user.country as country,
              user.authority as authority,
              user.visibility as visibility
              $add_fields
        FROM  user
        WHERE user.id IN (".implode(",", $id).") && user.authority != -1
    ");
    if ($u == NULL)
	return (new ErrorResponse("UserNotFound"));
    get_user_promotions($u);
    return (new ValueResponse($u));
}
