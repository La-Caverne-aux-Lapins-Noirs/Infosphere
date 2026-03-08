<?php

if (isset($Database))
    return ;

if (!isset($DatabaseFile))
    $DatabaseFile = "./database.json";

if (!UNIT_TEST)
{
    if (($json = json_decode(file_get_contents($DatabaseFile), true)) == NULL)
	exit ;
    $Database = new Database($json["host"], $json["login"], $json["password"], $json["database"], true);
    $Secured = $json["secured"];
}
else
{
    $Database = new Database("", "", "", "", true, "./database.sql");
    $Secured = "default";
}

$DBSelect = new DBSelect;

/**
 * @codeCoverageIgnore
 */
function destroy_db()
{
    global $Database;

    unset($Database);
}
register_shutdown_function("destroy_db");

