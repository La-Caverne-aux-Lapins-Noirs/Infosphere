<?php

$val = generate_password(128);
$out = hand_request($packet = [
    "command" => "ping",
    "content" => "b64:".base64_encode($val)
]);
AddDebugLogR($packet);
AddDebugLogR($out);
if ($out === false || $out === NULL)
    echo "Cannot reach the Hand";
else if (!isset($out["content"]) || $out["content"] != $val)
{
    echo "Bad ping!<br />";
    print_r($out);
}
else
{
    add_log(TRACE, "Infosphere hand runs.", 1, true);
    echo "Ping ok!";
}

