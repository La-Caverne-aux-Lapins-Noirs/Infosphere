<?php

$LogMsg = "";

if ($_POST["action"] == "add_laboratory")
{
    if (!am_i_director())
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $request = @try_insert(
	"laboratory", $_POST["codename"],
	[], // No additional fields
	$_FILES["icon"]["tmp_name"], $Configuration->GroupsDir,
	["name" => false, "description" => false], $_POST
    );
    $LogMsg = "GroupAdded";
}
else if ($_POST["action"] == "delete_laboratory")
{
    if (count($fetch = @fetch_laboratory($_POST["laboratory"], false)) == 0)
    {
	$request = new ErrorResponse("NotFound");
	return ;
    }
    if (!am_i_director_of($fetch["school"]) || count($fetch["school"]) > 1)
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $request = @mark_as_deleted("laboratory", $_POST["laboratory"]);
    $LogMsg = "GroupDeleted";
}

else if ($_POST["action"] == "add_group_member")
{
    if (($request = resolve_codename("user", $_POST["user"]))->is_error())
	return ;
    $user = $request->value;
    
    if (count($fetch = @fetch_laboratory($_POST["laboratory"], false)) == 0)
    {
	$request = new ErrorResponse("NotFound");
	return ;
    }

    if (!((count($fetch["user"]) == 0 && am_i_director_of($fetch["school"]))
       || is_group_admin($_POST["laboratory"])))
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $props = [];
    if (count($fetch["user"]) == 0)
	$properties = ["authority" => 3];
    if (($request = handle_linksf([
	"left_value" => $user,
	"right_value" => $_POST["laboratory"],
	"left_field_name" => "user",
	"right_field_name" => "laboratory",
	"properties" => $properties
    ]))->is_error())
          return ;
    $LogMsg = "MemberAdded";
}
else if ($_POST["action"] == "remove_member")
{
    if (count($fetch = @fetch_laboratory($_POST["laboratory"], false)) == 0)
    {
	$request = new ErrorResponse("NotFound");
	return ;
    }

    if (!is_group_admin($_POST["laboratory"]) && !(
	count($fetch["user"]) == 1 && am_i_director_of($fetch["school"])))
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $request = @remove_links($_POST["user"], $_POST["laboratory"], "user", "laboratory");
    $LogMsg = "MembersRemoved";
}
else if ($_POST["action"] == "edit_member_authority")
{
    $lab = (int)$_POST["laboratory"];
    $user = (int)$_POST["user"];
    $auth = (int)$_POST["authority"];
    if (!is_group_admin($lab) || $auth < 0 || $auth > 3)
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $Database->query("
       UPDATE user_laboratory
       SET authority = $auth
       WHERE id_user = $user AND id_laboratory = $lab
    ");
    $LogMsg = "ProfileUpdated";
}

else if ($_POST["action"] == "add_school")
{
    $lab = (int)$_POST["laboratory"];
    if (($request = resolve_codename("school", $_POST["school"]))->is_error())
	return ;
    $school = $request->value;
    if (!am_i_director())
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    if (($request = handle_links($school, $lab, "school", "laboratory"))->is_error())
	return ;
    $LogMsg = "SchoolAdded";
}
