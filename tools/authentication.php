<?php

@define("INSTALLATION", 0);

function hash_method($str)
{
    // return (password_hash($str, PASSWORD_BCRYPT));
    return (hash("whirlpool", $str, false));
}

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
    }

    get_user_public_data($usr);
    
    return (new ValueResponse($usr));
}

function get_user_public_data(&$usr)
{
    get_user_promotions($usr);
    get_user_children($usr);
    get_user_laboratories($usr);
    $usr["todo"] = db_select_all("id, content FROM user_todolist WHERE id_user = {$usr["id"]} ORDER BY id ASC");
}

function generate_password($len = 12)
{
    // Pas de O majuscule (pour ne pas confondre avec zéro)
    // Pas de \, ni `, ni ", ni ', ni ~ ou ^.
    // Pas de $ ou * pour limiter les risques avec les passages dans le shell.
    $randpool = "azertyuiopqsdfghjklmwxcvbnAZERTYUIPQSDFGHJKLMWXCVBN1234567890&#{([-|_@)]=}+%,?;.:/!";
    $letter = false;
    $number = false;
    $symbol = false;

    $rnd = "";
    for ($i = 0; $i < $len; ++$i)
    {
	$r = rand(0, strlen($randpool) - 1);
	$c = substr($randpool, $r, 1);
	if (ctype_alpha($c))
	    $letter = true;
	else if (ctype_digit($c))
	    $number = true;
	else
	    $symbol = true;
	$randpool = str_replace($c, "", $randpool);
	$rnd = $rnd.$c;
    }
    if ($letter == false)
	$rnd = $rnd.substr("azertyuiopqsdfghjklmwxcvbnAZERYTUIPQSDFGHJKLMWXCVBN", rand(0, 25 * 2 - 1), 1);
    if ($number == false)
	$rnd = $rnd.substr("0123456789", rand(0, 9), 1);
    if ($symbol == false)
	$rnd = $rnd.substr("&#{([-|_@)]=}+$%*,?;.:/!", rand(0, 25), 1);
    return ($rnd);
}

