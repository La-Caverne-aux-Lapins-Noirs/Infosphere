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

function add_log($type, $msg, $id_author = -1)
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
    $id = $Database->real_escape_string($id_author);
    $type = $Database->real_escape_string($type);
    $msg = $Database->real_escape_string($msg);
    if ($User["codename"] != $OriginalUser["codename"])
	$msg .= " - Logged as ".$User["codename"];
    $url = str_replace("&amp;", "&", unrollget());
    $urlhash = crc32($url);
    return (!!$Database->query("
      INSERT INTO log (id_user, log_date, type, message, ip, url, urlhash)
      VALUES ('$id', NOW(), '$type', '$msg', '".get_client_ip()."', '$url', $urlhash)
      "));
}

