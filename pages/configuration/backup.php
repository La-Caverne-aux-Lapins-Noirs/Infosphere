<?php ////////////// BACKUP

/*
if (!($json = json_decode(file_get_contents($DatabaseFile), true)))
    return ;
$cdate = str_replace(":", "_", db_form_date(now()));
$dir = "/tmp/infosphere_$cdate";
$file = "$dir/backup_$cdate.tar.gz";
print_r(shell_exec($x = "mkdir $dir && realpath /tmp/"));
$out = shell_exec(
    "mysqldump ".
    "-u '".$json["login"]."' ".
    "-p '".$json["password"]."' ".
    "--single-transaction ".
    "> $dir/database.sql || echo 'Failed'"
);
if (substr($out, 0, 6) != "Failed")
{
    shell_exec("tar cvfz $file $dir/database.sql dres/*");
    $outfile = file_get_contents($file);
}
unset ($json);
*/

