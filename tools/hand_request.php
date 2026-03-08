<?php

// On ne peut pas effectuer une écriture de taille supérieure à 4k, donc
// on éclate tout. Le séparateur de commande devient tabulation verticale.
function hand_packet($data)
{
    $data["date"] = date("Y-m-d\\TH:i:sP");

    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false)
        return "";

    $json .= "\v";

    $chunks = str_split($json, 2048);
    $chunks[] = "stop\v\n";

    return implode("\n", $chunks);
}


function run_ssh_packet_with_inline_key(
    string $packet,
    string $account,
    string $host,
    int $port,
    string $private_key
): array {
    $runtime_dirs = [
        "/run/infosphere_hand",
        "/dev/shm/infosphere_hand",
        sys_get_temp_dir() . "/infosphere_hand",
    ];

    $tmp_dir = null;
    foreach ($runtime_dirs as $dir)
    {
        if (!is_dir($dir))
            @mkdir($dir, 0700, true);
        @chmod($dir, 0700);

        if (is_dir($dir) && is_writable($dir))
        {
            $tmp_dir = $dir;
            break;
        }
    }

    if ($tmp_dir === null)
    {
        return [
            "stdout" => "",
            "stderr" => "No writable runtime directory available",
            "exit_code" => 255,
        ];
    }

    $key_file = tempnam($tmp_dir, "sshkey_");
    if ($key_file === false)
    {
        return [
            "stdout" => "",
            "stderr" => "Unable to create temporary key file",
            "exit_code" => 255,
        ];
    }

    @chmod($key_file, 0600);

    if ($private_key === "" || substr($private_key, -1) !== "\n")
        $private_key .= "\n";

    if (file_put_contents($key_file, $private_key) === false)
    {
        @unlink($key_file);
        return [
            "stdout" => "",
            "stderr" => "Unable to write temporary key file",
            "exit_code" => 255,
        ];
    }

    $cmd = [
        "ssh",
        "-T",
        "-i", $key_file,
        "-p", (string)$port,
        "-o", "RequestTTY=no",
        "-o", "BatchMode=yes",
        "-o", "IdentitiesOnly=yes",
        "-o", "UserKnownHostsFile=/dev/null",
        "-o", "StrictHostKeyChecking=no",
        "-o", "LogLevel=ERROR",
        $account . "@" . $host,
    ];

    $descriptors = [
        0 => ["pipe", "r"], // child stdin  : child reads, parent writes
        1 => ["pipe", "w"], // child stdout : child writes, parent reads
        2 => ["pipe", "w"], // child stderr : child writes, parent reads
    ];

    $pipes = [];
    $proc = proc_open($cmd, $descriptors, $pipes);

    if (!is_resource($proc))
    {
        @unlink($key_file);
        return [
            "stdout" => "",
            "stderr" => "Unable to start ssh",
            "exit_code" => 255,
        ];
    }

    fwrite($pipes[0], $packet);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($proc);

    @unlink($key_file);

    return [
        "stdout" => $stdout === false ? "" : $stdout,
        "stderr" => $stderr === false ? "" : $stderr,
        "exit_code" => $exit_code,
    ];
}

function hand_request(array $data, bool $code = true)
{
    global $Configuration;

    if (($acc = @$Configuration->Properties["handaccount"]) == NULL)
        return ["result" => "ko", "message" => "HandAccountMissing"];

    $url  = $Configuration->Properties["handurl"];
    $key  = $Configuration->Properties["handkey"];
    $port = $Configuration->Properties["handport"];

    // Mesure de sécurité supplémentaire de faible fiabilité
    // et potentiellement de haut emmerdement en cas de requêtes croisées.
    // Je la laisse telle quelle pour l’instant puisque Distrans va lire via URL.
    $albx = "";
    if ($code)
    {
        $rnd = base64_encode(openssl_random_pseudo_bytes(64));
        $albx = __DIR__ . "/../api/albedo.php";

        file_put_contents($albx, "");
        chmod($albx, 0600);
        file_put_contents($albx, $rnd, FILE_APPEND);

        $data["code"] = $rnd;
    }

    $packet = hand_packet($data);
    if ($packet === "")
    {
        if ($albx != "")
            @unlink($albx);
        add_log(REPORT, "Distrans error: invalid json packet");
        return (false);
    }

    add_log(REPORT, "handkey first line: " . strtok($key, "\n"));
    add_log(REPORT, "handkey len: " . strlen($key));
    add_log(REPORT, "handkey has literal \\n: " . (strpos($key, "\\n") !== false ? "yes" : "no"));
    add_log(REPORT, "handkey has CR: " . (strpos($key, "\r") !== false ? "yes" : "no"));    
    $out = run_ssh_packet_with_inline_key($packet, $acc, $url, $port, $key);

    if ($albx != "")
        @unlink($albx);

    if ($out["stdout"] === null)
    {
        add_log(REPORT, "Distrans error: NULL was returned");
        return (false);
    }

    if ($out["exit_code"] !== 0)
    {
        add_log(REPORT, "Distrans error: " . $data["command"] . " " . $out["stderr"]);
        return (false);
    }

    $decoded = json_decode($out["stdout"], true);
    if (!is_array($decoded))
    {
        add_log(REPORT, "Distrans error: invalid JSON response: " . $out["stdout"]);
        return (false);
    }

    return ($decoded);
}

