<?php

if (isset($_POST["activity"]) && (int)$_POST["activity"] != -1)
{
    if (isset($_POST["session"]))
	$session = $_POST["session"];
    else
	$session = -1;
    if (strstr($_POST["activity"], ";") == NULL && $_POST["activity"] != "*")
    {
	$activity = new FullActivity;
	if ($activity->build($_POST["activity"], true, false, $session) == false)
	{
	    $request = new ErrorResponse("NotAnId", $_POST["activity"]);
	    return ;
	}
	if ($activity->is_teacher == false)
	{
	    $request = new ErrorResponse("PermissionDenied");
	    return ;
	}
    }
}
else
    $activity = NULL;

if ($_POST["action"] == "enable" || $_POST["action"] == "disable")
{
    $request = @update_table("activity", $activity->id, ["enabled" => $enable]);
    $LogMsg = "ActivityModified";
    return ;
}

if ($_POST["action"] == "export_activity" && $export)
{
    $activities = [];
    $_POST["activity"] = trim($_POST["activity"]);
    if ($_POST["activity"] != "*" && $_POST["activity"] != "")
    {
	if (($request = split_symbols($_POST["activity"]))->is_error())
	    return ;
	foreach ($request->value as $id)
	{
	    $activity = new FullActivity;
	    if ($activity->build($id, false, true) == false)
		return ;
	    $activities[] = $activity;
	}
    }
    else
    {
	// On prend toutes les activités
	$request = new Response;
	if ($Position == "ActivitiesMenu")
	    $request = fetch_activity_template(-1, true);
	else
	    $request = fetch_activity(-1, true);
	foreach ($request->value as &$id)
	{
	    $activities[] = &$id;
	}
    }
    if ($_POST["format"] == "sketch")
    {
	$export_data = build_activity_sketch($activities);
	$export_format = "csv";
    }
    else if ($_POST["format"] == "detailed_sketch")
    {
	$export_data = build_activity_sketch($activities, true);
	$export_format = "csv";
    }
    else if ($_POST["action"] == "syllabus")
    {
	$export_data = build_syllabus($activities);
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

if ($_POST["action"] == "delete")
{
    $activity->build($activity->id, false, true); // On reconstruit l'objet recursivement
    $request = @mark_as_deleted("activity", $activity->id);
    foreach ($activity->subactivities as $sub)
    {
	@mark_as_deleted("activity", $sub->id);
	foreach ($sub->session as $ses)
	{
	    @mark_as_deleted("session", $ses->id, "");
	}
    }
    foreach ($activity->session as $ses)
    {
	@mark_as_deleted("session", $ses->id, "");
    }
    $LogMsg = "ActivityDeleted";
    return ;
}

if ($_POST["action"] == "undelete")
{
    $activity->build($activity->id, true, true); // On reconstruit l'objet recursivement
    $request = @unmark_as_deleted("activity", $activity->id);
    foreach ($activity->subactivities as $sub)
    {
	@unmark_as_deleted("activity", $sub->id);
	foreach ($sub->session as $ses)
	{
	    @unmark_as_deleted("session", $ses->id, "");
	}
    }
    foreach ($activity->session as $ses)
    {
	@unmark_as_deleted("session", $ses->id, "");
    }
    $LogMsg = "ActivityDeleted";
    return ;
}


if ($_POST["action"] == "manage_cycle")
{
    $request = @handle_links($activity->id, $_POST["cycle"], "activity", "cycle");
    $LogMsg = "ActivityModified";
    return ;
}

if ($_POST["action"] == "manage_class")
{
    if (!isset($_POST["subaction"]))
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    if ($_POST["subaction"] == "+")
    {
	if (($request = split_symbols($_POST["class"], ";", false))->is_error())
	    return ;
	foreach ($request->value as $v)
	{
	    if (substr($v, 0, 1) == '#') // Chapitre
		add_link($activity->id, substr($v, 1), "activity", "class", false, [], "activity_support");
	    else if (substr($v, 0, 1) == '$') // Autre activité
		add_link($activity->id, substr($v, 1), "activity", "subactivity_codename", false, [], "activity_support");
	    else // Asset
		add_link($activity->id, $v, "activity", "class_asset", false, [], "activity_support");
	}
    }
    else if ($_POST["subaction"] == "<")
	$request = @update_table("activity_support", (int)$_POST["support"],
				 ["number" => (int)$_POST["number"] - 1]);
    else if ($_POST["subaction"] == ">")
	$request = @update_table("activity_support", (int)$_POST["support"],
				 ["number" => (int)$_POST["number"] + 1]);
    else if ($_POST["subaction"] == "X") // id_activity pour double verifier
	$request = $Database->query("
           DELETE FROM activity_support
           WHERE id = {$_POST["class"]}
	   ");
    else
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $LogMsg = "ActivityModified";
    return ;
}

if ($_POST["action"] == "manage_teacher")
{
    $request = @add_teacher($activity->id, $_POST["teacher"]);
    $LogMsg = "ActivityModified";
    return ;
}

if ($_POST["action"] == "add_medal")
{
    $request = @handle_links($activity->id, $_POST["medal"], "activity", "medal");
    $LogMsg = "ActivityModified";
    return ;
}

if ($_POST["action"] == "remove_medal")
{
    $request = remove_link($activity->id, $_POST["medal"], "activity", "medal");
    $LogMsg = "MedalRemoved";
    return ;
}

if ($_POST["action"] == "medal_property")
{
    // Because javascript is shit
    $local = (int)$_POST["local"];
    $mandatory = (int)$_POST["mandatory"];
    $med = (int)$_POST["medal"];
    $grade_a = (int)$_POST["grade_a"];
    $grade_b = (int)$_POST["grade_b"];
    $grade_c = (int)$_POST["grade_c"];
    $bonus = (int)$_POST["bonus"];
    $Database->query("
	UPDATE activity_medal
        SET local = $local, mandatory = $mandatory, grade_a = $grade_a, grade_b = $grade_b, grade_c = $grade_c, bonus = $bonus
        WHERE id_medal = $med AND id_activity = {$activity->id}
    ");
    $LogMsg = "MedalEdited";
    return ;
}

if ($_POST["action"] == "add_activity")
{
    if (($request = resolve_codename("activity", $_POST["parent_activity"], "codename", true))->is_error())
	return ;
    $request = $request->value;
    if (substr($_POST["codename"], 0, 1) != "-" && $request["codename"] != "")
	$_POST["codename"] = $request["codename"]."-".$_POST["codename"];
    else
	$_POST["codename"] = $request["codename"].$_POST["codename"];
    $request = add_activity($_POST, $_FILES, $Position == "ActivitiesMenu");
    $LogMsg = "ActivityAdded";
    return ;
}

if ($_POST["action"] == "import_activities")
{
    // Si on est pas admin, c'est mort pour l'import
    if (!is_admin())
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    // Par defaut, ca ne fait que tester, il faut cocher pour être sur
    if (isset($_POST["notdry"]) && $_POST["notdry"] == "on")
	$dry = false;
    else
	$dry = true;

    $cnf = $_FILES["configuration"]["tmp_name"];
    if (($request = load_configuration($cnf, [], true))->is_error())
	return ;
    $cnf = $request->value;

    $request = import_activities($cnf, $Position == "ActivitiesMenu", $dry);
    if ($request[0] == false)
    {
	if ($dry == false)
	    $request = new ErrorResponse("ImportFailure", implode("\n", $request[1]));
	else
	{
	    if (count($request[1]) != 0)
		$request = new ErrorResponse("ImportFailure", implode("\n", $request[1]));
	    else
		$request = new Response;
	}
    }
    else
	$request = new Response;
    $LogMsg = "ImportDone";
    return ;
}

if ($_POST["action"] == "edit_activity")
{
    $request = edit_activity($_POST, $_FILES, $Position == "ActivitiesMenu");
    $LogMsg = "ActivityModified";
    return ;
}

if ($_POST["action"] == "add_session")
{
    if ($activity == NULL)
	return ;
    $request = add_session($_POST);
    $LogMsg = "SessionAdded";
}

if ($_POST["action"] == "edit_session")
{
    if ($activity == NULL)
	return ;
    $_POST["id_activity"] = $activity->id;
    if (!isset($_POST["id"]))
	$_POST["id"] = $activity->unique_session->id;
    $request = edit_session($_POST);
    $LogMsg = "SessionModified";
}

if ($_POST["action"] == "delete_session")
{
    $request = @mark_as_deleted("session", $activity->unique_session->id, "");
    $LogMsg = "SessionDeleted";
    return ;
}

if ($_POST["action"] == "undelete_session")
{
    $request = @unmark_as_deleted("session", $activity->unique_session->id, "");
    $LogMsg = "SessionRestored";
    return ;
}

if ($_POST["action"] == "add_room")
{
    if ($activity == NULL)
	return ;
    $_POST["id_activity"] = $activity->id;
    $request = @handle_links($activity->unique_session->id, $_POST["room"], "session", "room");
    $LogMsg = "SessionModified";
    return ;
}

if ($_POST["action"] == "move_instance")
{
    if ($_POST["codename"] == "up")
    {
	// On a besoin du parent du parent pour monter
	$parent = new FullActivity;
	if ($parent->build((int)$_POST["parent"], false, false) == false)
	{
	    $request = new ErrorResponse("NotAnId", $_POST["parent"]);
	    return ;
	}
	$_POST["target"] = $parent->parent_activity;
    }
    // On determine le parent comme etant le paramètre
    if (($request = resolve_codename("activity", $_POST["codename"]))->is_error())
	return ;
    $request = $request->value;
    $request = update_table("activity", $activity->id, ["parent_activity" => $request]);
    $LogMsg = "ActivityModified";
    return ;
}

if ($_POST["action"] == "instantiate")
{
    if ($Position != "ActivitiesMenu") // On intantie que des templates
	return ;
    $startdate = date_to_timestamp($_POST["start_date"]);
    if (isset($_POST["parent"]))
	$parent = $_POST["parent"];
    else
	$parent = -1;
    if (isset($_POST["suffix"]))
	$suffix = $_POST["suffix"];
    else
	$suffix = "";
    $id = $activity->id;
    $activity = new FullActivity;
    if ($activity->build($id, false, true, $session) == false)
	return ;
    $request = instantiate_template($activity, $startdate, $suffix, $parent);
    return ;
}

if ($_POST["action"] == "rename")
{
    $request = edit_codename("activity", $activity->id, $_POST["codename"]);
    $LogMsg = "Edited";
    return ;
}

if ($_POST["action"] == "copy")
{
    if ($Position == "InstancesMenu")
	return ;
    $id = $activity->id;
    $activity = new FullActivity;
    if ($activity->build($id, false, true, $session) == false)
	return ;
    $request = copy_template($activity, $_POST["codename"]);
    $LogMsg = "Copied";
    return ;
}

if ($_POST["action"] == "full_break")
{
    if ($Position != "InstancesMenu") // On ne casse le lien que des instances
	return ;
    $request = break_template_link($activity);
    $LogMsg = "LinkBroken";
}

if ($_POST["action"] == "move_left" || $_POST["action"] == "move_right")
{
    if ($Position != "InstancesMenu") // On ne deplace que des instances
	return ;
    $move = 60 * 60 * 24 * 7;
    if ($_POST["action"] == "move_left")
	$move = -$move;
    $request = move_activity_date($_POST["activity"], $move);
    $LogMsg = "InstanceMoved";
}

// Permet d'éditer ce qu'on veut tant qu'on a le format "bang_nom_du_champ"
if (strncmp($_POST["action"], "bang_", 5) == 0)
{
    $field = substr($_POST["action"], 5);
    if (in_array($field, ["id"])) // Pour limiter les betises.
    {
	$request = new ErrorResponse("PermissionDenied");
	return ;
    }
    $request = update_table("activity", $activity->id, [$field => $_POST["value"]]);
    $LogMsg = "ActivityModified";
    return ;
}

