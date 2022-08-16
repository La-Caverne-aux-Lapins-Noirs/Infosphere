<?php

if ($_POST["action"] == "add_school")
{
    $request = @try_insert_array([
	"table" => "school",
	"codename" => $_POST["codename"],
	"icon" => $_FILES["icon"]["tmp_name"],
	"icon_dir" => $Configuration->SchoolsDir,
	"language_fields" => ["name"],
	"language" => $_POST
    ]);
    $LogMsg = "Added";
}
else if ($_POST["action"] == "link_school_director" || $_POST["action"] == "link_school_student")
{
    $authority = $_POST["action"] == "link_school_director";
    $request = handle_linksf([
	"left_value" => @$_POST["user"],
	"right_value" => $_POST["school"],
	"left_field_name" => "user",
	"right_field_name" => "school",
	"properties" => ["authority" => $authority]
    ]);
    $LogMsg = "Edited";
}
else if ($_POST["action"] == "delete_school")
{
    $request = @mark_as_deleted("school", $_POST["school"]);
    $LogMsg = "Deleted";
}
else
{
    foreach (["room", "cycle", "laboratory"] as $lnk)
    {
	if ($_POST["action"] == "edit_school_$lnk")
	{
	    $request = handle_linksf([
		"left_value" => @$_POST["school"],
		"right_value" => @$_POST[$lnk],
		"left_field_name" => "school",
		"right_field_name" => $lnk
	    ]);
	    $LogMsg = "Edited";
	    break ;
	}
    }
}
