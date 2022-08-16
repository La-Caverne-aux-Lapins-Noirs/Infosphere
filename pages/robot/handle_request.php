<?php

if (is_admin() && isset($_POST["action"]))
{
    if ($_POST["action"] == "add")
    {
	require_once ("add_robot.php");

	$msg = @add_robot($_POST["codename"], $_POST["version"], $_FILES["file"]);
	$LogMsg = "RobotAdded";
    }
    else if($_POST["action"] == "remove")
    {
	require_once ("delete.php");

	$msg = @delete($_POST["id"]);
	$LogMsg = "RobotDeleted";
    }
    else if($_POST["action"] == "superremove")
    {
	require_once ("delete.php");

	$msg = @full_delete($_POST["id"]);
	$LogMsg = "RobotDeleted";
    }
    else if($_POST["action"] == "reset")
    {
	require_once ("delete.php");

	$msg = @reset_complaint($_POST["id"]);
	$LogMsg = "ComplaintReseted";
    }

    if ($msg != "")
    {
	$LogMsg = "";
	$ErrorMsg = $msg;
    }
    else
	$_POST = [];
}
