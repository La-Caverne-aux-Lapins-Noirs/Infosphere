<?php

$LogMsg = "";

if ($_POST["action"] == "add")
{
    require_once ("add_room.php");

    $request = @add_room($_POST["codename"], $_POST["capacity"],
			 $_FILES["map"]["tmp_name"],
			 $_FILES["conf"]["tmp_name"],
			 $_POST);
    $LogMsg = "RoomAdded";
}
else if ($_POST["action"] == "delete")
{
    if ($_POST["id"] == 1 || $_POST["id"] == "no_room")
	$request = new ErrorResponse("CannotDeleteNoRoom");
    else
	$request = @mark_as_deleted("room", $_POST["id"]);
    $LogMsg = "RoomDeleted";
}

