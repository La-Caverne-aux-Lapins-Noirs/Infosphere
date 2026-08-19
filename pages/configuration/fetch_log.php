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
        "6" => "REPORT",
        "7" => "WARNING",
        "8" => "ERROR"
    ]);
}

function configuration_log_filters($source = NULL)
{
    if ($source === NULL)
        $source = $_GET;
    $size = (int)($source["log_size"] ?? 100);
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
        "context" => trim((string)($source["log_context"] ?? "")),
        "id_min" => trim((string)($source["log_id_min"] ?? "")),
        "id_max" => trim((string)($source["log_id_max"] ?? "")),
        "from" => trim((string)($source["log_from"] ?? "")),
        "to" => trim((string)($source["log_to"] ?? ""))
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

function configuration_log_context_parts($value)
{
    $value = trim((string)$value);
    if ($value == "")
        return (NULL);
    if (preg_match('/^([a-zA-Z0-9_:-]+)\s*[#:= ]\s*([0-9]+)$/', $value, $m))
        return (["type" => $m[1], "id" => (int)$m[2]]);
    if (preg_match('/^([a-zA-Z0-9_:-]+)$/', $value, $m))
        return (["type" => $m[1], "id" => NULL]);
    if (preg_match('/^#?([0-9]+)$/', $value, $m))
        return (["type" => NULL, "id" => (int)$m[1]]);
    return (NULL);
}

function configuration_log_context_where($value)
{
    global $Database;

    $ctx = configuration_log_context_parts($value);
    if ($ctx == NULL)
        return (NULL);
    $where = [];
    if ($ctx["type"] !== NULL)
        $where[] = "lc_filter.misc_type = '".$Database->real_escape_string($ctx["type"])."'";
    if ($ctx["id"] !== NULL)
        $where[] = "lc_filter.id_misc = ".((int)$ctx["id"]);
    if (!count($where))
        return (NULL);
    return ("EXISTS (SELECT 1 FROM log_context lc_filter WHERE lc_filter.id_log = log.id AND ".implode(" AND ", $where).")");
}

function configuration_log_where($filters)
{
    global $Database;

    $where = [];
    $where[] = "NOT (log.id_user = 1 AND log.type = 0 AND log.message IN ('Albedo starts.', 'Albedo stops.'))";
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
    if (isset($filters["context"]) && $filters["context"] !== ""
        && ($context_where = configuration_log_context_where($filters["context"])) !== NULL)
        $where[] = $context_where;
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
             log.id as id,
             GROUP_CONCAT(DISTINCT CONCAT(log_context.misc_type, '#', log_context.id_misc) ORDER BY log_context.misc_type, log_context.id_misc SEPARATOR ', ') as contexts
      FROM log
      LEFT OUTER JOIN user ON log.id_user = user.id
      LEFT OUTER JOIN log_context ON log_context.id_log = log.id
      WHERE $where
      GROUP BY log.id
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
        COUNT(DISTINCT log.id) as cnt
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
        "log_context" => $filters["context"] ?? "",
        "log_from" => $filters["from"] ?? "",
        "log_to" => $filters["to"] ?? "",
        "log_size" => $filters["size"] ?? 100,
        "log_page" => $filters["page"] ?? 0
    ];
    foreach ($extra as $key => $value)
        $map[$key] = $value;
    foreach ($map as $key => $value)
        echo '<input type="hidden" name="'.configuration_html($key).'" value="'.configuration_html($value).'" />';
}
