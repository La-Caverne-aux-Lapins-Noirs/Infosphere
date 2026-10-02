<?php

// Persoc's firewall retrieves its deadlist from Distrans. Infosphere keeps
// the desired ordered list in the generic configuration table and publishes
// it with set_deadlist; Persoc later consumes the same state with get_deadlist.

function persoc_deadlist_configuration_codename()
{
    return ("persoc_deadlist");
}

function persoc_deadlist_configuration_row()
{
    global $Database;

    $codename = $Database->real_escape_string(persoc_deadlist_configuration_codename());
    return (db_select_one("\n        id, value\n        FROM configuration\n        WHERE codename = '$codename'\n        ORDER BY id ASC\n    "));
}

function persoc_deadlist_configuration_capacity()
{
    static $capacity = NULL;
    global $Database;

    if ($capacity !== NULL)
        return ($capacity);

    // The current schema reserves 8192 characters. Detect the real column
    // size nevertheless, so an older installation fails cleanly instead of
    // letting MariaDB truncate the deadlist.
    $capacity = 8192;
    $query = $Database->query("SHOW COLUMNS FROM configuration LIKE 'value'");
    if ($query !== NULL && ($row = $query->fetch_assoc()) != NULL)
    {
        $type = strtolower((string)($row["Type"] ?? ""));
        if (preg_match('/^varchar\\(([0-9]+)\\)/D', $type, $match) === 1)
            $capacity = max(1, (int)$match[1]);
        else if (preg_match('/^(tinytext|text|mediumtext|longtext)/D', $type) === 1)
            $capacity = PHP_INT_MAX;
    }
    return ($capacity);
}

function persoc_deadlist_normalize_entry($entry)
{
    $entry = trim((string)$entry);
    if ($entry == "")
        return (NULL);

    // Accept the CSV produced by Distrans/Persoc as convenient paste input.
    if (strpos($entry, ",") !== false)
    {
        $csv = str_getcsv($entry, ",", '"', "");
        $entry = trim((string)($csv[0] ?? ""));
    }
    $entry = rtrim($entry, ".");
    if ($entry == "")
        return (NULL);

    if (filter_var($entry, FILTER_VALIDATE_IP) !== false)
    {
        $packed = @inet_pton($entry);
        $normalized = $packed !== false ? @inet_ntop($packed) : false;
        return ($normalized !== false ? strtolower($normalized) : strtolower($entry));
    }

    if (strlen($entry) > 253 || strpos($entry, "://") !== false || strpos($entry, "/") !== false)
        return (false);

    $labels = explode(".", strtolower($entry));
    foreach ($labels as $label)
    {
        if ($label == "" || strlen($label) > 63
            || preg_match('/^[a-z0-9](?:[a-z0-9_-]{0,61}[a-z0-9])?$/D', $label) !== 1)
            return (false);
    }
    return (implode(".", $labels));
}

function persoc_deadlist_parse_text($text)
{
    $entries = [];
    $invalid = [];
    $seen = [];
    $lines = preg_split('/\r\n|\r|\n/', (string)$text);
    if (!is_array($lines))
        $lines = [];

    foreach ($lines as $line_number => $line)
    {
        $original = trim((string)$line);
        if ($original == "" || substr($original, 0, 1) == "#")
            continue ;
        $entry = persoc_deadlist_normalize_entry($original);
        if ($entry === false || $entry === NULL)
        {
            $invalid[] = [
                "line" => $line_number + 1,
                "value" => $original,
            ];
            continue ;
        }
        if (isset($seen[$entry]))
            continue ;
        $seen[$entry] = true;
        $entries[] = $entry;
    }

    return ([
        "entries" => $entries,
        "invalid" => $invalid,
    ]);
}

function persoc_deadlist_storage_text(array $entries)
{
    return (implode("\n", array_values($entries)));
}

function persoc_deadlist_storage_initialized()
{
    $row = persoc_deadlist_configuration_row();
    return ($row != NULL && $row["value"] !== NULL);
}

function persoc_deadlist_local_text()
{
    $row = persoc_deadlist_configuration_row();
    if ($row == NULL || $row["value"] === NULL)
        return ("");
    return ((string)$row["value"]);
}

function persoc_deadlist_local_entries()
{
    $parsed = persoc_deadlist_parse_text(persoc_deadlist_local_text());
    return ($parsed["entries"]);
}

