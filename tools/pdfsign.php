<?php
require_once (__DIR__."/load_configuration.php");

function pdfsign_truthy($v)
{
    if (is_bool($v)) return ($v);
    if (is_int($v) || is_float($v)) return ($v != 0);
    return (in_array(strtolower(trim((string)$v)), ["1", "true", "yes", "on"], true));
}

function pdfsign_configuration_path($school_codename)
{
    global $Configuration;

    $school_codename = trim((string)$school_codename);
    if ($school_codename == "" || !preg_match('/^[A-Za-z0-9_-]+$/D', $school_codename))
        return (NULL);
    return ($Configuration->SchoolsDir($school_codename)."pdfsign.dab");
}

function pdfsign_configuration($school_codename)
{
    static $configurations = [];
    $school_codename = trim((string)$school_codename);
    if (isset($configurations[$school_codename]))
        return ($configurations[$school_codename]);
    $configuration = [
        "Enabled" => false,
        "Binary" => "pdfsign",
        "Store" => "/var/lib/pdfsign/nss",
        "StorePasswordFile" => "/var/lib/pdfsign/store-password",
        "Nick" => "",
        "Reason" => "Infosphere document workflow",
        "RequireTrusted" => false,
        "TimestampUrl" => "",
        "TimestampCaFile" => "",
        "TimestampCaPath" => "",
    ];
    $file = pdfsign_configuration_path($school_codename);
    if ($file === NULL)
    {
        $configuration["ConfigurationError"] = "MissingSchool";
        return ($configurations[$school_codename] = $configuration);
    }
    if (!is_file($file))
    {
        $configuration["ConfigurationError"] = "MissingSchoolConfiguration";
        return ($configurations[$school_codename] = $configuration);
    }
    $loaded = load_configuration($file, [], true);
    if ($loaded->is_error() || !is_array($loaded->value))
    {
        $configuration["ConfigurationError"] = "CannotLoadConfiguration";
        return ($configurations[$school_codename] = $configuration);
    }
    foreach ($configuration as $key => $default)
        if (array_key_exists($key, $loaded->value))
            $configuration[$key] = $loaded->value[$key];
    $configuration["Enabled"] = pdfsign_truthy($configuration["Enabled"]);
    $configuration["RequireTrusted"] = pdfsign_truthy($configuration["RequireTrusted"]);
    return ($configurations[$school_codename] = $configuration);
}

function pdfsign_configuration_error(array $c)
{
    if (!empty($c["ConfigurationError"])) return ((string)$c["ConfigurationError"]);
    if (!pdfsign_truthy($c["Enabled"] ?? false)) return ("Disabled");
    foreach (["Binary", "Store", "StorePasswordFile", "Nick"] as $field)
        if (trim((string)($c[$field] ?? "")) == "") return ("Missing".$field);
    if (!is_dir((string)$c["Store"])) return ("MissingStore");
    if (!is_file((string)$c["StorePasswordFile"])) return ("MissingStorePasswordFile");
    return (NULL);
}

function pdfsign_run(array $command)
{
    $desc = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
    $proc = @proc_open($command, $desc, $pipes, NULL, NULL, ["bypass_shell" => true]);
    if (!is_resource($proc))
        return (["ok" => false, "status" => -1, "stdout" => "", "stderr" => "Cannot start PdfSign", "json" => NULL]);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($proc);
    $json = json_decode(trim((string)$stdout), true);
    return (["ok" => $status === 0, "status" => $status, "stdout" => (string)$stdout,
        "stderr" => (string)$stderr, "json" => is_array($json) ? $json : NULL]);
}

function pdfsign_add_trust_arguments(array &$cmd, array $c)
{
    if (pdfsign_truthy($c["RequireTrusted"] ?? false)) $cmd[] = "--require-trusted";
}

function pdfsign_add_timestamp_ca_arguments(array &$cmd, array $c)
{
    if (trim((string)($c["TimestampCaFile"] ?? "")) != "")
        array_push($cmd, "--ca-file", (string)$c["TimestampCaFile"]);
    else if (trim((string)($c["TimestampCaPath"] ?? "")) != "")
        array_push($cmd, "--ca-path", (string)$c["TimestampCaPath"]);
}

