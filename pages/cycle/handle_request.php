<?php

$LogMsg = "";

if ($_POST["action"] == "add_cycle")
{
    require_once ("add_cycle.php");

    $request = @add_cycle($_POST["name"], $_POST["year"], $_POST["first_week"]);
    $LogMsg = "SchoolYearAdded";
}
else if ($_POST["action"] == "delete_cycle")
{
    $request = @mark_as_deleted("cycle", $_POST["cycle"]);
    $LogMsg = "SchoolYearRemoved";
}
else if ($_POST["action"] == "add_user")
{
    $request = @add_links($_POST["login"], $_POST["cycle"], "user", "cycle");
    $LogMsg = "Success";
}
else if ($_POST["action"] == "remove_user")
{
    $request = @remove_links($_POST["user"], $_POST["cycle"], "user", "cycle");
    $LogMsg = "UserRemoved";
}
else if ($_POST["action"] == "add_activity")
{
    $request = @add_links($_POST["activity"], $_POST["cycle"], "activity", "cycle");
    $LogMsg = "Success";
}
else if ($_POST["action"] == "remove_activity")
{
    $request = @remove_links($_POST["activity"], $_POST["cycle"], "activity", "cycle");
    $LogMsg = "UserRemoved";
}
else if ($_POST["action"] == "cycle_done")
{
    $id = (int)$_POST["cycle"];
    $done = (int)$_POST["done"];
    $Database->query("UPDATE cycle SET done = $done WHERE id = $id");
}
else if ($_POST["action"] == "subscribe")
{
    $request = @subscribe_to_instance($_POST["activity"], $_POST["logins"], -1, true);
    $LogMsg = new InfoResponse("Subscribed", $_POST["logins"]);
}
else if ($_POST["action"] == "unsubscribe")
{
    $request = @unsubscribe_from_instance($_POST["activity"], $_POST["logins"], true);
    $LogMsg = new InfoResponse("Unsubscribed", $_POST["logins"]);
}
else if ($_POST["action"] == "export_activity" && $export)
{
    if ($_POST["format"] == "sketch")
    {
	$export_data = build_cycle_sketch($_POST["cycle"]);
	$export_format = "csv";
    }
    else if ($_POST["format"] == "detailed_sketch")
    {
	$export_data = build_cycle_sketch($_POST["cycle"], true);
	$export_format = "csv";
    }
    else if ($_POST["action"] == "syllabus")
    {
	$export_data = build_syllabus($_POST["cycle"]);
	$export_format = "json";
    }
    else
	return ;
    if (count($activities) > 1)
	$export_filename = $_POST["format"];
    else
	$export_filename = $activity->codename;
    return ;
}
else if ($_POST["action"] == "export_cycle" && is_admin())
{
    $_POST["data"] = export_cycle($_POST["data"]);
    require_once (__DIR__."/../../export.php");
}
else if ($_POST["action"] == "cycle_teacher")
{
    $user = $_POST["user"];
    $cycle = (int)$_POST["id_cycle"];
    $request = @add_teacher($cycle, $user, "cycle");
    $LogMsg = "CycleModified";
    return ;
}
