<?php

function subscribe_student($first_name, $last_name, $mail, $phone)
{
    $first_name = convert_to_codename($first_name);
    $last_name = convert_to_codename($last_name);
    $login = "$first_name.$last_name";
    if (($ret = subscribe($login, $mail, NULL))->is_error())
	return ($ret);
    $ret = $ret->value;
    return (set_user_data($ret["id"], ["phone" => $phone]));
}

