<?php

if (is_admin() && isset($_POST["action"]))
{
    if ($_POST["action"] == "add_category") {
	require_once ("add_category_script.php");

	$msg = @add_category($_POST["codename"], $_POST["rate"], $_POST["select_rate"], $_POST);
	$LogMsg = "CategoryAdded";
    }
    else if ($_POST["action"] == "import_category") {
	require_once ("add_category_script.php");
	require_once ("fetch_activities.php");

	$cat = fetch_activities("-1", $_POST["activity"]);
	$ext = array_key_first($cat);
	$msg = @add_category($ext, $_POST["import_rate"], $_POST["select_rate"], $cat[$ext]);
	$LogMsg = "CategoryAdded";
    }
    else if ($_POST["action"] == "add_endpoint") {
	require_once ("add_endpoint_script.php");

	$msg = @add_endpoint($_POST["id"], $_POST["codename"], $_POST["range"]);
	$LogMsg = "EndpointAdded";
    }

    if ($msg != "")
    {
	$LogMsg = "";
	$ErrorMsg = $msg;
    }
    else
	$_POST = [];
}
