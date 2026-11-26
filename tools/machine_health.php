<?php

require_once __DIR__."/machine_metrics.php";

function machine_health_ready()
{
    global $Database;
    static $ready = NULL;
    if ($ready !== NULL) return ($ready);
    try
    {
        $sql = file_get_contents(__DIR__."/../pages/configuration/machine_health.sql");
        return ($ready = $Database->query($sql) !== false);
    }
    catch (Throwable $e)
    {
        return ($ready = false);
    }
}

function machine_health_desk_key($desk)
{
    $mac = tc_metrics_mac($desk["mac"] ?? NULL);
    return ($mac !== NULL ? "desk:".$mac : NULL);
}

function machine_health_sample_values(array $sample)
{
    $online = $sample["online"] ?? NULL;
    $metrics = $online === true ? ($sample["metrics"] ?? []) : [];
    $state = machine_health_evaluate($sample, $sample["observed_at"]);
    $disks = array_column($metrics["disks"] ?? [], "used_percent");
    $temperatures = array_column($metrics["temperatures"] ?? [], "celsius");
    return (["t" => $sample["observed_at"], "cpu" => $metrics["cpu_percent"] ?? NULL,
        "ram" => $metrics["memory"]["used_percent"] ?? NULL, "disk" => $disks ? max($disks) : NULL,
        "temp" => $temperatures ? max($temperatures) : NULL,
        "rtt" => $online ? ($sample["response_ms"] ?? NULL) : NULL,
        "score" => $state["score"], "up" => $online === NULL ? NULL : ($online ? 100 : 0)]);
}

function machine_health_fetch(array $keys, $since = NULL, $compact = false, $latest_only = false)
{
    global $Database;
    if (!$keys || !machine_health_ready()) return ([]);
    $since = $since ?? time() - 48 * 3600;
    $keys = array_unique(array_filter($keys, "is_string"));
    if (!$keys) return ([]);
    $escaped = array_map(static function($key) use ($Database) {
        return "'".$Database->real_escape_string($key)."'";
    }, $keys);
    $out = [];
    $filter = "node_key IN (".implode(",", $escaped).")";
    $sql = $latest_only
        ? "SELECT node_key, observed_at, payload FROM machine_health_sample
            INNER JOIN (SELECT node_key, MAX(observed_at) AS observed_at FROM machine_health_sample
                WHERE $filter GROUP BY node_key) latest USING (node_key, observed_at)"
        : "SELECT node_key, observed_at, payload FROM machine_health_sample
            WHERE $filter AND observed_at >= ".(int)$since." ORDER BY node_key, observed_at";
    $query = $Database->query($sql, defined("MYSQLI_USE_RESULT") ? MYSQLI_USE_RESULT : 0);
    if (!$query) return ([]);
    while ($row = $query->fetch_assoc())
    {
        $sample = json_decode($row["payload"], true);
        if (!is_array($sample)) continue;
        $sample["observed_at"] = (int)$row["observed_at"];
        $index = count($out[$row["node_key"]] ?? []);
        if ($compact && $index > 0)
        {
            $previous = $out[$row["node_key"]][$index - 1];
            $out[$row["node_key"]][$index - 1] = ["observed_at" => $previous["observed_at"],
                "plot" => machine_health_sample_values($previous)];
        }
        $out[$row["node_key"]][] = $sample;
    }
    if (method_exists($query, "free")) $query->free();
    return ($out);
}

