<?php

require_once ("ext/sql_formatter.php");

$DBPerf = 0;
$DBHistory = [];
$DBMerge = [];
$DBCount = 0;

function formatstr($str)
{
    return (str_replace("\n", " ", $str)."\n");
}

class DatabaseAssoc
{
    public $obj;

    function __construct($obj)
    {
	$this->obj = $obj;
    }

    function fetch_assoc()
    {
	if (!UNIT_TEST)
	    return ($this->obj->fetch_assoc()); // @codeCoverageIgnore
	return ($this->obj->fetchArray(SQLITE3_ASSOC));
    }
}

class Database
{
    private $logfile = "./dres/db.log";
    private $last_query = NULL;
    public $db;
    public $dbname;
    public $debug;
    public $insert_id;
    public $affected_rows;

    public $print_on_error = []; // Remplir ca pour afficher des informations en cas d'erreur

    /**
     * @codeCoverageIgnore
     */
    function __construct(string		$url,
			 string		$user,
			 string		$pass,
			 string		$dbname,
			 bool		$debug = false,
			 string		$dbfile = "./database.sql")
    {
	if (!UNIT_TEST)
	    $this->db = new mysqli($url, $user, $pass, $dbname);
	else
	    $this->db = new SQLite3($dbfile);
	if ($this->db == NULL)
	    exit ;
	$this->dbname = $dbname;
	$this->debug = $debug;
    }

    function query($req, $display = false)
    {
	global $DBPerf;
	global $DBHistory;
	global $DBMerge;
	global $DBCount;
	global $BaseDir;

	if (UNIT_TEST)
	{
	    if (preg_match("/ALTER/", $req))
		return ("Skipped"); // @codeCoverageIgnore
	    $req = preg_replace("/CONCAT\(([a-zA-Z0-9_']+), ([a-zA-Z0-9_']+)\)/", '$1 || $2', $req);
	    $req = preg_replace("/NOW\(\)/", "date('now')", $req);
	    $out = [];
	    $out2 = [];
	    preg_match("/INSERT INTO ([a-zA-Z0-9_`]+)/", $req, $out);
	    preg_match("/INSERT INTO [a-zA-Z0-9_`]+ \(([a-zA-Z0-9_`]+)/", $req, $out2);
	    if (count($out))
	    {
		$out = $out[1];
		$id = $this->query("SELECT id FROM $out ORDER BY id DESC");
		if (($id = $id->fetch_assoc()) == false)
		    $id = 1;
		else
		    $id = $id["id"] + 1;
		if ($out2[1] != "id" && $out2[1] != "`id`")
		{
		    $req = preg_replace("/INSERT INTO ([a-zA-Z0-9_`]+) \(/", 'INSERT INTO $1 (id, ', $req);
		    $req = preg_replace("/VALUES \(/", "VALUES ($id, ", $req);
		}
	    }
	}

	if ($display)
	{
	    global $albedo;

	    if ($albedo == 1)
		echo $req;
	    else if ($BaseDir == "")
		AddDebugLogR($req); // @codeCoverageIgnore
	    else
		debug_response($req);
	}
	$Before = microtime(true);
	if (($last_query = @$this->db->query($req)) == NULL)
	{
	    $msg = "";
	    // @codeCoverageIgnoreStart
	    foreach ($this->print_on_error as $poe)
		$msg .= PrintR($poe, true);
	    $msg .= "-----------------\n".date("d/m/y H:i:s", time())."\n".SqlFormatter::format($req, false);
	    if (!UNIT_TEST)
		$msg .= formatstr("#> ".$this->db->error);
	    else
		$msg .= formatstr("#> ".$this->db->lastErrorMsg());
	    $back = debug_backtrace();
	    for ($i = 0; isset($back[$i]); ++$i)
		$msg .= "#> ".$back[$i]["file"].":".$back[$i]["line"]."\n";
	    $msg .= "-----------------\n";
	    if ((isset($User["authority"]) && $User["authority"] >= ADMINISTRATOR) || $this->debug)
	    {
		if (!UNIT_TEST)
		{
		    if ($BaseDir == "")
			echo "<div class='db_bug_div'>".str_replace("\n", "<br />", $msg)."</div>";
		    else
			echo strip_tags($msg);
		}
		else
		    echo $msg;
	    }
	    if ($this->logfile != "" && 0) // Désactivation
		file_put_contents($this->logfile, $msg."\n", FILE_APPEND | LOCK_EX);
	    return (NULL);
	    // @codeCoverageIgnoreEnd
	}
	$After = microtime(true);
	$tmp = str_replace("\n", " ", $req);
	// $DBHistory[] = ["delay" => $After - $Before, "query" => $tmp];
	$DBCount += 1;

	$call = debug_backtrace();
	for ($i = 0; isset($call[$i]) && $i < 8; ++$i);
	$xcall = "";
	for ($j = 0; $j < $i; ++$j)
	    $xcall .= "\t".$call[$j]["file"].":".$call[$j]["line"];
	$xcall = str_replace(getcwd(), "", $xcall);
	$hash = hash("md5", $xcall, false);
	if (isset($DBMerge[$hash]))
	{
	    $DBMerge[$hash]["delay"] += $After - $Before;
	    $DBMerge[$hash]["count"] += 1;
	}
	else
	    $DBMerge[$hash] = [
		"delay" => $After - $Before,
		"count" => 1,
		"back" => $xcall,
		"query" => $tmp
	    ];

	$DBPerf += $After - $Before;

	if (!UNIT_TEST)
	    $this->insert_id = $this->db->insert_id; // @codeCoverageIgnore
	else
	    $this->insert_id = $this->db->lastInsertRowId();

	$this->affected_rows = $this->db->affected_rows;

	return (new DatabaseAssoc($last_query));
    }

