<?php

// Shared wire format, version 1. Keep this collector identical in Persoc,
// Distrans and Infosphere. Linux counters require no privileged command.
function tc_metrics_string($value, $limit)
{
    return (is_string($value)
        ? json_decode(json_encode(substr($value, 0, $limit), JSON_INVALID_UTF8_SUBSTITUTE), true) : "");
}

function tc_metrics_mac($value)
{
    if (!is_string($value)) return (NULL);
    $hex = strtolower(str_replace([":", "-", "."], "", trim($value)));
    return (preg_match('/^[0-9a-f]{12}$/D', $hex) ? implode(":", str_split($hex, 2)) : NULL);
}

function tc_metrics_number($value, $min = 0, $max = PHP_INT_MAX)
{
    if (!is_numeric($value) || !is_finite((float)$value))
        return (NULL);
    $value = (float)$value;
    return ($value >= $min && $value <= $max ? $value : NULL);
}

function tc_metrics_read($path)
{
    $value = @file_get_contents($path);
    return ($value === false ? "" : trim($value));
}

function tc_metrics_normalize(array $input)
{
    $out = ["version" => 1];
    foreach (["sampled_at", "uptime_seconds", "cpu_count"] as $key)
        $out[$key] = tc_metrics_number($input[$key] ?? NULL);
    $out["cpu_percent"] = tc_metrics_number($input["cpu_percent"] ?? NULL, 0, 100);
    $out["load_1"] = tc_metrics_number($input["load_1"] ?? NULL);
    $out["boot_id"] = tc_metrics_string($input["boot_id"] ?? "", 64);
    $ticks = is_array($input["cpu_ticks"] ?? NULL) ? $input["cpu_ticks"] : [];
    $memory = is_array($input["memory"] ?? NULL) ? $input["memory"] : [];
    $out["cpu_ticks"] = [];
    foreach (["total", "idle"] as $key)
        $out["cpu_ticks"][$key] = tc_metrics_number($ticks[$key] ?? NULL);
    $out["memory"] = [];
    foreach (["total_bytes", "available_bytes", "used_bytes", "swap_total_bytes", "swap_used_bytes"] as $key)
        $out["memory"][$key] = tc_metrics_number($memory[$key] ?? NULL);
    $out["memory"]["used_percent"] = tc_metrics_number($memory["used_percent"] ?? NULL, 0, 100);
    $out["disks"] = [];
    foreach (array_slice(is_array($input["disks"] ?? NULL) ? $input["disks"] : [], 0, 32) as $disk)
    {
        if (!is_array($disk)) continue;
        $total = tc_metrics_number($disk["total_bytes"] ?? NULL, 1);
        $free = tc_metrics_number($disk["free_bytes"] ?? NULL, 0, $total ?? 0);
        if ($total === NULL || $free === NULL) continue;
        $out["disks"][] = [
            "path" => tc_metrics_string($disk["path"] ?? "/", 255),
            "total_bytes" => $total, "free_bytes" => $free,
            "used_percent" => round(100 * (1 - $free / $total), 2),
        ];
    }
    $out["temperatures"] = [];
    foreach (array_slice(is_array($input["temperatures"] ?? NULL) ? $input["temperatures"] : [], 0, 64) as $temp)
    {
        if (!is_array($temp)) continue;
        $value = tc_metrics_number($temp["celsius"] ?? NULL, -20, 200);
        if ($value === NULL) continue;
        $critical = tc_metrics_number($temp["critical"] ?? NULL, 40, 200) ?? 90;
        $warning = tc_metrics_number($temp["warning"] ?? NULL, 20, $critical - 1)
            ?? min(75, $critical - 10);
        $out["temperatures"][] = [
            "label" => tc_metrics_string($temp["label"] ?? "Capteur", 80),
            "celsius" => $value, "warning" => $warning, "critical" => $critical,
        ];
    }
    return ($out);
}

function tc_metrics_cpu(array $previous, $proc = "/proc")
{
    $stat = tc_metrics_read($proc."/stat");
    $ticks = ["total" => NULL, "idle" => NULL];
    if (preg_match('/^cpu\s+([0-9 ]+)/m', $stat, $match))
    {
        // guest/guest_nice are already included in user/nice.
        $values = array_map("floatval", preg_split('/\s+/', trim($match[1])));
        $ticks = ["total" => array_sum(array_slice($values, 0, 8)),
                  "idle" => ($values[3] ?? 0) + ($values[4] ?? 0)];
    }
    $boot = tc_metrics_read($proc."/sys/kernel/random/boot_id");
    $old = $previous["cpu_ticks"] ?? [];
    $percent = NULL;
    if ($boot !== "" && $boot === ($previous["boot_id"] ?? "")
        && isset($old["total"], $old["idle"], $ticks["total"]))
    {
        $total = $ticks["total"] - $old["total"];
        $idle = $ticks["idle"] - $old["idle"];
        if ($total > 0 && $idle >= 0 && $idle <= $total)
            $percent = round(100 * (1 - $idle / $total), 2);
    }
    preg_match_all('/^cpu[0-9]+\s/m', $stat, $cores);
    return (["cpu_ticks" => $ticks, "boot_id" => $boot,
             "cpu_percent" => $percent, "cpu_count" => count($cores[0]) ?: NULL]);
}

