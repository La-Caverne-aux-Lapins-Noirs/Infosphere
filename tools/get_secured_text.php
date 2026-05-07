<?php

function get_secured_text($msg, $pass = "")
{
    global $Secured;

    if ($pass === "")
	return ($msg);
    if ($pass === NULL)
	$pass = $Secured;
    $msg = openssl_decrypt(
	$msg,
	openssl_get_cipher_methods()[0],
	$pass,
	0, "azertyui01234567"
    );
    return ($msg);
}
