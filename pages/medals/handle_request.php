<?php

// Cette action peut etre réalisée par n'importe quel content author
if ($_POST["action"] == "add")
{
    $fields = [];
    if (@strlen($_POST["tags"]))
    {
	if (!($request = split_symbols($_POST["tags"], ";", false))->is_error())
	    $fields["tags"] = implode(";", $request->value);
    }
    if (@strlen($_POST["type"]))
    {
	if (($fields["type"] = $_POST["type"]) < 0 || $fields["type"] > 2)
	    unset($fields["type"]);
    }
    if (strlen(@$_POST["name"]) == 0)
    {
	$ret = forge_language_fields(["name"]);
	foreach ($ret as $r)
	{
	    $_POST[$r] = $_POST["medal"];
	}
    }
    if ($fields != [])
	$request = @try_insert(
	    "medal", $_POST["medal"],
	    $fields,
	    $_FILES["icon"]["tmp_name"], $Configuration->MedalsDir,
	    // Language
	    ["name", "description" => false], $_POST
	);
    $LogMsg = "MedalAdded";
}
else if ($_POST["action"] == "edit_icon")
{
    if (($med = db_select_one("codename FROM medal WHERE id = ".((int)$_POST["medal"]))) == NULL)
	$request = new ErrorResponse("NotAnId");
    else
    {
	$icon_file = $Configuration->MedalsDir.$med["codename"].".png";
	$request = upload_png($_FILES["icon"]["tmp_name"], $icon_file, [100, 100], MINIMUM_PICTURE_SIZE);
	if (!$request->is_error())
	    $Database->query("UPDATE medal SET icon = '$icon_file' WHERE id = ".((int)$_POST["medal"]));
	$LogMsg = "MedalEdited";
    }
}
else if ($_POST["action"] == "medal_medal")
{
    $request = handle_links(
	$_POST["medal"], $_POST["medals"],
	"medal", "medal",
	false, "medal_medal",
	false, "medal",
	"implied_medal",
	[]
    );
    $LogMsg = "MedalEdited";
}
else if ($_POST["action"] == "medal_medal_properties")
{
    $med = (int)$_POST["medal_medal"];
    $props = [];
    foreach ($_POST as $k => $prop)
    {
	if (!in_array($k, ["nbr"]))
	    continue ;
	$props[] =
	    $Database->real_escape_string($k)." = '".
	    $Database->real_escape_string($prop)."'"
	;
    }
    $props = implode(", ", $props);
    if ($Database->query("UPDATE medal_medal SET $props WHERE id = $med") == NULL)
	$request = new ErrorResponse("CannotUpdate");
    $LogMsg = "MedalEdited";
}

// Ces actions néccessite d'être le créateur de la médaille, de l'activité... ou un administrateur

$medal = $_POST["medal"];
if (($request = resolve_codename("medal", $_POST["medal"], "codename", true))->is_error())
    return ;
$medal = $request->value;
$medal_auth = is_admin();

if (isset($_POST["activity"]))
{
    if (($request = resolve_codename("activity", $_POST["activity"], "codename", true))->is_error())
	return ;
    $activity = $request->value;
    $activity_auth = is_admin() || have_rights($activity["id"]);
}

if ($_POST["action"] == "update")
{
    if ($medal_auth)
    {
	$fields = [];
	if (@strlen($_POST["tags"]))
	{
	    if (!($request = split_symbols($_POST["tags"], ";", false))->is_error())
		$fields["tags"] = implode(";", $request->value);
	}
	if (@strlen($_POST["type"]))
	{
	    if (($fields["type"] = $_POST["type"]) < 0 || $fields["type"] > 2)
		unset($fields["type"]);
	}
	// Cette action n'a actuellement aucun formulaire associé.
	if ($fields != NULL)
	    $request = @try_update(
		"medal", $_POST["medal"],
		$fields,
		$_FILES["icon"]["tmp_name"], $Configuration->MedalsDir,
		// Language
		["name", "description"], $_POST
	    );
	$LogMsg = "MedalUpdated";
    }
    else
	$request = new ErrorResponse("PermissionDenied");
}
else if ($_POST["action"] == "link")
{
    if ($activity_auth)
    {
	$request = @add_links($_POST["activity"], $_POST["medal"], "activity", "medal");
	$LogMsg = "LinkEstablished";
    }
    else
	$request = new ErrorResponse("PermissionDenied");
}
else if ($_POST["action"] == "unlink")
{
    if ($activity_auth)
    {
	$request = @remove_links($_POST["activity"], $_POST["medal"], "activity", "medal");
	$LogMsg = "LinkRemoved";
    }
    else
	$request = new ErrorResponse("PermissionDenied");
}
else if ($_POST["action"] == "delete")
{
    if ($medal_auth)
    {
	$request = @mark_as_deleted("medal", $_POST["medal"]);
	$LogMsg = "MedalDeleted";
    }
    else
	$request = new ErrorResponse("PermissionDenied");
}