function tc_metrics_memory($proc = "/proc")
{
    preg_match_all('/^(\w+):\s+(\d+)\s+kB/m', tc_metrics_read($proc."/meminfo"), $matches);
    $mem = [];
    foreach ($matches[1] as $index => $name)
        $mem[$name] = (float)$matches[2][$index] * 1024;
    $total = $mem["MemTotal"] ?? NULL;
    $available = $mem["MemAvailable"] ?? (isset($mem["MemFree"])
        ? $mem["MemFree"] + ($mem["Buffers"] ?? 0) + ($mem["Cached"] ?? 0)
          + ($mem["SReclaimable"] ?? 0) - ($mem["Shmem"] ?? 0) : NULL);
    $available = $total !== NULL && $available !== NULL ? max(0, min($total, $available)) : NULL;
    $used = $total !== NULL && $available !== NULL ? $total - $available : NULL;
    return (["total_bytes" => $total, "available_bytes" => $available, "used_bytes" => $used,
        "used_percent" => $total > 0 && $used !== NULL ? round(100 * $used / $total, 2) : NULL,
        "swap_total_bytes" => $mem["SwapTotal"] ?? NULL,
        "swap_used_bytes" => isset($mem["SwapTotal"], $mem["SwapFree"])
            ? max(0, $mem["SwapTotal"] - $mem["SwapFree"]) : NULL]);
}

function tc_metrics_temperatures($sys = "/sys")
{
    $out = [];
    foreach (glob($sys."/class/hwmon/hwmon*/temp*_input") ?: [] as $file)
    {
        $raw = tc_metrics_number(tc_metrics_read($file), -20000, 200000);
        if ($raw === NULL) continue;
        $base = substr($file, 0, -6);
        $chip = tc_metrics_read(dirname($file)."/name");
        $label = tc_metrics_read($base."_label") ?: basename($base);
        $out[] = ["label" => trim($chip." · ".$label, " ·"), "celsius" => $raw / 1000,
            "warning" => (float)tc_metrics_read($base."_max") / 1000,
            "critical" => (float)tc_metrics_read($base."_crit") / 1000];
    }
    // Some SoCs expose their CPU only here even when an NVMe has hwmon data.
    foreach (glob($sys."/class/thermal/thermal_zone*/temp") ?: [] as $file)
    {
        $raw = tc_metrics_number(tc_metrics_read($file), -20000, 200000);
        if ($raw !== NULL)
            $out[] = ["label" => tc_metrics_read(dirname($file)."/type") ?: "Thermal zone",
                      "celsius" => $raw / 1000];
    }
    return ($out);
}

function tc_metrics_disks(array $paths = [], $proc = "/proc")
{
    $paths = array_merge(["/"], $paths);
    foreach (explode("\n", tc_metrics_read($proc."/mounts")) as $line)
    {
        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 3 || !in_array($parts[2],
            ["ext2", "ext3", "ext4", "xfs", "btrfs", "zfs", "vfat", "ntfs3", "overlay"], true))
            continue;
        $paths[] = preg_replace_callback('/\\\\([0-7]{3})/', static function($m) {
            return chr(octdec($m[1]));
        }, $parts[1]);
    }
    $out = [];
    foreach (array_slice(array_unique($paths), 0, 32) as $path)
    {
        if (!is_dir($path)) continue;
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if ($total > 0 && $free !== false)
            $out[] = ["path" => $path, "total_bytes" => $total, "free_bytes" => $free];
    }
    return ($out);
}

function tc_metrics_collect(array $previous = [], array $paths = [], $proc = "/proc", $sys = "/sys")
{
    $uptime = explode(" ", tc_metrics_read($proc."/uptime"))[0];
    $load = explode(" ", tc_metrics_read($proc."/loadavg"))[0];
    return (tc_metrics_normalize(tc_metrics_cpu($previous, $proc) + [
        "sampled_at" => time(), "uptime_seconds" => tc_metrics_number($uptime),
        "load_1" => tc_metrics_number($load), "memory" => tc_metrics_memory($proc),
        "disks" => tc_metrics_disks($paths, $proc), "temperatures" => tc_metrics_temperatures($sys),
    ]));
}