    function real_escape_string($str)
    {
	if (!UNIT_TEST)
	    return ($this->db->real_escape_string($str)); // @codeCoverageIgnore
	return ($this->db->escapeString($str));
    }
}

class DBSelect
{
    private $Assoc;

    function query($query, $display = false)
    {
	global $Database;

	if ($this->Assoc == NULL)
	    if (($this->Assoc = $Database->query("SELECT ".$query, $display)) == NULL)
		return (false); // @codeCoverageIgnore
	if (($r = $this->Assoc->fetch_assoc()) == NULL)
	    $this->Assoc = NULL;
	return ($r);
    }
}

function db_select_all($query, $key_field = "", $display = false)
{
    global $DBSelect;

    $data = [];
    while (($r = $DBSelect->query($query, $display)))
    {
	if ($key_field != "")
	{
	    if ($r[$key_field] == "")
		continue ;
	    $data[$r[$key_field]] = $r;
	}
	else
	    $data[] = $r;
    }
    return ($data);
}

function db_select_one($query, $display = false)
{
    global $Database;

    if (($ret = $Database->query("SELECT ".$query." LIMIT 1  ", $display)) == NULL)
	return (NULL); // @codeCoverageIgnore
    return ($ret->fetch_assoc());
}

function db_update_one($table, $id, array $fields, $fetch = false, $display = false)
{
    global $Database;
    
    if (is_symbol($id))
	if (($id = resolve_codename($table, $id))->is_error())
	    return (NULL);
    $mods = [];
    foreach ($fields as $k => $v)
    {
	if ($v !== NULL)
	{
	    if (!is_number($v))
		$mods[] = "`".$Database->real_escape_string($k)."` = '".$Database->real_escape_string($v)."'";
	    else
		$mods[] = "`".$Database->real_escape_string($k)."` = ".((int)$v)." ";
	}
	else
	    $mods[] = "`".$Database->real_escape_string($k)."` = NULL";
    }
    if (!is_array($id))
	$filter = " `id` = $id ";
    else
    {
	$filter = [];
	foreach ($id as $k => $v)
	{
	    $k = $Database->real_escape_string($k);
	    if (!is_int($v))
	    {
		$v = $Database->real_escape_string($v);
		$filter[] = " `$k` = '$v' ";
	    }
	    else
		$filter[] = " `$k` = $v ";
	}
	$filter = implode(" AND ", $filter);
    }
    $mods = implode(", ", $mods);
    if (($ret = $Database->query("UPDATE `$table` SET $mods WHERE $filter ", $display)) == NULL)
	return (NULL);
    if ($fetch && $Database->affected_rows)
	return (db_select_one("* FROM `$table` WHERE $filter "));
    return ($Database->affected_rows != 0);
}

function db_print_all($query, $key_field = "", $ret = false)
{
    $out = "";
    foreach (db_select_all($query, $key_field) as $x)
	print_r($x);
}

function dump_tables($table, $line = "")
{
    global $Database;

    if (!is_array($table))
	$table = [$table];
    foreach ($table as $t)
    {
	if ($line != "")
	{
	    vline($line);
	    echo "Dumping $t:\n";
	}
	db_print_all("* FROM `".$Database->real_escape_string($t)."`");
    }
}

function set_db_err_print($x)
{
    global $Database;

    $Database->print_on_error = [$x];
}

function add_db_err_print($x)
{
    global $Database;

    $Database->print_on_error[] = $x;
}

function secure_text($msg, $cipher = "")
{
    global $Database;

    $msg = strip_tags($msg);
    $msg = str_replace("\n", "<br />", trim($msg));
    if ($cipher != "")
	$msg = openssl_encrypt(
	    $msg,
	    openssl_get_cipher_methods()[0],
	    $cipher,
	    0, "azertyui01234567"
	);
    $msg = $Database->real_escape_string($msg);
    return ($msg);
}

function get_secured_text($msg, $cipher = "")
{
    if ($cipher != "")
	$msg = openssl_decrypt(
	    $msg,
	    openssl_get_cipher_methods()[0],
	    $cipher,
	    0, "azertyui01234567"
	);
    return ($msg);
}

function edit_secured_text($msg, $cipher = "")
{
    $msg = get_secured_text($msg, $cipher);
    $msg = str_replace("<br />", "\n", $msg);
    return ($msg);
}

function db_select_rows($table, $blist)
{
    global $Database;

    $rows = db_select_all($x = "
      `COLUMN_NAME`
      FROM `INFORMATION_SCHEMA`.`COLUMNS`
      WHERE `TABLE_SCHEMA`='{$Database->dbname}' AND `TABLE_NAME`='$table'
    ");
    $out = [];
    foreach ($rows as $r)
    {
	$fnd = false;
	foreach ($blist as $b)
	{
	    if ($b == $r["COLUMN_NAME"])
		$fnd = true;
	}
	if ($fnd == false)
	    $out[] = $r["COLUMN_NAME"];
    }
    return ($out);
}

function db_get_tables()
{
    global $Database;

    $tables = [];
    foreach (db_select_all("
      TABLE_NAME FROM `INFORMATION_SCHEMA`.`TABLES`
      WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = '{$Database->dbname}'
    ", "TABLE_NAME") as $tab)
    {
	$tables[$tab["TABLE_NAME"]] = $tab["TABLE_NAME"];
    }
    return ($tables);
}

