<?php

function configuration_log_type_labels()
{
    return ([
        "0" => "TRACE",
        "1" => "UNCRITICAL_USER_DATA",
        "2" => "CRITICAL_USER_DATA",
        "3" => "CREATIVE_OPERATION",
        "4" => "EDITING_OPERATION",
        "5" => "DESTRUCTIVE_OPERATION",
        "6" => "REPORT"
    ]);
}

function configuration_log_filters($source = NULL)
{
    if ($source === NULL)
        $source = $_GET;
    $size = (int)($source["log_size"] ?? 75);
    if (!in_array($size, [25, 50, 75, 100, 150, 200]))
        $size = 75;
    return ([
        "page" => max(0, (int)($source["log_page"] ?? 0)),
        "size" => $size,
        "type" => trim((string)($source["log_type"] ?? "")),
        "search" => trim((string)($source["log_search"] ?? "")),
        "user" => trim((string)($source["log_user"] ?? "")),
        "ip" => trim((string)($source["log_ip"] ?? "")),
        "url" => trim((string)($source["log_url"] ?? "")),
        "id_min" => trim((string)($source["log_id_min"] ?? "")),
        "id_max" => trim((string)($source["log_id_max"] ?? "")),
        "from" => trim((string)($source["log_from"] ?? "")),
        "to" => trim((string)($source["log_to"] ?? "")),
        "with_system" => (int)($source["log_system"] ?? 0) == 1
    ]);
}

function configuration_log_datetime($value, $end_of_day = false)
{
    $value = trim((string)$value);
    if ($value == "")
        return (NULL);
    $timestamp = strtotime(str_replace("T", " ", $value));
    if ($timestamp === false)
        return (NULL);
    if ($end_of_day && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
        $timestamp += 24 * 60 * 60 - 1;
    return (date("Y-m-d H:i:s", $timestamp));
}

function configuration_log_ip_hash($value)
{
    $value = trim((string)$value);
    if ($value == "")
        return (NULL);
    if (is_number($value))
        return ((int)$value);
    return (crc32($value) & 0x7FFFFFFF);
}

function configuration_log_where($filters)
{
    global $Database;

    $where = [];
    if (!isset($filters["with_system"]) || !$filters["with_system"])
        $where[] = "(log.id_user != 1 OR log.type != 0)";
    if (isset($filters["type"]) && $filters["type"] !== "" && is_number($filters["type"]))
        $where[] = "log.type = ".((int)$filters["type"]);
    if (isset($filters["id_min"]) && $filters["id_min"] !== "" && is_number($filters["id_min"]))
        $where[] = "log.id >= ".((int)$filters["id_min"]);
    if (isset($filters["id_max"]) && $filters["id_max"] !== "" && is_number($filters["id_max"]))
        $where[] = "log.id <= ".((int)$filters["id_max"]);
    if (isset($filters["user"]) && $filters["user"] !== "")
    {
        if (is_number($filters["user"]))
            $where[] = "log.id_user = ".((int)$filters["user"]);
        else
        {
            $user = "%".$Database->real_escape_string($filters["user"])."%";
            $where[] = "user.codename LIKE '$user'";
        }
    }
    if (isset($filters["ip"]) && $filters["ip"] !== "")
    {
        $ip = configuration_log_ip_hash($filters["ip"]);
        if ($ip !== NULL)
            $where[] = "log.ip = $ip";
    }
    if (isset($filters["url"]) && $filters["url"] !== "")
    {
        if (is_number($filters["url"]))
            $where[] = "log.urlhash = ".((int)$filters["url"]);
        else
        {
            $url = "%".$Database->real_escape_string($filters["url"])."%";
            $where[] = "log.url LIKE '$url'";
        }
    }
    if (isset($filters["from"]) && ($from = configuration_log_datetime($filters["from"])) !== NULL)
        $where[] = "log.log_date >= '".$Database->real_escape_string($from)."'";
    if (isset($filters["to"]) && ($to = configuration_log_datetime($filters["to"], true)) !== NULL)
        $where[] = "log.log_date <= '".$Database->real_escape_string($to)."'";
    if (isset($filters["search"]) && $filters["search"] !== "")
    {
        $search = "%".$Database->real_escape_string($filters["search"])."%";
        $where[] = "(log.message LIKE '$search' OR user.codename LIKE '$search' OR log.url LIKE '$search')";
    }
    if (!count($where))
        return ("1");
    return (implode(" AND ", $where));
}

function fetch_log($page = 0, $filters = NULL, $size = NULL)
{
    if ($filters === NULL)
        $filters = configuration_log_filters();
    if ($size === NULL)
        $size = $filters["size"] ?? 75;
    $page = max(0, (int)$page);
    $size = max(1, min(200, (int)$size));
    $offset = $page * $size;
    $where = configuration_log_where($filters);
    return (db_select_all("
             log.id_user as id_user,
             user.codename as user,
             log.log_date as date,
             log.type as type,
             log.message as message,
             log.ip as ip,
             log.url as url,
             log.urlhash as urlhash,
             log.id as id
      FROM log
      LEFT OUTER JOIN user ON log.id_user = user.id
      WHERE $where
      ORDER BY log.id DESC
      LIMIT $offset, $size
    "));
}

function fetch_log_count($filters = NULL)
{
    if ($filters === NULL)
        $filters = configuration_log_filters();
    $where = configuration_log_where($filters);
    $count = db_select_one("
        COUNT(*) as cnt
        FROM log
        LEFT OUTER JOIN user ON log.id_user = user.id
        WHERE $where
    ");
    return ((int)($count["cnt"] ?? 0));
}


function configuration_log_hidden_inputs($filters, $extra = [])
{
    $map = [
        "log_search" => $filters["search"] ?? "",
        "log_type" => $filters["type"] ?? "",
        "log_user" => $filters["user"] ?? "",
        "log_ip" => $filters["ip"] ?? "",
        "log_url" => $filters["url"] ?? "",
        "log_from" => $filters["from"] ?? "",
        "log_to" => $filters["to"] ?? "",
        "log_system" => !empty($filters["with_system"]) ? 1 : 0,
        "log_size" => $filters["size"] ?? 75,
        "log_page" => $filters["page"] ?? 0
    ];
    foreach ($extra as $key => $value)
        $map[$key] = $value;
    foreach ($map as $key => $value)
        echo '<input type="hidden" name="'.configuration_html($key).'" value="'.configuration_html($value).'" />';
}
