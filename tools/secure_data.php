<?php

function secure_data($data, $pass = NULL)
{
    global $Secured;
    
    if ($pass == NULL)
	$pass = $Secured;
    $lhash = longhash($pass);
    $msg = openssl_encrypt(
	$data, "blowfish", $pass, 0, "azertyui"
    );
    return (base64_encode($msg));
}

