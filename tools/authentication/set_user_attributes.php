<?php

// Cette fonction peut tout faire
function set_user_attributes($user, $new)
{
    global $Database;

    if ($user == NULL)
	return (new ErrorResponse("InvalidParameter"));
    if (!is_array($user))
    {
	if (($user = resolve_codename("user", $user, "codename", true))->is_error())
	    return ($user);
	$user = $user->value;
    }
    $forgery = [];
    $clean_user = [];
    $password_regenerated = false;
    foreach ($user as $k => $v)
    {
	if (in_array($k, ["id", "salt", "local_salt", "mail"]))
	    continue ;
	if (isset($new[$k]))
	{
	    $nk = $Database->real_escape_string($k);
	    $nv = $Database->real_escape_string($new[$k]);
	    $forgery[] = "$nk = '$nv'";
	}
    }
    if (isset($new["mail"]))
    {
	if (filter_var($new["mail"], FILTER_VALIDATE_EMAIL) == false)
	    return (new ErrorResponse("BadMail"));
	$nk = $Database->real_escape_string("mail");
	$nv = $Database->real_escape_string($new["mail"]);
	$forgery[] = "$nk = '$nv'";
    }
    if (isset($new["password"]))
    {
	if (($msg = regenerate_password($user, $new["password"]))->is_error())
	    return ($msg); // @codeCoverageIgnore
	$npassword = $nv = $msg->value;
	$password_regenerated = true;
	$forgery[] = "password = '$nv'";
    }

    if (count($forgery) == 0)
	return (new ValueResponse($user));
    $id = $Database->real_escape_string($user["id"]);
    $forgery = implode(",", $forgery);
    if ($Database->query("
      UPDATE user
      SET $forgery
      WHERE id = '$id'
    ") == false)
      return (new ErrorResponse("CannotUpdate")); // @codeCoverageIgnore
    $user_query = $Database->query("
      SELECT *
      FROM user
      WHERE id = '$id'
    ");
    if (($new_user = $user_query->fetch_assoc()) == NULL)
	return (new ErrorResponse("UnknownId")); // @codeCoverageIgnore
    unset($new_user["password"]);
    unset($new_user["salt"]);
    unset($new_user["local_salt"]);
    add_log(UNCRITICAL_USER_DATA, "User ".$user["id"]." changed data.", $user["id"]);
    if (isset($new["mail"]) && $user["mail"] != $new["mail"])
    {
	add_log(CRITICAL_USER_DATA, "User ".$user["mail"]." switched to ".$new["mail"].".");
	send_mail_change_mail($user, $new_user);
    }
    if ($password_regenerated)
    {
	add_log(CRITICAL_USER_DATA, "User ".$user["id"]." changed password.");
	$out = hand_request([
	    "command" => "newpassword",
	    "user" => $user["codename"],
	    "id" => $user["id"],
	    "password" => $npassword
	]);
	send_password_change_mail($new_user, $new["password"]);
    }
    return (new ValueResponse($new_user));
}

