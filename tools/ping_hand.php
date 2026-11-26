<?php

require_once (__DIR__."/distrans_status.php");

function ping_hand(&$health_probe = NULL)
{
    // On check l'état de Distrans
    $started = microtime(true);
    $out = hand_request([
        "command" => "ping"
    ], true, 10);
    $health_probe = ["online" => false, "response_ms" => NULL];
    if (is_array($out) &&
        ($out["status"] ?? null) === "ok" &&
        ($out["result"] ?? null) === "ok" &&
        ($out["pong"] ?? false) === true)
    {
        $health_probe = ["online" => true,
            "response_ms" => round(1000 * (microtime(true) - $started), 2)];
        distrans_touch_running_log(1);
        return (true);
    }
    add_log(REPORT, "Distrans failure.", 1, true);
    return (false);
}

