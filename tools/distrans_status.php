<?php

// Canonical log message for the Distrans/Hand liveness heartbeat.
// Keep the legacy wording as an alias so old databases and old logs remain readable.
if (!defined("DISTRANS_RUNNING_LOG"))
    define("DISTRANS_RUNNING_LOG", "Distrans runs.");
if (!defined("DISTRANS_LEGACY_RUNNING_LOG"))
    define("DISTRANS_LEGACY_RUNNING_LOG", "Infosphere hand runs.");

function distrans_running_log_messages()
{
    return ([DISTRANS_RUNNING_LOG, DISTRANS_LEGACY_RUNNING_LOG]);
}

function distrans_running_log_condition($field = "message")
{
    global $Database;

    $conditions = [];
    foreach (distrans_running_log_messages() as $message)
    {
        $conditions[] = "$field = '".$Database->real_escape_string($message)."'";
        // Older calls through add_log() may have appended the impersonation suffix.
        $conditions[] = "$field LIKE '".$Database->real_escape_string($message." - Logged as %")."'";
    }
    return ("(".implode(" OR ", $conditions).")");
}

function distrans_last_running_log($id_user = NULL, $type = NULL)
{
    global $Database;

    $where = [distrans_running_log_condition("message")];
    if ($id_user !== NULL)
        $where[] = "id_user = ".((int)$id_user);
    if ($type !== NULL)
        $where[] = "type = '".$Database->real_escape_string((string)$type)."'";
    return (db_select_one("log_date, message FROM log WHERE ".implode(" AND ", $where)." ORDER BY log_date DESC"));
}

function distrans_current_log_url()
{
    global $LOG_COMPOSITION;

    if (@strlen($LOG_COMPOSITION))
        return ($LOG_COMPOSITION);
    if (function_exists("unrollget"))
        return (str_replace("&amp;", "&", unrollget()));
    return ("");
}

function distrans_current_log_ip()
{
    if (!function_exists("get_client_ip"))
        return (0);
    return (crc32(get_client_ip()) & 0x7FFFFFFF);
}

function distrans_touch_running_log($id_user = 1)
{
    global $Database;

    $id_user = (int)$id_user;
    $type = $Database->real_escape_string(defined("TRACE") ? TRACE : "0");
    $canonical = $Database->real_escape_string(DISTRANS_RUNNING_LOG);
    $condition = distrans_running_log_condition("message");

    $last = db_select_one("id FROM log WHERE id_user = $id_user AND type = '$type' AND $condition ORDER BY log_date DESC");
    if ($last != NULL)
    {
	$lastid = (int)$last["id"];
        return (!!$Database->query("
           UPDATE log
           SET log_date = NOW(), message = '$canonical'
           WHERE id = $lastid
	"));
    }

    $url = $Database->real_escape_string(distrans_current_log_url());
    $urlhash = crc32($url) & 0x7FFFFFFF;
    $ip = distrans_current_log_ip();
    return (!!$Database->query("
        INSERT INTO log (id_user, log_date, type, message, ip, url, urlhash)
        VALUES ($id_user, NOW(), '$type', '$canonical', $ip, '$url', $urlhash)
    "));
}
