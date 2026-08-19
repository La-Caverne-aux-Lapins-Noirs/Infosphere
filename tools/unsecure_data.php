<?php

function unsecure_data($data, $pass = NULL)
{
    global $Secured;

    if ($pass == NULL)
        $pass = $Secured;
    if (!is_string($data) || !str_starts_with($data, "IS2:"))
        return (false);

    $packed = base64_decode(substr($data, 4), true);
    if ($packed === false || strlen($packed) < 28)
        return (false);

    $iv = substr($packed, 0, 12);
    $tag = substr($packed, 12, 16);
    $ciphertext = substr($packed, 28);
    $key = hash("sha256", (string)$pass, true);
    return (openssl_decrypt(
        $ciphertext,
        "aes-256-gcm",
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    ));
}
