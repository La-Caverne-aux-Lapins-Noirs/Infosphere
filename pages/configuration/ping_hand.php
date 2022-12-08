<?php

$val = generate_password(128);
$out = hand_request([
    "command" => "ping",
    "content" => "b64:".base64_encode($val)
]);
if ($out === false || $out === NULL)
    echo "Cannot reach the Hand";
else if (!isset($out["content"]) || $out["content"] != $val)
{
    echo "Bad ping!<br />";
    print_r($out);
}
else
    echo "Ping ok!";

