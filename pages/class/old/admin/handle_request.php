<?php

if ($_POST["action"] == "add")
{
    $request = @try_insert("class", $_POST["codename"], [], "", "", ["name", "description"], $_POST);
    $LogMsg = "ClassAdded";
}
else if ($_POST["action"] == "delete")
{
    $request = @mark_as_deleted("class", $_POST["id"]);
    $LogMsg = "ClassDeleted";
}
else if ($_POST["action"] == "add_asset")
{
    require_once ("add_asset.php");

    $request = @add_asset(@$_POST["id_class"], @$_POST["codename"], @$_POST["chapter"], @$_FILES, @$_POST);
    $LogMsg = "FileAdded";
}
else if($_POST["action"] == "remove")
{
    $request = @mark_as_deleted("class_asset", $_POST["id"], "");
    $LogMsg = "FileDeleted";
}
else if($_POST["action"] == "superremove" && is_admin())
{
    require_once ("full_delete_asset.php");

    $request = @full_delete_asset($_POST["id"]);
    $LogMsg = "FileDeleted";
}
