<?php

$request = "";
if ($User != NULL && $User["authority"] >= STUDENT && isset($_POST["action"]) && $_POST["action"] == "fill_token")
{
    require_once ("enter_token.php");

    if (($request = enter_token($_POST["code"])) != "")
	$ErrorMsg = $Dictionnary[$request]."<br />";
    else
	$LogMsg = "TokenEntered";
}