function persoc_deadlist_save_entries(array $entries, &$error = NULL)
{
    global $Database;
    global $Configuration;

    $error = NULL;
    $text = persoc_deadlist_storage_text($entries);
    $capacity = persoc_deadlist_configuration_capacity();
    if ($capacity != PHP_INT_MAX && strlen($text) > $capacity)
    {
        $error = "La blacklist occupe ".strlen($text)." caractères alors que configuration.value en accepte $capacity";
        return (false);
    }

    $row = persoc_deadlist_configuration_row();
    $value = $Database->real_escape_string($text);
    if ($row == NULL)
    {
        $codename = $Database->real_escape_string(persoc_deadlist_configuration_codename());
        $ok = $Database->query("\n            INSERT INTO configuration (codename, value)\n            VALUES ('$codename', '$value')\n        ") !== NULL;
    }
    else
    {
        $id = (int)$row["id"];
        $ok = $Database->query("\n            UPDATE configuration\n            SET value = '$value'\n            WHERE id = $id\n        ") !== NULL;
    }

    if (!$ok)
    {
        $error = "Échec de l'écriture dans la table configuration";
        return (false);
    }

    // Keep the request-local configuration cache coherent as well.
    if (isset($Configuration) && isset($Configuration->Properties))
        $Configuration->Properties[persoc_deadlist_configuration_codename()] = $text;
    return (true);
}

function persoc_deadlist_response_ok($response)
{
    if (!is_array($response))
        return (false);

    $has_status = false;
    if (array_key_exists("result", $response))
    {
        $has_status = true;
        if (strtolower((string)$response["result"]) != "ok")
            return (false);
    }
    if (array_key_exists("status", $response))
    {
        $has_status = true;
        if (strtolower((string)$response["status"]) != "ok")
            return (false);
    }
    if (array_key_exists("ok", $response))
    {
        $has_status = true;
        $ok = $response["ok"];
        if ($ok !== true && !in_array(strtolower(trim((string)$ok)), ["1", "true", "ok"], true))
            return (false);
    }
    return ($has_status);
}

function persoc_deadlist_response_error($response, $fallback = "Réponse Distrans invalide")
{
    if ($response === false || $response === NULL)
        return ("Distrans est indisponible");
    if (!is_array($response))
        return ($fallback);
    foreach (["message", "msg", "error", "content"] as $field)
        if (isset($response[$field]) && trim((string)$response[$field]) != "")
            return (trim((string)$response[$field]));
    return ($fallback);
}

function persoc_deadlist_entries_from_response($response)
{
    if (!is_array($response))
        return ([]);

    $raw = [];
    if (isset($response["deadlist"]) && is_array($response["deadlist"]))
    {
        foreach ($response["deadlist"] as $entry)
        {
            if (is_array($entry))
                foreach (["entry", "value", "host", "hostname", "ip"] as $field)
                    if (isset($entry[$field]))
                    {
                        $raw[] = $entry[$field];
                        break ;
                    }
            if (!is_array($entry))
                $raw[] = $entry;
        }
    }
    else if (isset($response["deadlist_csv"]) && is_string($response["deadlist_csv"]))
    {
        foreach (preg_split('/\r\n|\r|\n/', $response["deadlist_csv"]) as $line)
            if (trim((string)$line) != "")
                $raw[] = $line;
    }

    $entries = [];
    $seen = [];
    foreach ($raw as $value)
    {
        $entry = persoc_deadlist_normalize_entry($value);
        if ($entry === false || $entry === NULL || isset($seen[$entry]))
            continue ;
        $seen[$entry] = true;
        $entries[] = $entry;
    }
    return ($entries);
}

function persoc_deadlist_remote_state()
{
    $response = hand_request(["command" => "get_deadlist"]);
    if (!persoc_deadlist_response_ok($response))
        return ([
            "ok" => false,
            "entries" => [],
            "error" => persoc_deadlist_response_error($response),
            "response" => $response,
        ]);
    return ([
        "ok" => true,
        "entries" => persoc_deadlist_entries_from_response($response),
        "error" => "",
        "response" => $response,
    ]);
}

function persoc_deadlist_push(array $entries = NULL)
{
    if ($entries === NULL)
        $entries = persoc_deadlist_local_entries();
    $response = hand_request([
        "command" => "set_deadlist",
        "deadlist" => array_values($entries),
    ]);
    if (!persoc_deadlist_response_ok($response))
        return ([
            "ok" => false,
            "error" => persoc_deadlist_response_error($response),
            "response" => $response,
        ]);
    return ([
        "ok" => true,
        "error" => "",
        "response" => $response,
    ]);
}

function persoc_deadlist_status($query_remote = true)
{
    $initialized = persoc_deadlist_storage_initialized();
    $local_text = persoc_deadlist_local_text();
    $local = persoc_deadlist_local_entries();
    $status = [
        "initialized" => $initialized,
        "local" => $local,
        "local_size" => strlen($local_text),
        "capacity" => persoc_deadlist_configuration_capacity(),
        "remote" => [],
        "remote_ok" => false,
        "remote_error" => "",
        "synchronized" => false,
    ];
    if (!$query_remote)
        return ($status);

    $remote = persoc_deadlist_remote_state();
    $status["remote_ok"] = $remote["ok"];
    $status["remote"] = $remote["entries"];
    $status["remote_error"] = $remote["error"];
    $status["synchronized"] = $initialized && $remote["ok"] && $remote["entries"] === $local;
    return ($status);
}
