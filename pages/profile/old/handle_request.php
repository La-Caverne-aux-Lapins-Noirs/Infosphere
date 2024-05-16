<?php

if ($ParentConnexion)
{
    $request = new ErrorResponse("ParentsCantModify");
    return ;
}

if ($_POST["action"] == "update")
{
    unset($_POST["action"]);
    if (!is_admin() && $User["id"] != $_POST["id"])
	$request = new ErrorResponse("PermissionDenied");
    else
    {
	if (!strlen($_POST["birth_date"]))
	    unset($_POST["birth_date"]);
	if (strlen(@$_FILES["avatar"]["tmp_name"]))
	{
	    $_POST["avatar"] = $Configuration->AvatarsDir.sprintf("%08d", $_POST["id"]).".png";
	    if (($request = upload_png($_FILES["avatar"]["tmp_name"], $_POST["avatar"], [500, 500], MAXIMUM_PICTURE_SIZE))->is_error())
		return ;
	}
	if (strlen(@$_FILES["photo"]["tmp_name"]) && is_admin())
	{
	    $_POST["photo"] = $Configuration->AvatarsDir.sprintf("%08d", $_POST["id"])."_photo.png";
	    if (($request = upload_png($_FILES["photo"]["tmp_name"], $_POST["photo"], [500, 500], MAXIMUM_PICTURE_SIZE))->is_error())
		return ;
	}
	$request = set_user_data($_POST["id"], $_POST, $conf_fields);
    }
    $LogMsg = "ProfileUpdated";
}
else if ($_POST["action"] == "pass_update")
{
    if ($_POST["new_pass"] != $_POST["repeat_pass"])
	$request = new ErrorResponse("PasswordDoesNotMatch");
    else if (!is_admin() && $User["id"] != $_POST["id"])
	$request = new ErrorResponse("PermissionDenied");
    else
	$request = regenerate_password(["id" => $_POST["id"]], $_POST["new_pass"]);
    $LogMsg = "ProfileUpdated";
}
else if ($_POST["action"] == "add_medal")
{
    $request = edit_medal($_POST["user"], $_POST["medal"], $_POST["activity"]);
    $LogMsg = "MedalAdded";
}
else if ($_POST["action"] == "refresh_profile")
{
    if (is_admin())
    {
	$refresh = true;
	$LogMsg = "ProfileRefreshed";
    }
}
else if ($_POST["action"] == "cycle_comment")
{
    $iduser = $User["id"];
    $idcycle = (int)$_POST["id_cycle"];
    $idusercycle = (int)$_POST["id_user_cycle"];
    $comment = $Database->real_escape_string($_POST["cycle_comment"]);
    $sel = db_select_one("* FROM user_cycle WHERE id_cycle = $idcycle AND id = $idusercycle");
    if ($sel == NULL)
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    if (!is_admin()) // Si on est pas admin, on vérifie qu'on a le droit de faire ce qu'on fait.
	$sel = db_select_one("
  	  cycle_teacher.id FROM cycle_teacher
  	  LEFT JOIN laboratory ON cycle_teacher.id_laboratory = laboratory.id
	  LEFT JOIN user_laboratory ON laboratory.id = user_laboratory.id_laboratory
	  WHERE cycle_teacher.id_cycle = $idcycle
          AND cycle_teacher.id_user = $iduser
	  OR (user_laboratory.id_user = $iduser AND user_laboratory.authority >= 2)
	");
    else
	$sel = true;
    if ($sel == NULL)
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $Database->query("
	UPDATE user_cycle
	SET commentaries = '$comment'
	WHERE id = $idusercycle
    ");
    add_log(TRACE, "Set a commentary for user cycle $idusercycle.");
    $LogMsg = "CommentAdded";
    return ;
}
else if ($_POST["action"] == "module_comment")
{
    $iduser = $User["id"];
    $idmodule = (int)$_POST["id_module"];
    $idteam = (int)$_POST["id_team"];
    $comment = $Database->real_escape_string($_POST["module_comment"]);
    $sel = db_select_one("* FROM team WHERE id = $idteam AND id_activity = $idmodule");
    if ($sel == NULL)
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    if (!is_admin()) // Si on est pas admin, on vérifie qu'on a le droit de faire ce qu'on fait.
	$sel = db_select_one("
  	  activity_teacher.id FROM activity_teacher
  	  LEFT JOIN laboratory ON activity_teacher.id_laboratory = laboratory.id
	  LEFT JOIN user_laboratory ON laboratory.id = user_laboratory.id_laboratory
	  WHERE activity_teacher.id_activity = $idmodule
          AND activity_teacher.id_user = $iduser
	  OR (user_laboratory.id_user = $iduser AND user_laboratory.authority >= 2)
	");
    else
	$sel = true;
    if ($sel == NULL)
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $Database->query("
	UPDATE team
	SET commentaries = '$comment'
	WHERE id = $idteam
    ");
    add_log(TRACE, "Set a commentary for team $idteam.");
    $LogMsg = "CommentAdded";
    return ;
}

