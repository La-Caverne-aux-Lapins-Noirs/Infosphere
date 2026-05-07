<?php

function unsecure_data($data, $pass = NULL)
{
    global $Secured;
    
    if ($pass == NULL)
	$pass = $Secured;
    $lhash = longhash($pass);
    $data = base64_decode($data);
    $msg = openssl_decrypt(
	$data, "blowfish", $pass, 0, "azertyui"
    );
    return ($msg);
}
