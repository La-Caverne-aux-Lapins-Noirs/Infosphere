<?php

function secure_data($data, $pass = NULL)
{
    global $Secured;

    if ($pass == NULL)
        $pass = $Secured;

    $key = hash("sha256", (string)$pass, true);
    $iv = random_bytes(12);
    $tag = "";
    $ciphertext = openssl_encrypt(
        (string)$data,
        "aes-256-gcm",
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        "",
        16
    );
    if ($ciphertext === false)
        return (false);
    return ("IS2:".base64_encode($iv.$tag.$ciphertext));
}
