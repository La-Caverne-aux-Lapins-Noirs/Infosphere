<?php

function get_login_info($login, $password, $clear_password=true)
{
    global $Database;
    global $albedo;

    $login = $Database->real_escape_string($login);
    $rows = db_select_rows("user", ["full_profile"]);
    $rows = implode(", ", $rows);
    $user_query = $Database->query($x = "
      SELECT $rows
      FROM user
      WHERE codename = '$login'
    ");
    if (($usr = $user_query->fetch_assoc()) == NULL)
	return (new ErrorResponse("UnknownLogin"));
    if ($usr["deleted"] != NULL && $usr["id"] != 1) // On ne peut pas bannir Albedo
	return (new ErrorResponse("BannedAccount"));

    if ($clear_password)
    {
	$local_salt = base64_decode($usr["local_salt"]);
	if (($cookiehash = hash_method($local_salt.$password)) == false)
	    return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore
    }
    else
	$cookiehash = $password;

    $usr["children"] = db_select_all("
       user.codename as codename,
       parent_child.id_child as id
       FROM parent_child
       LEFT JOIN user ON parent_child.id_child = user.id
       WHERE parent_child.id_parent = ".$usr["id"]."
    ");

    $salt = base64_decode($usr["salt"]);
    if (($hash = hash_method($salt.$cookiehash)) == false)
	return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore

    if ($usr["password"] != $hash)
	return (new ErrorResponse("InvalidPassword"));
    unset($usr["password"]);
    unset($usr["salt"]);
    unset($usr["local_salt"]);
    if (!UNIT_TEST && !isset($albedo))
    {
	set_cookie("login", $login, time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
	set_cookie("password", $cookiehash, time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
	set_cookie("log_as", "", time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
    }

    get_user_public_data($usr);
    
    return (new ValueResponse($usr));
}
