<?php

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
