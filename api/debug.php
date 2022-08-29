<?php
$debug_log = [];

function _debug_response_tree(&$out, $data)
{
    global $Database;

    foreach ($data as $k => $v)
    {
	if (is_array($v))
	    $out[$k] = _debug_response_tree($out[$k], $v);
	else
	    $out[$k] = substr(strval($Database->real_escape_string($v)), 0, 32);
    }
    return ($out);
}

function debug_response_tree($data, $label = "")
{
    $out = [];
    _debug_response_tree($out, $data);
    debug_response($out, $label);
}

function debug_response($data, $label = "")
{
    global $debug_log;

    if ($label != "")
	$label = "$label:\n\n";
    //$debug_log[] = htmlentities(PrintR($label, true).PrintR($data, true));
    $debug_log[] = PrintR($label, true).PrintR($data, true);
}

function gtfo($msg = "")
{
    throw new Exception($msg);
}

function debug_responsek($data, $label = "", $msg = "")
{
    debug_response($data, $label);
    gtfo($msg);
}

function debug_packet()
{
    global $debug_log;

    if ($debug_log != [])
    {
	echo '{"result":"DEBUG","msg":"DEBUG","content":"'.implode($debug_log).'"}';
	die();
    }
}
