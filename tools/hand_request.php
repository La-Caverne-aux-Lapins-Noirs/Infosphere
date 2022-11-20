<?php

function hand_request($data)
{
    global $Configuration;

    $acc = $Configuration->Properties["handaccount"];
    $url = $Configuration->Properties["handurl"];
    $key = base64_decode($Configuration->Properties["handkey"]);
    $key = unsecure_data($key, $acc.$url."hand_request");
    
    $fifo = __DIR__."/../.sshkey";
    if (!file_exists($fifo))
    {
	file_put_contents($fifo, $key);
	system("chmod 600 $fifo");
    }

    // Mesure de sécurité suplémentaire de faible fiabilité
    $rnd = base64_encode(openssl_random_pseudo_bytes(64));
    file_put_contents(__DIR__."/../api/albedo.php", $rnd);
    $data["code"] = $rnd;

    $data = json_encode($data, JSON_UNESCAPED_UNICODE);
    $cmd = "echo '$data\nstop' | ssh -o 'StrictHostKeyChecking no' $acc@$url -i $fifo -p 4422 -tt infosphere_hand";
    $out = shell_exec($cmd);
    $out = explode("\n", $out);
    unlink($fifo);
    return (json_decode(end($out), true));
}

