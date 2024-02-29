<?php

// On ne peut pas effectuer une écriture de taille supérieure à 4k, donc
// on éclate tout. Le séparateur de commande devient tabulation verticale.
function hand_packet($data)
{
    $data = json_encode($data, JSON_UNESCAPED_UNICODE)."\v";
    $datatab = str_split($data, 2048);
    $datatab[] = "stop\v\n";
    $data = implode("\n", $datatab);
    $ship = __DIR__."/../.msg".uniqid();

    file_put_contents($ship, "");
    system("chmod 600 $ship");
    file_put_contents($ship, $data);
    return ($ship);
}

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
	file_put_contents($fifo, "");
	system("chmod 600 $fifo");
	file_put_contents($fifo, $key, FILE_APPEND);
    }

    // Mesure de sécurité suplémentaire de faible fiabilité
    // Et potentiellement de haut emmerdement en cas de requetes croisées
    $alb = $albx = "";
    if ($code)
    {
	$rnd = base64_encode(openssl_random_pseudo_bytes(64));
	$albx = __DIR__."/../api/albedo.php";
	file_put_contents($albx, "");
	system("chmod 600 $albx");
	file_put_contents($albx, $rnd, FILE_APPEND);
	$data["code"] = $rnd;
	// $alb = "; rm -f $albx";
    }

    $compress = "";
    // if (strlen($data) > 4096)
    // $compress = "-C";

    $ship = hand_packet($data);
    $cmd = "cat $ship | ssh $compress -o 'UserKnownHostsFile=/dev/null' -o 'StrictHostKeyChecking no' $acc@$url -i $fifo -p 4422 -tt infosphere_hand ; rm -f $ship ; rm -f $fifo "; // $alb ";
    $out = shell_exec($cmd);
    /*
       fprintf(STDERR, "$cmd\n");
       fprintf(STDERR, "BEFORE\n");
       fprintf(STDERR, $out);
       fprintf(STDERR, "AFTER\n");
     */
    $out = explode("\n", $out);
    @unlink($fifo); // Juste parceque...
    @unlink($ship); // Pareil...
    if ($albx != "")
	@unlink($albx);
    return (json_decode(end($out), true));
}

