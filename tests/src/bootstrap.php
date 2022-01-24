<?php
// @codeCoverageIgnoreStart
define("UNIT_TEST", "1");
require_once ("../tools/index.php");
require_once ("../language.php");

$silent = true;

function vline($v = "-")
{
    for ($i = 0; $i < 42; ++$i)
	echo $v;
    echo "\n";
}

function clear_table($table)
{
    global $Database;

    if (is_array($table))
	foreach ($table as $v)
	    clear_table($v);
    else
	$Database->query("DELETE FROM `$table`; VACUUM;");
}

function build_database(&$Database)
{
    $files = [];
    $files = glob("../*/install.sql");
    $files = array_merge($files, glob("../*/*/install.sql"));
    $query = [];
    foreach ($files as $f)
    {
	// echo "Loading scripts from $f.\n";
	$msg = file_get_contents($f);
	$msg = explode("\n", $msg);
	foreach ($msg as $i => $m)
	{
	    $m = preg_replace('/COLLATE.utf8_bin/', "", $m);
	    $m = preg_replace("/COMMENT.*,/", ",", $m);
	    $m = preg_replace("/COMMENT.*;/", ";", $m);
	    $m = preg_replace("/COMMENT.*/", "", $m);
	    $m = preg_replace("/ENGINE=InnoDB DEFAULT CHARSET=utf8/", "", $m);
	    $m = preg_replace("/current_timestamp\(\)/", "CURRENT_TIMESTAMP", $m);
	    $m = preg_replace("/ON UPDATE.*,$/", ",", $m);
	    $m = preg_replace("/ON UPDATE.*/", "", $m);
	    $m = preg_replace("/DEFAULT current_timestamp\(.*?\)/", "DEFAULT 0", $m);
	    $m = preg_replace("/AUTO_INCREMENT/", "", $m);
	    $msg[$i] = $m;
	}
	$msg = implode("\n", $msg);
	$query[] = $msg;
    }
    $query = implode("\n", $query);
    $query = SqlFormatter::splitQuery($query);
    foreach ($query as $q)
    {
	$x = [];
	$res = $Database->query($q);
	if (preg_match("/CREATE TABLE ([a-zA-Z0-9_`]+)/", $q, $x))
	{
	    /*
	    echo "Creating ".$x[0]." - ";
	    if ($res == NULL)
		echo "FAILURE\n";
	    else if ($res != "Skipped")
		echo "SUCCESS\n";
	    else
		echo "Skipped\n";
	    */
	}
    }
}

$_SERVER["REMOTE_ADDR"] = "0.0.0.0";
$_SERVER["REQUEST_URI"] = "localhost";
build_database($Database);
// @codeCoverageIgnoreStop
