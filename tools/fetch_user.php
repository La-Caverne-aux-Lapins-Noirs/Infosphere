<?php

function fetch_user($id, $add_fields = [])
{
    global $Database;

    if (($id = resolve_codename("user", $id))->is_error())
	return ($id);
    $id = $id->value;
    die();
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
              user.address_name as address_name,
              user.street_name as street_name,
              user.postal_code as postal_code,
              user.city as city,
              user.country as country,
              user.admin_note as admin_note,
              user.authority as authority,
              user.visibility as visibility,
              user.misc_configuration as misc_configuration
              $add_fields
        FROM  user
        WHERE user.id IN (".implode(",", $id).") && user.authority != -1
    ");
    if ($u == NULL)
	return (new ErrorResponse("UserNotFound"));
    $u["misc_configuration"] = json_decode($u["misc_configuration"], true);
    get_user_promotions($u);
    return (new ValueResponse($u));
}
