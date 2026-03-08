<?php

function ping_hand()
{
    // On check l'état de la main
    $out = hand_request(["command" => "ping", "content" => "b64:".base64_encode("ping")]);
    if ($out && $out["result"] == "ok" && $out["content"] == "ping")
    {
	add_log(TRACE, "Infosphere hand runs.", 1, true);
	return (true);
    }
    add_log(REPORT, "Infosphere hand failure.", 1, true);
    return (false);
}

