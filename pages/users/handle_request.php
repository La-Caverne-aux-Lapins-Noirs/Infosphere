<?php

if ($_POST["action"] == "set_status")
{
    $request = @set_user_data($_POST["user"], ["authority" => $_POST["authority"]]);
    $LogMsg = "UserModified";
}
else if ($_POST["action"] == "delete")
{
    $request = @set_user_data($_POST["user"], ["authority" => BANISHED]);
    $LogMsg = "UserBanned";
}
else if ($_POST["action"] == "add_user")
{
    if (strlen($_POST["login"]) && strlen($_POST["mail"]))
    {
	// A terme, il faudra changer le type de retour de subscribe vers XReponse
	if (($request = @subscribe($_POST["login"], $_POST["mail"], NULL, false))["Error"] != "")
	    $request = new ErrorResponse($request["Error"]);
	else
	    $request = new ValueResponse($request["User"]);
    }
    if (isset($_FILES["configuration"]["tmp_name"]) && $_FILES["configuration"]["tmp_name"] != "")
    {
	$new_name = "./dres/trash/".pathinfo($_FILES["configuration"]["tmp_name"], PATHINFO_FILENAME);
	$new_name .= ".".pathinfo($_FILES["configuration"]["name"], PATHINFO_EXTENSION);
	mupload_file($_FILES["configuration"]["tmp_name"], $new_name);
	if (!($request = load_configuration($new_name, ["login", "mail", "authority" => false, "first_name" => false, "family_name" => false, "birth_date" => false, "phone" => false]))->is_error())
	{
	    foreach ($request->value as $nod)
	    {
		if (($request = @subscribe($nod["login"], $nod["mail"], NULL, false))["Error"] != "")
		{
		    $request = new ErrorResponse($request["Error"], $nod["login"]." ".$nod["mail"]);
		    break ;
		}
		else
		    $request = new ValueResponse($request["User"]);
		if (isset($nod["authority"]) && $nod["authority"] != NULL && $nod["authority"] <= 2) // "Student"
		    $request = @set_user_data($nod["login"], [
			"authority" => $nod["authority"],
			"first_name" => strtolower($nod["first_name"]),
			"family_name" => strtolower($nod["family_name"]),
			"birth_date" => $nod["birth_date"],
			"phone" => $nod["phone"]
		    ]);
	    }
	}
	unlink($new_name);
    }
    $LogMsg = "UserAdded";
}
else if ($_POST["action"] == "new_password")
{
    $_POST["user"] = (int)$_POST["user"];
    $request = set_user_attributes(["id" => $_POST["user"]], ["password" => generate_password()]);
    if ($request["Error"] != "")
	$request = new ErrorResponse($request["Error"]);
    else
	$request = new Response;
    $LogMsg = "PasswordEdited";
}
else if ($_POST["action"] == "add_children")
{
    $_POST["user"] = (int)$_POST["user"];
    $request = handle_links($_POST["user"], $_POST["parent_of"], "user", "user", true, "parent_child", false, "parent", "child");
    $LogMsg = "ChildEdited";
}
