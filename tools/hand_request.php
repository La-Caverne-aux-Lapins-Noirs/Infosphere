<?php

require_once (__DIR__."/distrans_challenge.php");

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
        "/run/distrans",
        "/dev/shm/distrans",
        sys_get_temp_dir() . "/distrans",
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
    $rnd = NULL;
    if ($code)
    {
        $rnd = base64_encode(openssl_random_pseudo_bytes(64));
        $albx = distrans_write_challenge($rnd);
        if ($albx === false)
        {
            add_log(REPORT, "Distrans error: cannot write challenge file");
            return (false);
        }
        $data["code"] = $rnd;
    }

    $packet = hand_packet($data);
    if ($packet === "")
    {
        if ($albx != "")
            distrans_clear_challenge($albx, $rnd);
        add_log(REPORT, "Distrans error: invalid json packet");
        return (false);
    }

    $out = run_ssh_packet_with_inline_key($packet, $acc, $url, $port, $key);

    if ($albx != "")
        distrans_clear_challenge($albx, $rnd);

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

    if (isset($decoded["result"]) && $decoded["result"] != "ok")
    {
        $reason = "";
        foreach (["message", "msg", "error", "content"] as $field)
            if (isset($decoded[$field]) && trim((string)$decoded[$field]) != "")
            {
                $reason = trim((string)$decoded[$field]);
                break ;
            }
        if ($reason == "")
            $reason = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        add_log(REPORT, "Distrans error: " . ($data["command"] ?? "unknown") . " returned " . $decoded["result"] . ": " . $reason);
    }

    // add_log(REPORT, "Distrans success: ".$out["stdout"]);
    return ($decoded);
}

