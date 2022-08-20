<?php

$LogMsg = "";

if ($_POST["action"] == "add_laboratory")
{
    if (!is_admin())
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
else if ($_POST["action"] == "delete_group")
{
    if (!is_admin())
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $request = @mark_as_deleted("laboratory", $_POST["laboratory"]);
    $LogMsg = "GroupDeleted";
}
else if ($_POST["action"] == "remove_member")
{
    if (!is_group_admin($_POST["laboratory"]))
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $request = @remove_links($_POST["user"], $_POST["laboratory"], "user", "laboratory");
    $LogMsg = "MembersRemoved";
}
else if ($_POST["action"] == "add_group_member")
{
    $lab = (int)$_POST["laboratory"];
    if (($request = resolve_codename("user", $_POST["user"]))->is_error())
	return ;
    $user = $request->value;
    if (!is_group_admin($lab))
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    if (($request = handle_links($user, $lab, "user", "laboratory"))->is_error())
	return ;
    $LogMsg = "MemberAdded";
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
    if (!is_admin())
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    if (($request = handle_links($school, $lab, "school", "laboratory"))->is_error())
	return ;
    $LogMsg = "SchoolAdded";
}
