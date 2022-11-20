<?php

$val = generate_password(128);
$out = hand_request([
    "command" => "ping",
    "content" => "b64:".base64_encode($val)
]);
if ($out === false)
    echo "Cannot reach the Hand";
else if (!isset($out["content"]) || $out["content"] != $val)
    echo "Bad ping!";
else
    echo "Ping ok!";

