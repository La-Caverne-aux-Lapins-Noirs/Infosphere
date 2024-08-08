<?php
// @codeCoverageIgnoreStart
@define("UNIT_TEST", 0);
@define("INSTALLATION", 1);

// A FAIRE POUR AMELIORER LE SERVICE:
// Etendre la taille max du POST et d'upload
// Agrandir la taille maximale accessible d'une requete (pour les gros cookies)

require_once ("language.php");
require_once ("tools/response.php");
require_once ("tools/hand_request.php");
require_once ("tools/authentication.php");
require_once ("tools/set_cookie.php");
require_once ("tools/log.php");
require_once ("tools/database.php");
require_once ("tools/send_mail.php");
require_once ("tools/utils.php");
require_once ("tools/ext/sql_formatter.php");

function build_htaccess()
{
    ob_start();
    ?>
    <Files "database.json">
	Order Allow,Deny
	Deny from all
    </Files>
    
    <Files .htaccess>
	Order allow,deny
	Deny from all
    </Files>
<?php
    return (ob_get_clean());
}

$ErrorMsg = "";
if (isset($_POST["destroy"]) && file_exists("version.php"))
{
    if (($json = json_decode(file_get_contents("./database.json"), true)) != NULL)
    {
	if ($json["password"] == $_POST["password"])
	{
	    require_once ("./tools/db_connect.php");
	    foreach (db_get_tables() as $t)
		$Database->query("DROP TABLE `$t`");
	    unlink("./version.php");
	    unlink("./database.json");
	}
    }
    goto Formular;
}

if (isset($_POST["host"]) && !file_exists("version.php"))
{
    if (strlen($_POST["admin_password"]) < 8)
    {
	$ErrorMsg = "BadPassword";
	goto Formular;
    }
    if ($_POST["admin_password"] != $_POST["admin_repassword"])
    {
	$ErrorMsg = "PasswordDoesNotMatch";
	goto Formular;
    }
    // On installe les informations de connexion à la base de donnée

    $htaccess = [];
    foreach (explode("\n", build_htaccess()) as $lin)
	$htaccess[] = trim($lin);
    if (@file_put_contents("./.htaccess", implode("\n", $htaccess)) === false)
    {
	$ErrorMsg = "CannotWriteHtaccess";
	goto Formular;
    }

    $data["host"] = $_POST["host"];
    $data["login"] = $_POST["login"];
    $data["password"] = $_POST["password"];
    $data["database"] = $_POST["database"];
    if (($json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false)
    {
	$ErrorMsg = "CannotGenerateJSON";
	goto Formular;
    }
    if (file_put_contents("./database.json", $json) === false)
    {
	$ErrorMsg = "CannotWriteDatabaseJSON";
	goto Formular;
    }
    // On se connecte à la base de donnée
    require_once ("./tools/db_connect.php");
    if ($Database === NULL)
    {
	$ErrorMsg = "CannotConnectToDatabase.";
	goto Formular;
    }

    // On installe l'interieur de la base de donnée
    $files = [];
    $files = array_merge($files, glob("database.sql"));
    $files = array_merge($files, glob("*/database.sql"));
    $files = array_merge($files, glob("*/*/database.sql"));
    $files = array_merge($files, glob("update.sql"));
    $files = array_merge($files, glob("update/*.sql"));
    $files = array_merge($files, glob("*/update.sql"));
    $files = array_merge($files, glob("*/*/update.sql"));
    $sql = "";
    foreach ($files as $f)
	$sql .= file_get_contents($f)."\n";
    foreach (explode(";", $sql) as $req)
	if (trim($req) != "")
	    $Database->query($req);
    
    foreach (db_get_tables() as $table)
	$Database->query("
           ALTER TABLE `$table`
             MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
	");
    
    if (($msg = subscribe($_POST["admin_login"], $_POST["admin_mail"], $_POST["admin_password"]))->is_error())
    {
	$ErrorMsg = $msg->label;
	goto Formular;
    }
    $Database->query("
        UPDATE user
        SET authority = 1
        WHERE id = 1
    ");
    $Database->query("
        INSERT INTO configuration (codename, value) VALUES 
        ('subscription_possible', 1),
        ('self_signing', 1),
        ('mailgun_sender', '{$_POST["admin_mail"]}'),
        ('welcome_note', NULL),
        ('mail_password', NULL),
        ('style', 'default');
	");
    $t = "<?php // @codeCoverageIgnoreStart\n".'$version = "0.1";'."\n// @codeCoverageIgnoreEnd\n";
    file_put_contents("./version.php", $t);
    // On ajoute la crontab pour Albedo - toutes les 2 minutes, ca roule
    // Avec les écritures en cas de probleme dans /tmp.
    // Ce systeme avec /tmp est nul. Il faudra faire autrement.
    system("crontab -l > albecron");
    if (file_put_contents(
	"albecron", 
	"*/2 * * * * /usr/bin/php ".getcwd()."/albedo.php ".
	escapeshellarg($_POST["admin_login"])." ".
	escapeshellarg($_POST["admin_password"])." ".
	" > /tmp/albedo 2> /tmp/albedo\n",
	FILE_APPEND
    ) == false)
    {
	$ErrorMsg = "CannotWriteCrontab";
	goto Formular;
    }
    ob_start();
    $out = system("crontab albecron && echo 'OK' || echo 'FailToAddCrontab' ; rm -f albecron");
    ob_end_clean();
    if (substr($out, 0, 2) != "OK")
    {
	$ErrorMsg = $out;
	goto Formular;
    }
}
    
if (file_exists("version.php"))
{
    require_once ("version.php"); ?>
	<html>
	    <head>
    		<style>
		 @font-face
		 {
		     src: url("/res/futura.ttf");
		     font-family: futura;
		 }
		 *
		 {
		     font-family: futura;
		 }
		 body
		 {
		     background: linear-gradient(to bottom, black, white, white, black);
		     text-align: center;
		 }
		</style>
	    </head>
	    <body>
		<img src="res/logo.png" width="700" height="700" /><br />
		<div style=
		     "text-align: center;
		     font-size: xx-large;
		     position: absolute;
		     top: 33%;
		     width: 99%;
		     margin-left: 0.5%;
		     ">
		    Infosphere installed.<br />
		    Version is <?=$version; ?><br />
		    <br />
		    <form method="post" action="install.php">
			<input type="password" name="password" placeholder="Database password" />
			<input type="submit" name="destroy" value="Destroy infosphere" />
		    </form>
		</div>
	    </body>
	</html>
	<?php
    exit;
}

Formular:
require_once ("install.phtml");
// @codeCoverageIgnoreEnd
?>