function pdfsign_seal($school_codename, $input, $output, $timestamp_output = NULL)
{
    $c = pdfsign_configuration($school_codename);
    if (($error = pdfsign_configuration_error($c)) !== NULL)
        return (["ok" => false, "error" => "PdfSign".$error]);
    if (!is_file($input)) return (["ok" => false, "error" => "MissingInput"]);
    $cmd = [(string)$c["Binary"], "seal", "--input", (string)$input, "--output", (string)$output,
        "--store", (string)$c["Store"], "--store-password-file", (string)$c["StorePasswordFile"],
        "--nick", (string)$c["Nick"], "--reason", (string)$c["Reason"]];
    pdfsign_add_trust_arguments($cmd, $c);
    $tsa = trim((string)($c["TimestampUrl"] ?? ""));
    if ($tsa != "")
    {
        if ($timestamp_output === NULL || $timestamp_output == "") $timestamp_output = (string)$output.".tsr";
        array_push($cmd, "--timestamp-url", $tsa, "--timestamp-output", (string)$timestamp_output);
        pdfsign_add_timestamp_ca_arguments($cmd, $c);
    }
    $cmd[] = "--json";
    $r = pdfsign_run($cmd);
    if (!$r["ok"] || !is_array($r["json"]) || empty($r["json"]["ok"]))
        return (["ok" => false, "error" => "PdfSignSealFailed", "detail" => trim($r["stderr"]."\n".$r["stdout"]), "status" => $r["status"]]);
    if (!is_file($output)) return (["ok" => false, "error" => "PdfSignMissingOutput"]);
    $ih = hash_file("sha256", $input); $oh = hash_file("sha256", $output);
    if (($r["json"]["input_sha256"] ?? "") !== $ih || ($r["json"]["output_sha256"] ?? "") !== $oh)
        return (["ok" => false, "error" => "PdfSignHashMismatch"]);
    $th = NULL;
    if ($tsa != "")
    {
        if (!is_file($timestamp_output)) return (["ok" => false, "error" => "PdfSignMissingTimestamp"]);
        $th = hash_file("sha256", $timestamp_output);
        if (($r["json"]["timestamp_sha256"] ?? "") !== $th)
            return (["ok" => false, "error" => "PdfSignTimestampHashMismatch"]);
    }
    return (["ok" => true, "input_hash" => $ih, "output_hash" => $oh,
        "signatures" => (int)($r["json"]["signatures"] ?? 0),
        "certificates_trusted" => !empty($r["json"]["certificates_trusted"]),
        "timestamp_hash" => $th, "timestamp_file" => $tsa != "" ? (string)$timestamp_output : NULL]);
}

function pdfsign_verify($school_codename, $input, $timestamp = NULL)
{
    $c = pdfsign_configuration($school_codename);
    if (($error = pdfsign_configuration_error($c)) !== NULL)
        return (["ok" => false, "error" => "PdfSign".$error]);
    if (!is_file($input)) return (["ok" => false, "error" => "MissingInput"]);
    $cmd = [(string)$c["Binary"], "verify", "--input", (string)$input, "--strict", "--store", (string)$c["Store"]];
    pdfsign_add_trust_arguments($cmd, $c); $cmd[] = "--json";
    $r = pdfsign_run($cmd);
    if (!$r["ok"] || !is_array($r["json"]) || empty($r["json"]["ok"]))
        return (["ok" => false, "error" => "PdfSignVerifyFailed", "detail" => trim($r["stderr"]."\n".$r["stdout"])]);
    if ($timestamp !== NULL && $timestamp != "")
    {
        if (!is_file($timestamp)) return (["ok" => false, "error" => "PdfSignMissingTimestamp"]);
        $cmd = [(string)$c["Binary"], "verify-timestamp", "--input", (string)$input, "--timestamp", (string)$timestamp];
        pdfsign_add_timestamp_ca_arguments($cmd, $c); $cmd[] = "--json";
        $tr = pdfsign_run($cmd);
        if (!$tr["ok"] || !is_array($tr["json"]) || empty($tr["json"]["ok"]))
            return (["ok" => false, "error" => "PdfSignTimestampVerifyFailed", "detail" => trim($tr["stderr"]."\n".$tr["stdout"])]);
    }
    return (["ok" => true, "hash" => hash_file("sha256", $input),
        "signatures" => (int)($r["json"]["signatures"] ?? 0),
        "certificates_trusted" => !empty($r["json"]["certificates_trusted"])]);
}
