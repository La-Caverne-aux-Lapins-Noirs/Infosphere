<?php

function hand_request($data, $code = true)
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
    if ($code)
    {
	$rnd = base64_encode(openssl_random_pseudo_bytes(64));
	file_put_contents(__DIR__."/../api/albedo.php", $rnd);
	$data["code"] = $rnd;
    }

    $data = json_encode($data, JSON_UNESCAPED_UNICODE)."\v";
    $compress = "";
    if (strlen($data) > 4096)
	$compress = "-C";
    // On ne peut pas effectuer une écriture de taille supérieure à 4k, donc
    // on éclate tout. Le séparateur de commande devient tabulation verticale.
    $datatab = str_split($data, 2048);
    $datatab[] = "stop\v\n";
    $data = implode("\n", $datatab);
    $ship = __DIR__."/../.msg".uniqid();
    file_put_contents($ship, $data);
    $cmd = "cat $ship | ssh $compress -o 'StrictHostKeyChecking no' $acc@$url -i $fifo -p 4422 -tt infosphere_hand ";
    $out = shell_exec($cmd);
    $out = explode("\n", $out);
    @unlink($fifo);
    @unlink($ship);
    return (json_decode(end($out), true));
}

