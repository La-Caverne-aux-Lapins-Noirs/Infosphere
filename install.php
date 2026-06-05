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
require_once ("tools/authentication/index.php");
require_once ("tools/set_cookie.php");
require_once ("tools/log.php");
require_once ("tools/database.php");
require_once ("tools/utils.php");
require_once ("tools/ext/sql_formatter.php");


function install_append_warning(&$warnings, $message)
{
    $warnings[] = $message;
    error_log("Infosphere install: ".$message);
}

function install_write_file_if_different($path, $content)
{
    if (file_exists($path) && file_get_contents($path) === $content)
	return (true);
    return (@file_put_contents($path, $content) !== false);
}

function install_command_path($command)
{
    if (!function_exists("shell_exec"))
	return ("");
    return (trim((string)@shell_exec("command -v ".escapeshellarg($command)." 2> /dev/null")));
}

function install_exec($command, &$output, &$return_value)
{
    $output = [];
    $return_value = 127;
    if (!function_exists("exec"))
	return (false);
    @exec($command, $output, $return_value);
    return (true);
}

function install_apache_hls_configuration(&$warnings)
{
    $content = "<IfModule mod_mime.c>\n".
	"  AddType application/vnd.apple.mpegurl .m3u8\n".
	"  AddType video/iso.segment .m4s\n".
	"  AddType video/mp4 .mp4\n".
	"</IfModule>\n";
    @mkdir("res/install/apache", 0775, true);
    @file_put_contents("res/install/apache/infosphere-hls.conf", $content);

    $available = "/etc/apache2/conf-available";
    $target = $available."/infosphere-hls.conf";
    if (!is_dir($available))
    {
	install_append_warning(
	    $warnings,
	    "Apache2 conf-available was not found; HLS MIME configuration was written to res/install/apache/infosphere-hls.conf only."
	);
	return (false);
    }
    if (!is_writable($available) && !(file_exists($target) && is_writable($target)))
    {
	install_append_warning(
	    $warnings,
	    "Cannot write ".$target."; copy res/install/apache/infosphere-hls.conf there manually and enable it with a2enconf infosphere-hls."
	);
	return (false);
    }
    if (!install_write_file_if_different($target, $content))
    {
	install_append_warning($warnings, "Cannot write ".$target.".");
	return (false);
    }
    @chmod($target, 0644);

    $a2enconf = install_command_path("a2enconf");
    if ($a2enconf != "")
    {
	$out = [];
	$ret = 0;
	install_exec(escapeshellcmd($a2enconf)." infosphere-hls 2>&1", $out, $ret);
	if ($ret != 0)
	    install_append_warning($warnings, "a2enconf infosphere-hls failed: ".implode(" ", $out));
    }
    else
    {
	$enabled = "/etc/apache2/conf-enabled/infosphere-hls.conf";
	if (is_dir(dirname($enabled)) && is_writable(dirname($enabled)) && !file_exists($enabled))
	    @symlink($target, $enabled);
	if (!file_exists($enabled))
	    install_append_warning($warnings, "a2enconf was not found; enable infosphere-hls.conf manually.");
    }

    $apache2ctl = install_command_path("apache2ctl");
    if ($apache2ctl != "")
    {
	$out = [];
	$ret = 0;
	install_exec(escapeshellcmd($apache2ctl)." configtest 2>&1", $out, $ret);
	if ($ret != 0)
	{
	    install_append_warning($warnings, "apache2ctl configtest failed after adding infosphere-hls.conf: ".implode(" ", $out));
	    return (false);
	}
    }

    if (function_exists("posix_geteuid") && posix_geteuid() == 0)
    {
	$out = [];
	$ret = 0;
	install_exec("systemctl reload apache2 2>&1", $out, $ret);
	if ($ret != 0)
	{
	    $out = [];
	    install_exec("service apache2 reload 2>&1", $out, $ret);
	}
	if ($ret != 0)
	    install_append_warning($warnings, "Apache2 was configured for HLS but could not be reloaded automatically; reload it manually.");
    }
    else
    {
	install_append_warning($warnings, "Apache2 was configured for HLS; reload it manually if this installer was not allowed to do so.");
    }
    return (true);
}

