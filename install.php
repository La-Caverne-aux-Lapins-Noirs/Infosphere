<?php
// @codeCoverageIgnoreStart
@define("UNIT_TEST", 0);

// A FAIRE POUR AMELIORER LE SERVICE:
// Etendre la taille max du POST et d'upload
// Agrandir la taille maximale accessible d'une requete (pour les gros cookies)

require_once ("language.php");
require_once ("tools/authentication.php");
require_once ("tools/log.php");
require_once ("tools/database.php");
require_once ("tools/send_mail.php");
require_once ("tools/utils.php");
require_once ("tools/ext/sql_formatter.php");

$ErrorMsg = "";
if (isset($_POST["destroy"]) && file_exists("version.php"))
{
    if (($data = json_decode(file_get_contents("./database.json"), true)) != NULL)
    {
	require_once ("version.php");

	if ($data["password"] == $_POST["password"] && isset($tables))
	{
	    $Database = new Database($data["host"], $data["login"], $data["password"], $data["database"], true);
	    foreach ($tables as $t)
		$Database->query("DROP TABLE `$t`");
	    unlink("./version.php");
	    //unlink("./database.json");
	}
    }
}
else if (isset($_POST["host"]) && !file_exists("version.php"))
{
    if (strlen($_POST["admin_password"]) < 8)
	$ErrorMsg = "BadPassword";
    else if ($_POST["admin_password"] != $_POST["admin_repassword"])
	$ErrorMsg = "PasswordDoesNotMatch";
    else
    {
	// On installe les informations de connexion à la base de donnée
	$data["host"] = $_POST["host"];
	$data["login"] = $_POST["login"];
	$data["password"] = $_POST["password"];
	$data["database"] = $_POST["database"];
	$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	file_put_contents("./database.json", $json);

	// On se connecte à la base de donnée
	$Database = new Database($data["host"], $data["login"], $data["password"], $data["database"], true);

	// On installe l'interieur de la base de donnée
	$files = [];
	$files = glob("*/install.sql");
	$files = array_merge($files, glob("*/*/install.sql"));
	$query = [];
	foreach ($files as $f)
	{
	    $f = SqlFormatter::splitQuery(file_get_contents($f));
	    foreach ($f as $q)
		$query[] = $q;
	}
	if (file_exists("./update.sql"))
	{
	    $f = SqlFormatter::splitQuery(file_get_contents("./update.sql"));
	    foreach ($f as $q)
		$query[] = $q;
	}
	if (is_dir("./update/"))
	{
	    foreach (scandir("./update/") as $dir)
	    {
		if ($dir == "." || $dir == "..")
		    continue ;
		$f = SqlFormatter::splitQuery(file_get_contents($dir));
		foreach ($f as $q)
		     $query[] = $q;
	    }
	}
	$tables = [];
	foreach ($query as $q)
	{
	    if (preg_match("/CREATE TABLE ([a-zA-Z0-9_`]+)/", $q, $x))
	    {
		$x = $x[1];
		if (substr($x, 0, 1) == '`')
		    $x = substr($x, 1, strlen($x) - 2);
		$x = "'$x'";
		$tables[] = $x;
	    }
	    $Database->query($q);
	}

	// On ajoute le compte administrateur
	$msg = subscribe($_POST["admin_login"], $_POST["admin_mail"], $_POST["admin_password"]);
	$Database->query("UPDATE user SET authority = 6 WHERE codename = '".$_POST["admin_login"]."'");
	if ($msg["Error"] != "")
	{
	    foreach ($tables as $t)
	    {
		$t = str_replace("'", "`", $t);
		$Database->query("DROP TABLE $t");
	    }
	    $ErrorMsg = $msg["Error"];
	}
	else
	{
	    // On conlut en ecrivant le fichier qui interdit la reinstallation et
	    // contient le numero de version.
	    $t = "<?php // @codeCoverageIgnoreStart\n".'  $version = "0.1";'."\n";
	    $t .= '  $tables = ['."\n    ";
	    $t .= implode(",\n    ", $tables);
	    $t .= "\n  ];\n// @codeCoverageIgnoreEnd\n";
	    file_put_contents("./version.php", $t);

	    // On ajoute la crontab pour Albedo - toutes les 2 minutes, ca roule
	    system("echo '*/2 * * * * /usr/bin/php ".getcwd()."/albedo.php ".escapeshellarg($_POST["admin_login"])." ".escapeshellarg($_POST["admin_password"])." >> /tmp/albedo 2>> /tmp/albedo' > /var/spool/cron/crontabs/www-data");
	}
    }
}

if (file_exists("version.php"))
{
    require_once ("version.php");

    echo '<html><body><div style="text-align: center; font-size: xx-large; position: absolute; top: 33%; width: 100%;">';
    echo 'Infosphere installed.<br />Version is '.$version.'.<br /><br />';
    echo '<form method="post" action="install.php"><input type="password" name="password" placeholder="Database password" /><input type="submit" name="destroy" value="Destroy infosphere" /></form>';
    echo '</div></body></html>';
    exit;
}

require_once ("install.phtml");
// @codeCoverageIgnoreEnd
?>
