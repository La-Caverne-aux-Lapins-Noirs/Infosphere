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

    if ($id_author == -1)
    {
	if ($User == NULL)
	    throw new Exception ("InvalidParameter");
	$id_author = $OriginalUser["id"];
    }
    if (!isset($id_author) || !isset($type) || !isset($msg))
	throw new Exception ("InvalidParameter");
    $id = $Database->real_escape_string($oid_author = $id_author);
    $type = $Database->real_escape_string($otype = $type);
    $msg = $Database->real_escape_string($omsg = $msg);
    if ($User && $User["codename"] != $OriginalUser["codename"])
	$msg .= " - Logged as ".$User["codename"];
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

