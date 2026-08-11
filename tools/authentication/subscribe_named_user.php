<?php

function build_named_user_login($first_name, $last_name)
{
    $first_name = convert_to_codename($first_name);
    $last_name = convert_to_codename($last_name);
    return ($first_name.".".$last_name);
}

function subscribe_named_user($first_name, $last_name, $mail, $phone, $cookie = true, $fake = false, $profile_status = NULL)
{
    $login = build_named_user_login($first_name, $last_name);
    if (($ret = subscribe($login, $mail, NULL, $cookie, $fake, $profile_status))->is_error())
        return ($ret);
    $user = $ret->value;
    return (set_user_data($user["id"], ["phone" => $phone]));
}
