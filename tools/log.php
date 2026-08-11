<?php

define("TRACE", "0");
define("UNCRITICAL_USER_DATA", "1");
define("CRITICAL_USER_DATA", "2");
define("CREATIVE_OPERATION", "3");
define("EDITING_OPERATION", "4");
define("DESTRUCTIVE_OPERATION", "5");
define("REPORT", "6");

$LogType = [
    TRACE, UNCRITICAL_USER_DATA, CRITICAL_USER_DATA,
    CREATIVE_OPERATION, EDITING_OPERATION, DESTRUCTIVE_OPERATION,
    REPORT
];

function add_log($type, $msg, $id_author = -1, $edit = false)
{
    global $Database;
    global $User;
    global $OriginalUser;
    global $LOG_COMPOSITION;

    // during subscription/public-form $User may already be set while
    // $OriginalUser is not (or conversely while the session is being restored).
    // logging must not assume that both globals always exist at the same time.
    $current_user = is_array($User) ? $User : NULL;
    $original_user = is_array($OriginalUser) ? $OriginalUser : NULL;

    if ($id_author == -1)
    {
	if ($original_user != NULL && isset($original_user["id"]))
	    $id_author = $original_user["id"];
	else if ($current_user != NULL && isset($current_user["id"]))
	    $id_author = $current_user["id"];
	else
	    throw new Exception ("InvalidParameter");
    }
    if (!isset($id_author) || !isset($type) || !isset($msg))
	throw new Exception ("InvalidParameter");
    $id = $Database->real_escape_string($oid_author = $id_author);
    $type = $Database->real_escape_string($otype = $type);
    $msg = $Database->real_escape_string($omsg = $msg);
    if ($current_user != NULL && $original_user != NULL
	&& isset($current_user["codename"], $original_user["codename"])
	&& $current_user["codename"] != $original_user["codename"])
	$msg .= " - Logged as ".$current_user["codename"];
    if (@strlen($LOG_COMPOSITION))
	$url = $LOG_COMPOSITION;
    else
	$url = str_replace("&amp;", "&", unrollget());
    $urlhash = crc32($url) & 0x7FFFFFFF;
    $ip = crc32(get_client_ip()) & 0x7FFFFFFF;
    if ($edit == false)
	return (!!$Database->query("
	  INSERT INTO log (id_user, log_date, type, message, ip, url, urlhash)
	  VALUES ('$id', NOW(), '$type', '$msg', $ip, '$url', $urlhash)
	"));
    if (($last = db_select_one("id FROM log WHERE id_user = $id AND message = '$msg'")) == NULL)
	return (add_log($otype, $omsg, $oid_author, false));
    return (!!$Database->query("
      UPDATE log SET log_date = NOW() WHERE id = ".$last["id"]."
      "));
}

function normalize_log_contexts($contexts)
{
    $out = [];
    if (!is_array($contexts))
	return ($out);
    foreach ($contexts as $k => $ctx)
    {
	if (is_array($ctx))
	{
	    if (isset($ctx["misc_type"]) && isset($ctx["id_misc"]))
	    {
		$type = $ctx["misc_type"];
		$id = $ctx["id_misc"];
	    }
	    else if (isset($ctx[0]) && isset($ctx[1]))
	    {
		$type = $ctx[0];
		$id = $ctx[1];
	    }
	    else
		continue ;
	}
	else
	{
	    if (!is_string($k))
		continue ;
	    $type = $k;
	    $id = $ctx;
	}
	$type = trim((string)$type);
	if ($type == "" || !preg_match('/^[a-zA-Z0-9_:-]+$/', $type))
	    continue ;
	if ($id === NULL || $id === "" || !is_numeric($id))
	    continue ;
	$id = (int)$id;
	$key = $type."#".$id;
	$out[$key] = ["misc_type" => $type, "id_misc" => $id];
    }
    return (array_values($out));
}

function add_log_context($id_log, $misc_type, $id_misc)
{
    global $Database;

    $id_log = (int)$id_log;
    $id_misc = (int)$id_misc;
    $misc_type = $Database->real_escape_string((string)$misc_type);
    if ($id_log <= 0 || $id_misc == 0 || $misc_type == "")
	return (false);
    return (!!$Database->query("\n        INSERT IGNORE INTO log_context (id_log, misc_type, id_misc)\n        VALUES ($id_log, '$misc_type', $id_misc)\n    "));
}

function add_log_contexts($id_log, $contexts)
{
    foreach (normalize_log_contexts($contexts) as $ctx)
	add_log_context($id_log, $ctx["misc_type"], $ctx["id_misc"]);
}

function contextual_log_default_url($contexts)
{
    $contexts = normalize_log_contexts($contexts);
    if (count($contexts) == 0)
	return (NULL);
    $ctx = $contexts[0];
    if ($ctx["misc_type"] == "activity" || $ctx["misc_type"] == "instance")
	return ("instance".$ctx["id_misc"]);
    return ($ctx["misc_type"].$ctx["id_misc"]);
}

function add_contextual_log($type, $msg, $contexts = [], $id_author = -1, $edit = false)
{
    global $Database;
    global $User;
    global $OriginalUser;
    global $LOG_COMPOSITION;

    if ($id_author == -1)
    {
	if ($User == NULL)
	    throw new Exception ("InvalidParameter");
	$id_author = $OriginalUser["id"];
    }
    if (!isset($id_author) || !isset($type) || !isset($msg))
	throw new Exception ("InvalidParameter");

    $oid_author = $id_author;
    $otype = $type;
    $omsg = $msg;
    $id = $Database->real_escape_string($id_author);
    $type = $Database->real_escape_string($type);
    $msg = $Database->real_escape_string($msg);
    if ($User && $OriginalUser && $User["codename"] != $OriginalUser["codename"])
	$msg .= " - Logged as ".$Database->real_escape_string($User["codename"]);

    if (@strlen($LOG_COMPOSITION))
	$url = $LOG_COMPOSITION;
    else if (($url = contextual_log_default_url($contexts)) == NULL)
	$url = str_replace("&amp;", "&", unrollget());
    $url = $Database->real_escape_string($url);
    $urlhash = crc32($url) & 0x7FFFFFFF;
    $ip = crc32(get_client_ip()) & 0x7FFFFFFF;

    if ($edit != false)
    {
	$last = db_select_one("id FROM log WHERE id_user = $id AND message = '$msg' ORDER BY id DESC LIMIT 1");
	if ($last != NULL)
	{
	    $Database->query("UPDATE log SET log_date = NOW() WHERE id = ".((int)$last["id"]));
	    add_log_contexts($last["id"], $contexts);
	    return (true);
	}
    }

    $ok = $Database->query("\n        INSERT INTO log (id_user, log_date, type, message, ip, url, urlhash)\n        VALUES ('$id', NOW(), '$type', '$msg', $ip, '$url', $urlhash)\n    ");
    if (!$ok)
	return (false);
    $id_log = $Database->insert_id;
    add_log_contexts($id_log, $contexts);
    return ($id_log);
}

function fetch_contextual_logs($contexts, $limit = 200, $types = NULL)
{
    global $Database;

    $contexts = normalize_log_contexts($contexts);
    if (count($contexts) == 0)
	return ([]);

    $clauses = [];
    $legacy = [];
    foreach ($contexts as $ctx)
    {
	$type = $Database->real_escape_string($ctx["misc_type"]);
	$id = (int)$ctx["id_misc"];
	$clauses[] = "(log_context.misc_type = '$type' AND log_context.id_misc = $id)";
	if ($type == "activity" || $type == "instance")
	{
	    $url = $Database->real_escape_string("instance".$id);
	    $urlhash = crc32("instance".$id) & 0x7FFFFFFF;
	    $legacy[] = "(log.urlhash = $urlhash AND log.url = '$url')";
	}
	else
	{
	    $url = $Database->real_escape_string($type.$id);
	    $urlhash = crc32($type.$id) & 0x7FFFFFFF;
	    $legacy[] = "(log.urlhash = $urlhash AND log.url = '$url')";
	}
    }
    $where = "(".implode(" OR ", array_merge($clauses, $legacy)).")";
    if ($types !== NULL)
    {
	if (!is_array($types))
	    $types = [$types];
	$clean = [];
	foreach ($types as $t)
	    if (is_numeric($t))
		$clean[] = (int)$t;
	if (count($clean))
	    $where .= " AND log.type IN (".implode(",", $clean).")";
    }
    $limit = max(1, min(1000, (int)$limit));

    return (db_select_all("\n        log.id, log.id_user, log.log_date, log.type, log.message, log.url, user.codename,\n        GROUP_CONCAT(DISTINCT CONCAT(log_context.misc_type, '#', log_context.id_misc) ORDER BY log_context.misc_type SEPARATOR ', ') as contexts\n        FROM log\n        LEFT JOIN user ON log.id_user = user.id\n        LEFT JOIN log_context ON log_context.id_log = log.id\n        WHERE $where\n        GROUP BY log.id\n        ORDER BY log.log_date DESC, log.id DESC\n        LIMIT $limit\n    "));
}

