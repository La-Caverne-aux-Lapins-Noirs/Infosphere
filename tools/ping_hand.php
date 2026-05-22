<?php

require_once (__DIR__."/distrans_status.php");

function ping_hand()
{
    // On check l'état de Distrans
    $out = hand_request([
        "command" => "ping"
    ]);
    if (is_array($out) &&
        ($out["status"] ?? null) === "ok" &&
        ($out["result"] ?? null) === "ok" &&
        ($out["pong"] ?? false) === true)
    {
        distrans_touch_running_log(1);
        return (true);
    }
    add_log(REPORT, "Distrans failure.", 1, true);
    return (false);
}

