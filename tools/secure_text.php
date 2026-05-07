<?php

function secure_text($msg, $pass = "")
{
    global $Database;
    global $Secured;
    
    $msg = strip_tags($msg);
    $msg = str_replace("\n", "<br />", trim($msg));
    if ($pass === "")
	return ($Database->real_escape_string($msg));
    if ($pass === NULL)
	$pass = $Secured;
    $msg = openssl_encrypt(
	$msg, "blowfish", $pass, 0, "azertyui01234567"
    );
    $msg = $Database->real_escape_string($msg);
    return ($msg);
}
