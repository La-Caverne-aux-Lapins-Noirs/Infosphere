<?php

require_once (__DIR__."/../../tools/distrans_status.php");

function configuration_html($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}

function configuration_javascript($value)
{
    return (htmlspecialchars(json_encode((string)$value), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}

function configuration_bool($value)
{
    if ($value === NULL)
        return (false);
    if (is_bool($value))
        return ($value);
    $value = strtolower(trim((string)$value));
    return ($value != "" && $value != "0" && $value != "false" && $value != "no" && $value != "null");
}

function configuration_table_columns()
{
    static $columns = NULL;
    global $Database;

    if ($columns !== NULL)
        return ($columns);

    $columns = [];
    $query = $Database->query("SHOW COLUMNS FROM configuration");
    if ($query !== NULL)
    {
        while (($row = $query->fetch_assoc()) != NULL)
            if (isset($row["Field"]))
                $columns[$row["Field"]] = true;
    }

    if (!isset($columns["id"]))
    {
        $columns = [
            "id" => true,
            "codename" => true,
            "value" => true
        ];
    }
    return ($columns);
}

function configuration_column_exists($column)
{
    $columns = configuration_table_columns();
    return (isset($columns[$column]));
}

function configuration_order_clause()
{
    $order = [];
    foreach (["position", "rank", "weight", "priority", "id"] as $field)
        if (configuration_column_exists($field))
            $order[] = "`$field`";
    $order[] = "`codename`";
    return (implode(", ", $order));
}

function configuration_rows()
{
    return (db_select_all("* FROM configuration ORDER BY ".configuration_order_clause(), "codename"));
}

function configuration_row_flag($row, $fields)
{
    foreach ($fields as $field)
        if (isset($row[$field]) && configuration_bool($row[$field]))
            return (true);
    return (false);
}

function configuration_row_secured($row)
{
    return (configuration_row_flag($row, ["secured", "secure", "secret"]));
}

function configuration_row_hidden($row)
{
    if (configuration_row_secured($row))
        return (true);
    if (configuration_row_flag($row, ["hidden", "hide", "masked", "sensitive", "dangerous", "problematic", "problem"]))
        return (true);
    if (isset($row["visible"]) && !configuration_bool($row["visible"]))
        return (true);
    if (isset($row["display"]) && !configuration_bool($row["display"]))
        return (true);
    return (false);
}

function configuration_row_readonly($row)
{
    return (configuration_row_flag($row, ["readonly", "read_only", "locked"]));
}

function configuration_row_description($row)
{
    global $Language;

    $language = isset($Language) ? $Language : "fr";
    foreach ([
        $language."_description",
        "description",
        $language."_comment",
        "comment"
    ] as $field)
        if (isset($row[$field]) && trim($row[$field]) != "")
            return ($row[$field]);
    return ("");
}

function configuration_row_label($row)
{
    global $Language;

    $language = isset($Language) ? $Language : "fr";
    foreach ([
        $language."_name",
        $language."_label",
        "name",
        "label"
    ] as $field)
        if (isset($row[$field]) && trim($row[$field]) != "")
            return ($row[$field]);
    return ($row["codename"]);
}

function configuration_public_value($row)
{
    if (!isset($row["value"]))
        return ("");
    if (configuration_row_secured($row))
        return ("");
    return ($row["value"]);
}

function configuration_value_for_edition($row)
{
    if (!isset($row["value"]))
        return ("");
    if (configuration_row_secured($row))
        return ("");
    return ($row["value"]);
}

function configuration_row_kind($row)
{
    if (configuration_row_readonly($row))
        return ("readonly");
    if (configuration_row_secured($row))
        return ("secured");
    if (configuration_row_hidden($row))
        return ("hidden");
    return ("normal");
}

function configuration_operation_default_list()
{
    return ([
        "run_albedo.php",
        "backup.php",
        "ping_hand.php",
        "new_ldap_user.php",
        "test_mail.php",
        "antispam.php",
        "regen_medals.php",
        "cipher.php",
        "new_home.php",
        "deploy_exam.php",
        "workspy_test.php",
        "get_versions.php"
    ]);
}

function configuration_operation_discovered_list()
{
    $files = [];
    foreach (["custom_*.php", "operation_*.php", "usual_*.php"] as $pattern)
        foreach (glob(__DIR__."/".$pattern) as $file)
            $files[] = basename($file);
    return ($files);
}

function configuration_operation_list()
{
    global $Operations;

    if (!isset($Operations) || !is_array($Operations))
        $Operations = configuration_operation_default_list();

    $all = array_merge($Operations, configuration_operation_discovered_list());
    $out = [];
    foreach ($all as $operation)
    {
        $operation = basename($operation);
        if (isset($out[$operation]))
            continue ;
        if (!preg_match('/^[a-zA-Z0-9_\-.]+\.php$/', $operation))
            continue ;
        if (!file_exists(__DIR__."/".$operation))
            continue ;
        if (in_array($operation, [
            "index.php",
            "access.php",
            "model.php",
            "render.php",
            "style.php",
            "fetch_log.php",
            "handle_request.php",
            "usual_operation.php"
        ]))
            continue ;
        $out[$operation] = [
            "name" => $operation,
            "path" => __DIR__."/".$operation,
            "title" => configuration_operation_title($operation)
        ];
    }
    return (array_values($out));
}

function configuration_operation_title($operation)
{
    $title = preg_replace('/\.php$/', '', basename($operation));
    $title = preg_replace('/^(custom|operation|usual)_/', '', $title);
    $title = str_replace("_", " ", $title);
    return (ucfirst($title));
}

function configuration_operation_source($operation)
{
    ob_start();
    highlight_file($operation["path"]);
    return (ob_get_clean());
}

function configuration_write_htaccess()
{
    $out = "<Files .htaccess>\n  Order allow,deny\n  Deny from all\n</Files>\n";
    foreach (configuration_operation_list() as $operation)
    {
        $file = $operation["name"];
        $out .= "\n<Files $file>\n  Order allow,deny\n  Deny from all\n</Files>\n";
    }
    $path = __DIR__."/.htaccess";
    if (!file_exists($path) || file_get_contents($path) != $out)
        file_put_contents($path, $out);
}


function configuration_last_log_message($message)
{
    global $Database;

    $message = $Database->real_escape_string($message);
    return (db_select_one("log_date, message FROM log WHERE message = '$message' ORDER BY log_date DESC"));
}

function configuration_recent_log_stats($hours = 24)
{
    $hours = max(1, min(24 * 31, (int)$hours));
    return (db_select_all("type, COUNT(*) as cnt FROM log WHERE log_date >= DATE_SUB(NOW(), INTERVAL $hours HOUR) GROUP BY type ORDER BY type"));
}

function configuration_execute_operation_by_id($operation_id, &$outfile = NULL)
{
    $operation_id = (int)$operation_id;
    $operations = configuration_operation_list();
    $outfile = NULL;
    if (!isset($operations[$operation_id]))
        return (["result" => "Invalid operation.", "outfile" => NULL, "operation" => NULL]);

    $operation = $operations[$operation_id];
    ob_start();
    require ($operation["path"]);
    $result = ob_get_clean();
    if ($result == "")
        $result = "Operation executed.";
    return (["result" => $result, "outfile" => $outfile, "operation" => $operation]);
}

function configuration_render_panel_content($file, $data = [])
{
    global $Dictionnary;
    global $Language;
    global $Configuration;
    global $Database;
    global $User;
    global $OriginalUser;

    $configuration_panel_data = is_array($data) ? $data : [];
    if (is_array($configuration_panel_data))
        extract($configuration_panel_data, EXTR_SKIP);
    ob_start();
    require (__DIR__."/".$file);
    return (ob_get_clean());
}

function configuration_current_query($extra = [])
{
    $query = $_GET;
    foreach ($extra as $key => $value)
    {
        if ($value === NULL)
            unset($query[$key]);
        else
            $query[$key] = $value;
    }
    return (http_build_query($query));
}

function configuration_format_bytes($bytes)
{
    $units = ["o", "Ko", "Mo", "Go", "To"];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1)
    {
        $bytes /= 1024;
        $i += 1;
    }
    return (round($bytes, $i == 0 ? 0 : 1)." ".$units[$i]);
}
