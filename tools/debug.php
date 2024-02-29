<?php

$DebugLog = "";

function systemd($cmd, $res = null)
{
    AddDebugLogR($cmd);
    $r = shell_exec($cmd." 2>1& ");
    AddDebugLogR($r === null ? "ERROR" : $r);
    return ($r);
}

$UniqueBacktrack = false;

function backtrack_msg($uniq = false)
{
    global $UniqueBacktrack;

    if ($UniqueBacktrack)
	return ;
    if ($uniq)
	$UniqueBacktrack = true;
    $msg = "";
    $back = debug_backtrace();
    for ($i = 0; isset($back[$i]); ++$i)
	$msg .= "#> ".$back[$i]["file"].":".$back[$i]["line"]."\n";
    return ($msg);
}

function backtrack($uniq = false)
{
    AddDebugLogR(backtrack_msg($uniq));
}

function AddDebugLog($msg, $admin = true)
{
    global $OriginalUser;

    if ($admin && (!isset($OriginalUser) || $OriginalUser["authority"] < ADMINISTRATOR))
	return ;
    global $DebugLog;

    $DebugLog .= "'".$msg."'\n";
    return (0);
}
$TraceNbr = 0;
function TraceL()
{
    global $TraceNbr;

    AddDebugLog("-- Trace $TraceNbr --");
    $TraceNbr += 1;
}

function DebugQuery($query)
{
    return (str_replace("\n", "<br />", str_replace(" ", "&nbsp;", $query)));
}

function PrintR($v, $ret = false)
{
    $v = print_r($v, true);
    $v = str_replace(" ", "&nbsp;", $v);
    $v = str_replace("\n", "<br />", $v);
    $v .= "<br />";
    if (!$ret)
	echo $v; // @codeCoverageIgnore
    return ($v);
}

function AddDebugLogR($v, $admin = true)
{
    AddDebugLog(PrintR($v, true), $admin);
    return (true);
}

function error_backtrace($errno, $errstr, $errfile, $errline, $errcontext)
{
    if(!(error_reporting() & $errno))
        return;
    switch($errno)
    {
	case E_WARNING      :
	case E_USER_WARNING :
	case E_STRICT       :
	case E_NOTICE       :
	case E_USER_NOTICE  :
            $type = 'warning';
            $fatal = false;
            break;
	default             :
            $type = 'fatal error';
            $fatal = true;
            break;
    }
    $trace = debug_backtrace();
    array_pop($trace);
    if(php_sapi_name() == 'cli') {
        echo 'Backtrace from ' . $type . ' \'' . $errstr . '\' at ' . $errfile . ' ' . $errline . ':' . "\n";
        foreach($trace as $item)
        echo '  ' . (isset($item['file']) ? $item['file'] : '<unknown file>') . ' ' . (isset($item['line']) ? $item['line'] : '<unknown line>') . ' calling ' . $item['function'] . '()' . "\n";
    } else {
        echo '<p class="error_backtrace">' . "\n";
        echo '  Backtrace from ' . $type . ' \'' . $errstr . '\' at ' . $errfile . ' ' . $errline . ':' . "\n";
        echo '  <ol>' . "\n";
        foreach($trace as $item)
        echo '    <li>' . (isset($item['file']) ? $item['file'] : '<unknown file>') . ' ' . (isset($item['line']) ? $item['line'] : '<unknown line>') . ' calling ' . $item['function'] . '()</li>' . "\n";
        echo '  </ol>' . "\n";
        echo '</p>' . "\n";
    }
    if(ini_get('log_errors')) {
        $items = array();
        foreach($trace as $item)
        $items[] = (isset($item['file']) ? $item['file'] : '<unknown file>') . ' ' . (isset($item['line']) ? $item['line'] : '<unknown line>') . ' calling ' . $item['function'] . '()';
        $message = 'Backtrace from ' . $type . ' \'' . $errstr . '\' at ' . $errfile . ' ' . $errline . ': ' . join(' | ', $items);
        error_log($message);
    }
    if($fatal)
        exit(1);
}

set_error_handler('error_backtrace');