function install_download_file($url, $destination)
{
    $tmp = $destination.".download";
    @unlink($tmp);
    $context = stream_context_create([
	"http" => ["timeout" => 60],
	"https" => ["timeout" => 60]
    ]);
    $data = @file_get_contents($url, false, $context);
    if ($data !== false && strlen($data) > 50000 && @file_put_contents($tmp, $data) !== false)
    {
	@rename($tmp, $destination);
	return (file_exists($destination) && filesize($destination) > 50000);
    }

    $commands = [
	"curl -fsSL --max-time 60 -o ".escapeshellarg($tmp)." ".escapeshellarg($url),
	"wget -q -T 60 -O ".escapeshellarg($tmp)." ".escapeshellarg($url)
    ];
    foreach ($commands as $cmd)
    {
	$out = [];
	$ret = 0;
	install_exec($cmd." 2>&1", $out, $ret);
	if ($ret == 0 && file_exists($tmp) && filesize($tmp) > 50000)
	{
	    @rename($tmp, $destination);
	    return (file_exists($destination) && filesize($destination) > 50000);
	}
    }
    @unlink($tmp);
    return (false);
}

function install_hls_javascript(&$warnings)
{
    $dir = "script/ext";
    $destination = $dir."/hls.min.js";
    if (file_exists($destination) && filesize($destination) > 50000)
	return (true);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true))
    {
	install_append_warning($warnings, "Cannot create ".$dir." to install hls.min.js.");
	return (false);
    }
    if (!is_writable($dir))
    {
	install_append_warning($warnings, "Cannot write ".$destination."; download hls.min.js manually into script/ext/.");
	return (false);
    }
    $url = "https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js";
    if (!install_download_file($url, $destination))
    {
	install_append_warning($warnings, "Cannot download hls.min.js from ".$url."; HLS playback will still fall back to direct MP4 when needed.");
	return (false);
    }
    @chmod($destination, 0644);
    return (true);
}

function install_hls_support(&$warnings)
{
    install_apache_hls_configuration($warnings);
    install_hls_javascript($warnings);
}

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
$InstallWarnings = [];
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
    install_hls_support($InstallWarnings);
}
    
if (isset($_POST["setup_hls"]) && file_exists("version.php"))
{
    if (($json = json_decode(file_get_contents("./database.json"), true)) != NULL)
    {
	if ($json["password"] == $_POST["password"])
	    install_hls_support($InstallWarnings);
	else
	    $ErrorMsg = "BadInstall";
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
		    <?php if ($ErrorMsg != "") { ?>
		        <div style="font-size: large; color: darkred; background: rgba(255, 255, 255, 0.70); margin: 20px auto; padding: 10px; width: 80%; text-align: left;">
			    <?=isset($Dictionnary[$ErrorMsg]) ? $Dictionnary[$ErrorMsg] : htmlspecialchars($ErrorMsg); ?>
			</div>
		    <?php } ?>
		    <?php if (!empty($InstallWarnings)) { ?>
		        <div style="font-size: large; color: #6b3f00; background: rgba(255, 255, 255, 0.70); margin: 20px auto; padding: 10px; width: 80%; text-align: left;">
			    <b>Installation warnings:</b><br />
			    <?php foreach ($InstallWarnings as $warning) { ?>
			        - <?=htmlspecialchars($warning); ?><br />
			    <?php } ?>
			</div>
		    <?php } ?>
		    <br />
		    <form method="post" action="install.php">
			<input type="password" name="password" placeholder="Database password" />
			<input type="submit" name="setup_hls" value="Install / refresh HLS support" />
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