function subscribe($login, $mail, $password = NULL, $cookie = true, $fake = false)
{
    global $Database;

    if ($password == NULL && $fake == false)
	$password = generate_password();
    if (@strlen($login) < 2)
	return (new ErrorResponse("BadLogin", $login));
    if ($fake == false)
    {
	if (@strlen($password) < 8 || !preg_match("#[0-9]+#", $password) || !preg_match("#[a-zA-Z]+#", $password))
	    return (new ErrorResponse("BadPassword"));
    }
    if (filter_var($mail, FILTER_VALIDATE_EMAIL) == false)
	return (new ErrorResponse("BadMail", $mail));

    if (!INSTALLATION)
	add_log(TRACE, "User $login is trying to subscribe", 0);
    $login = $Database->real_escape_string($login);
    $mail = $Database->real_escape_string($mail);
    $user_query = $Database->query("
      SELECT codename, mail
      FROM user
      WHERE codename = '$login' OR mail = '$mail'
    ");    
    if (($usr = $user_query->fetch_assoc()) != NULL)
    {
	if ($usr["codename"] == $login && $usr["mail"] == $mail)
	    return (new ErrorResponse("LoginAndMailUsed", $login." ".$mail));
	if ($usr["codename"] == $login)
	    return (new ErrorResponse("LoginUsed", $login));
	return (new ErrorResponse("MailUsed", $mail));
    }

    if ($fake == false)
    {
	$local_salt = openssl_random_pseudo_bytes(256);
	$salt = openssl_random_pseudo_bytes(256);

	if (($cookiehash = hash_method($local_salt.$password)) == false)
	    return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore
	if (($hash = hash_method($salt.$cookiehash)) == false)
	    return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore

	$salt = base64_encode($salt);
	$local_salt = base64_encode($local_salt);
    }
    else
    {
	$hash = "";
	$salt = "";
	$local_salt = "";
    }
    

    if (($Database->query("
      INSERT INTO user (codename, password, registration_date, salt, local_salt, mail)
      VALUES ('$login', '$hash', NOW(), '$salt', '$local_salt', '$mail')
    ")) == false)
    {
	if (!INSTALLATION)
	    add_log(TRACE, "Insertion failed.", 0); // @codeCoverageIgnore
	return (new ErrorResponse("CannotRegister")); // @codeCoverageIgnore
    }

    if ($fake == false)
    {
	if (count($fl = explode(".", $login)) == 2)
	{
	    $first = $fl[0];
	    $last = $fl[1];
	}
	else
	{
	    $first = "First";
	    $last = "Last";
	}
    
	$out = hand_request([
	    "command" => "newuser",
	    "user" => $login,
	    "first_name" => $first,
	    "last_name" => $last,
	    "mail" => $mail,
	    "password" => $password,
	    "school" => "efrits"
	]);
	if ($out != NULL)
	    $Database->query("UPDATE user SET uid = ".$out["uid"]." WHERE codename = '$login'");
    }
    
    if (!UNIT_TEST && $cookie && $fake == false)
    {
	set_cookie("login", $login, time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
	set_cookie("password", $cookiehash, time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
    }

    $user_query = $Database->query("SELECT * FROM user WHERE codename = '$login'");
    $usr = $user_query->fetch_assoc();
    unset($usr["salt"]);
    unset($usr["local_salt"]);
    unset($usr["password"]);
    if (!INSTALLATION && $fake == false)
    {
	send_subscribe_mail($usr["id"], $login, $mail, $password);
	add_log(CRITICAL_USER_DATA, "User ".$usr["codename"]." added", $usr["id"]);
    }
    return (new ValueResponse($usr));
}

function try_subscribe($login, $mail, $password, $repassword, $fake = false)
{
    if ($password != $repassword && $fake == false)
	return (new ErrorResponse("PasswordDoesNotMatch"));
    return (subscribe($login, $mail, $password, !$fake, $fake));
}

function regenerate_password($usr, $newpass)
{
    global $Database;
    global $User;

    $hash_query = $Database->query("
      SELECT salt, local_salt
      FROM user
      WHERE id = '".$Database->real_escape_string($usr["id"])."'
      ");
    if (($salts = $hash_query->fetch_assoc()) == NULL)
	return (new ErrorResponse("UnknownId")); // @codeCoverageIgnore
    $salts["local_salt"] = base64_decode($salts["local_salt"]);
    if (!($cookie_pass = hash_method($salts["local_salt"].$newpass)))
	return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore
    if (!UNIT_TEST && $usr["id"] == $User["id"]) // On ne se deconnecte pas si on change son propre mot de passe...
	set_cookie("password", $cookie_pass, time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
    $salts["salt"] = base64_decode($salts["salt"]);
    if (!($final_pass = hash_method($salts["salt"].$cookie_pass)))
	return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore
    return (new ValueResponse($final_pass));
}

// Cette fonction ne peut éditer aucun aspect critique lié a l'authentification ou au contact.
function set_user_data($id, $vals, $misc_fields = [], $adduser = false, $fake_account = false)
{
    global $Database;
    global $User;

    if (isset($vals["id"]))
	$id = $vals["id"];
    if ($id == -1 || ($id = resolve_codename("user", $id))->is_error())
    {
	if ($id != -1 && ($id->label != "CodeNameAlreadyUsed" || $adduser == false))
	    return ($id);
	if (($ret = try_subscribe($vals["codename"], $vals["mail"], NULL, NULL, $fake_account))->is_error())
	{
	    if ($ret->label != "LoginAndMailUsed"
		&& $ret->label != "MailUsed"
		&& $ret->label != "LoginUsed")
	    return ($ret);
	}
	$id = resolve_codename("user", $vals["codename"]);
    }

    if (($id = $id->value) == 1) // && !UNIT_TEST && 0)
	return (new ErrorResponse("CannotEditAdministrator")); // @codeCoverageIgnore
    if (!isset($vals))
	return (new ErrorResponse("MissingParameter"));

    /*
    $misc = $User["misc_configuration"];
    foreach ($misc_fields as $i => $v)
    {
	if ($v["type"] == "checkbox")
	    $misc["profile"][$v["label"]] = (@$vals[$v["label"]] == "on");
	else
	    $misc["profile"][$v["label"]] = $vals[$v["label"]];
	unset($vals[$v["label"]]);
    }
    $vals["misc_configuration"] = json_encode($misc, JSON_UNESCAPED_SLASHES);
    */

    /// Il faut resoudre id
    $constfields = ["id", "password", "salt", "local_salt", "codename", "registration_date"];
    $forge = unroll($vals, UPDATE, $constfields);
    $req = "UPDATE user SET $forge WHERE id = $id";
    if ($Database->query($req) == false)
	return (new ErrorResponse("CannotEdit")); // @codeCoverageIgnore

    if ($User && $id == $User["id"])
    {
	foreach ($vals as $i => $v)
	{
	    if (in_array($i, $constfields))
		continue ; // @codeCoverageIgnore
	    $User[$i] = $v;
	}
    }
    return (resolve_codename("user", $id, "codename", true));
}

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
	$nv = $msg->value;
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
	send_password_change_mail($new_user, $new["password"]);
    }
    return (new ValueResponse($new_user));
}