function machine_health_store($key, array $sample, $at)
{
    global $Database;
    $key = $Database->real_escape_string($key);
    $json = json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return (false);
    $json = $Database->real_escape_string($json);
    // One observation per two-minute bucket, including repeated manual runs.
    $at = (int)(floor($at / 120) * 120);
    return ($Database->query("INSERT INTO machine_health_sample (node_key, observed_at, payload)
        VALUES ('$key', $at, '$json') ON DUPLICATE KEY UPDATE payload = VALUES(payload)") !== false);
}

function machine_health_collect(array $probe)
{
    global $Database;
    if (!machine_health_ready())
    {
        add_log(REPORT, "Machine monitoring: history table unavailable", 1, true);
        return (false);
    }
    $now = time();
    $last = machine_health_fetch(["infosphere"], $now - 3600);
    $last = !empty($last["infosphere"]) ? end($last["infosphere"]) : [];
    $ok = machine_health_store("infosphere", ["kind" => "local", "online" => true,
        "metrics" => tc_metrics_collect($last["metrics"] ?? [], [getcwd()]), "response_ms" => NULL], $now);

    $remote = NULL;
    $response_ms = $probe["response_ms"] ?? NULL;
    if (!empty($probe["online"]))
    {
        $start = microtime(true);
        $remote = hand_request(["command" => "get_health"], true, 10);
        if (is_array($remote) && ($remote["result"] ?? "") === "ok"
            && ($remote["protocol"] ?? 0) === 1)
            $response_ms = round(1000 * (microtime(true) - $start), 2);
        else
            $remote = NULL;
    }
    $ok = machine_health_store("distrans", ["kind" => "server",
        "online" => !empty($probe["online"]), "response_ms" => $response_ms,
        "metrics" => is_array($remote["server"] ?? NULL) ? tc_metrics_normalize($remote["server"]) : NULL,
        "issue" => !empty($probe["online"]) && !$remote ? "telemetry_unavailable" : NULL], $now) && $ok;

    // Include assigned AND unassigned machines. When Distrans is unreachable,
    // their state is unknown, rather than attributing its outage to every PC.
    $remote_machines = [];
    foreach ($remote["machines"] ?? [] as $machine)
    {
        if (!is_array($machine)) continue;
        $key = machine_health_desk_key($machine);
        if ($key !== NULL) $remote_machines[$key] = $machine;
    }
    foreach (db_select_all("id, mac FROM room_desk") as $desk)
    {
        $key = machine_health_desk_key($desk);
        if ($key === NULL) continue;
        $machine = $remote_machines[$key] ?? NULL;
        $online = $remote !== NULL ? (bool)($machine["online"] ?? false) : NULL;
        $sample = ["kind" => "workstation", "online" => $online,
            "response_ms" => $online ? tc_metrics_number($machine["response_ms"] ?? NULL, 0, 60000) : NULL,
            "metrics" => $online && is_array($machine["metrics"] ?? NULL)
                ? tc_metrics_normalize($machine["metrics"]) : NULL,
            "metrics_at" => $machine["metrics_at"] ?? NULL,
            "issue" => $remote === NULL ? "upstream_unavailable" : NULL];
        $ok = machine_health_store($key, $sample, $now) && $ok;
    }
    $ok = $Database->query("DELETE FROM machine_health_sample WHERE observed_at < ".($now - 48 * 3600)) !== false && $ok;
    if (!$ok) add_log(REPORT, "Machine monitoring: cannot persist all observations", 1, true);
    return ($ok);
}

function machine_health_component($value, $warning, $critical)
{
    if ($value <= $warning) return (100.0);
    return (max(0.0, 100 * ($critical - $value) / ($critical - $warning)));
}

function machine_health_evaluate($sample, $now = NULL)
{
    $now = $now ?? time();
    $state = ["status" => "unknown", "label" => "Sans mesure", "score" => NULL,
        "reasons" => [], "components" => [], "missing" => [], "bad" => false];
    if (!$sample) return ($state);
    if ($now - (int)($sample["observed_at"] ?? 0) > 360
        || (int)($sample["observed_at"] ?? 0) > $now + 5)
    {
        $state["label"] = "Collecte sans relevé récent";
        return ($state);
    }
    if (($sample["online"] ?? NULL) === false)
    {
        if (($sample["kind"] ?? "") === "workstation")
            return (array_merge($state, ["status" => "off", "label" => "Éteint / sans signal"]));
        return (array_merge($state, ["status" => "critical", "label" => "Distrans injoignable",
            "score" => 0.0, "bad" => true, "reasons" => ["Le serveur Distrans ne répond pas."]]));
    }
    if (($sample["online"] ?? NULL) === NULL)
    {
        $state["label"] = "État inconnu · liaison Distrans indisponible";
        return ($state);
    }
    $metrics = $sample["metrics"] ?? [];
    $components = [];
    $reasons = [];
    $critical = false;
    $inputs = [
        "CPU" => [$metrics["cpu_percent"] ?? NULL, 85, 98],
        "RAM" => [$metrics["memory"]["used_percent"] ?? NULL, 85, 97],
    ];
    $disks = $metrics["disks"] ?? [];
    $disk_usage = NULL;
    foreach ($disks as $disk)
        $disk_usage = max($disk_usage ?? 0, $disk["used_percent"]);
    $inputs["Disque"] = [$disk_usage, 80, 90];
    foreach ($inputs as $label => [$value, $warning, $limit])
    {
        if ($value === NULL) { $state["missing"][] = $label; continue; }
        $components[$label] = machine_health_component($value, $warning, $limit);
        if ($value >= $warning) $reasons[] = $label." : ".round($value, 1)." % utilisés";
        if ($value >= $limit) $critical = true;
    }
    $temp_score = NULL;
    foreach ($metrics["temperatures"] ?? [] as $temp)
    {
        $value = machine_health_component($temp["celsius"], $temp["warning"], $temp["critical"]);
        $temp_score = min($temp_score ?? 100, $value);
        if ($temp["celsius"] >= $temp["warning"])
            $reasons[] = $temp["label"]." : ".round($temp["celsius"], 1)." °C";
        if ($temp["celsius"] >= $temp["critical"]) $critical = true;
    }
    if ($temp_score !== NULL) $components["Température"] = $temp_score;
    else $state["missing"][] = "Température";
    $resource_count = count($components);
    if (($sample["kind"] ?? "") !== "local")
    {
        $rtt = tc_metrics_number($sample["response_ms"] ?? NULL, 0, 60000);
        if ($rtt !== NULL)
        {
            $components["Réponse"] = machine_health_component($rtt, 1000, 5000);
            if ($rtt >= 1000) $reasons[] = "Réponse : ".round($rtt)." ms";
            if ($rtt >= 5000) $critical = true;
        }
        else $state["missing"][] = "Réponse";
    }
    if (!$resource_count)
    {
        $state["label"] = "En ligne · mesures indisponibles";
        return ($state);
    }
    // Log-space geometric mean; one exhausted resource gives a zero score.
    $score = in_array(0.0, $components, true) ? 0.0
        : exp(array_sum(array_map(static fn($value) => log($value), $components)) / count($components));
    $status = $critical || $score < 40 ? "critical" : ($reasons || $score < 70 ? "warning" : "ok");
    return (array_merge($state, ["status" => $status, "label" => ["ok" => "Bon état",
        "warning" => "À surveiller", "critical" => "État dégradé"][$status].
            ($state["missing"] ? " · mesures partielles" : ""),
        "score" => round($score, 1), "reasons" => $reasons, "components" => $components,
        "bad" => $status !== "ok"]));
}

function machine_health_server_alerts()
{
    $out = [];
    foreach (machine_health_fetch(["infosphere", "distrans"], time() - 600) as $key => $rows)
    {
        $state = machine_health_evaluate(end($rows));
        if ($state["bad"])
            $out[$key] = ($key === "infosphere" ? "Infosphère" : "Distrans")." : ".
                $state["label"].($state["reasons"] ? " — ".implode(" ; ", $state["reasons"]) : "");
    }
    return ($out);
}
