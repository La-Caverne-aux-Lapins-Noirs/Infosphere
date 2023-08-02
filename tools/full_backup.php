<?php

function full_backup()
{
    global $DatabaseFile;
    global $Configuration;

    if (!isset($DatabaseFile))
	return (false);
    if (($json = json_decode(file_get_contents($DatabaseFile), true)) == NULL)
	return (false);
    if (($host = $json["host"]) != "localhost" && $host != "127.0.0.1")
	$host = "-h $host";
    else
	$host = "";
    $id = now();
    $login = $json["login"];
    $dbname = $json["database"];
    $pass = $json["password"];

    $acc = $Configuration->Properties["handaccount"];
    $url = $Configuration->Properties["handurl"];
    $key = base64_decode($Configuration->Properties["handkey"]);
    $key = unsecure_data($key, $acc.$url."hand_request");
    $fifo = __DIR__."/../.sshkey";
    if (!file_exists($fifo))
    {
	file_put_contents($fifo, $key);
	system("chmod 600 $fifo");
    }

    /*
    $out = shell_exec("mysqldump $host -u '$login' --password='$pass' $dbname --ignore-table=$dbname.log > /tmp/backup$id.sql");
    if ($out === false || $out === NULL)
	return (false);
    
    $out = shell_exec("tar cvfz /tmp/backup$id.tar.gz dres/* /tmp/backup$id.sql");
    @unlink("/tmp/backup$id.sql");
    if ($out === false || $out === NULL)
	return (false);
    */

    // scp -o 'StrictHostKeyChecking no' -p 4422 -i /home/damdoshi/Infosphere/tools/../.sshkey tools/utils.php  infosphere_hand@nfs.efrits.fr:.
    shell_exec("scp -o 'StrictHosKeyChecking no' -p 4422 -i $fifo /tmp/backup$id.tar.gz $acc@$url:./");
    @unlink($fifo);
    @unlink("/tmp/backup$id.tar.gz");
    return (true);
}
