<?php

if ($ParentConnexion)
{
    $request = new ErrorResponse("ParentsCantModify");
    return ;
}

if ($User != NULL && isset($_POST["action"]))
{
    $request = new ValueResponse("");

    //////////////////////////
    // Je veux m'inscrire moi
    if ($_POST["action"] == "subscribe")
    {
	$request = @subscribe_to_instance($activity);
	$LogMsg = "YouHaveSubscribed";
    }
    //////////////////////////////
    // Je veux me desinscrire moi
    else if ($_POST["action"] == "unsubscribe")
    {
	$request = @unsubscribe_from_instance($activity);
	$LogMsg = "YouHaveUnsubscribed";
    }
    ///////////////////////////////////////////////////////////////////////////////
    // Je veux inscrire un élève dans une equipe seul ou dans une equipe existante
    else if ($_POST["action"] == "force_subscribe" || $_POST["action"] == "force_subscribe_team")
    {
	if (!isset($_POST["logins"]))
	    $request = new ErrorResponse("MissingLogins");
	else if (!$activity->is_teacher)
	{
	    $_POST["logins"] = $Database->real_escape_string($_POST["logins"]);
	    $request = new ErrorResponse("PermissionDenied");
	    @add_log(REPORT, "I have tried to subscribe students '".$_POST["logins"]."' and I am not a teacher.");
	}
	else
	{
	    $request = @subscribe_to_instance($activity, $_POST["logins"], $_POST["team"], true);
	    $LogMsg = new InfoResponse("Subscribed", $_POST["logins"]);
	}
    }
    ////////////////////////////////
    // Je veux desinscrire un élève
    else if ($_POST["action"] == "force_unsubscribe")
    {
	if (!isset($_POST["logins"]))
	    $request = new ErrorResponse("MissingLogins");
	else if (!$activity->is_teacher)
	{
	    $_POST["logins"] = $Database->real_escape_string($_POST["logins"]);
	    $request = new ErrorResponse("PermissionDenied");
	    @add_log(REPORT, "I have tried to unsubscribe students '".$_POST["logins"]."' and I am not a teacher.");
	}
	else
	{
	    $request = @unsubscribe_from_instance($activity, $_POST["logins"], true);
	    $LogMsg = new InfoResponse("Unsubscribed", $_POST["logins"]);
	}
    }
    else if ($_POST["action"] == "generate_slots")
    {
	if (!isset($_POST["duration"]))
	    $request = new ErrorResponse("MissingDuration");
	else if (!$activity->is_teacher)
	{
	    $request = ErrorResponse("PermissionDenied");
	    @add_log(REPORT, "I have tried to generate appointment slots and I am not a teacher");
	}
	else
	{
	    if (!is_number($_POST["simultaneous"]))
		$_POST["simultaneous"] = 1;
	    $request = @generate_slots($session, $_POST["duration"], $_POST["simultaneous"]);
	    $LogMsg = "SlotsGenerated";
	}
    }
    else if ($_POST["action"] == "add_simultaneous_slots")
    {
	// Ajoute pour chaque créneau horaire un slot supplémentaire
	$request = add_slot_layer($session);
	$LogMsg = "SlotsGenerated";
    }
    else if ($_POST["action"] == "add_teacher")
    {
	///////////////////////////////////////////////////////////////////////////////
	// Je suis un prof, je veux ajouter des professeurs responsable sur l'activité
	if (!$activity->is_teacher)
	{
	    $request = new ErrorResponse("PermissionDenied");
	    @add_log(REPORT, "I have tried to add teachers and I am not a teacher.");
	}
	else
	{
	    if ($session != NULL)
		@$request = add_teacher($session["id_session"], $_POST["logins"]);
	    else
		@$request = add_teacher($instance["id_instance"], $_POST["logins"], "instance");
	    $LogMsg = "TeachersAdded";
	}
    }
    else if ($_POST["action"] == "remove_teacher")
    {
	///////////////////////////////////////////////////////////////////////////////
	// Je suis un prof, je veux ajouter des professeurs responsable sur l'activité
	if (!$activity->is_teacher)
	{
	    $request = new ErrorResponse("PermissionDenied");
	    @add_log(REPORT, "I have tried to remove teachers and I am not a teacher.");
	}
	else if (!isset($_POST["type"]) || ($_POST["type"] != "laboratory" && $_POST["type"] != "user"))
	    $request = new ErrorResponse("InvalidParameter");
	else
	{
	    require_once ("remove_teacher.php");

	    @$request = remove_teacher($session, $_POST["type"], $_POST["id"], $_POST["activity"]);
	    @$request = remove_teacher($session, $_POST["type"], $_POST["id"], $_POST["activity"]);
	    $LogMsg = "TeacherRemoved";
	}
    }
    else if ($_POST["action"] == "add_medal")
    {
	////////////////////////////////////////////////////////////////////////////////////////
	// Je suis prof, je veux forcer une médaille pour un(e) élève - et debugger l'intranet.
	if ($activity->reference_activity != -1)
	    @$request = add_medal($_POST["id"], $_POST["medal"], $activity->reference_activity);
	else
	    @$request = add_medal($_POST["id"], $_POST["medal"], $activity->id);
	$LogMsg = "MedalAdded";
    }
    else if ($_POST["action"] == "remove_medal")
    {
	//////////////////////////////////////////////////////////////////////////////////
	// Je suis prof, je veux VIRER une medaille donnée a un eleve lors de mon activité
	if (!$activity->is_teacher || !is_number($_POST["id"]))
	{
	    $_POST["id"] = $Database->real_escape_string($_POST["id"]);
	    $request = new ErrorResponse("PermissionDenied");
	    @add_log(REPORT, "I have tried to remove a medal to '".$_POST["id"]."' and I am not a teacher.");
	}
	else
	{
	    $Database->query("DELETE FROM activity_user_medal WHERE id = ".$_POST["id"]);
	    $LogMsg = "MedalRemoved";
	}
    }
    else if ($_POST["action"] == "user_team_comment")
    {
	////////////////////////////////////////////////////////////
	// Je suis prof, je veux ecrire un commentaire sur un eleve
	$_POST["commentaries"] = $Database->real_escape_string($_POST["commentaries"]);
	$_POST["user"] = (int)$_POST["user"];
	$_POST["team"] = (int)$_POST["team"];
	$Database->query("
           UPDATE user_team
           SET commentaries = '".$_POST["commentaries"]."'
           WHERE id_team = ".$_POST["team"]." AND id_user = ".$_POST["user"]."
	   ");
	$LogMsg = "CommentAdded";
    }
    else if ($_POST["action"] == "set_appointment")
    {
	////////////////////////////////////////////////////////////////////////////////////////////
	// Je suis prof et je veux inscrire ou je suis eleve et je veux m'inscrire a un rendez vous
	if (!is_number($slot = $_POST["id_slot"]))
	    $request = new ErrorResponse("InvalidSlot");
	$x = db_select_one("id_team FROM appointment_slot WHERE id = $slot");
	if ($x["id_team"] == -1)
	{
	    if ($activity->is_teacher)
		$id_team = @is_number($_POST["id_team"]) ? $_POST["id_team"] : -1;
	    else if (isset($team["id"]))
		$id_team = $team["id"];
	    else
		$id_team = -1;
	    if ($id_team != -1)
	    {
		$request = update_table("appointment_slot", $slot, ["id_team" => $id_team]);
		add_log(TRACE, "Subscribing team $id_team to slot $slot");
	    }
	}
    }
    else if ($_POST["action"] == "delete_appointment")
    {
	//////////////////////////////////////////////////////
	// Je suis prof et je veux supprimer des rendez vous
	// OU je suis eleve et je veux supprimer le mien
	$slot = (int)$_POST["id_slot"];
	$x = db_select_one("id_team FROM appointment_slot WHERE id = $slot");
	if (!$activity->is_teacher)
	{
	    if ($activity->can_subscribe && $x["id_team"] == $activity->user_team["id"])
	    {
		$request = update_table("appointment_slot", $slot, ["id_team" => -1]);
		add_log(TRACE, "I am with team ".$x["id_team"]." and remove from the slot $slot");
	    }
	}
	else
	{
	    $request = update_table("appointment_slot", $slot, ["id_team" => -1]);
	    add_log(TRACE, "Teacher remove team ".$x["id_team"]." from the slot $slot");
	}
    }
    else if ($_POST["action"] == "switch_slot")
    {
	////////////////////////////////////////////////////
	// Je suis prof et je veux modifier des rendez vous
	if ($activity->is_teacher)
	{
	    $slot = $_POST["id_slot"];
	    if (($id_team = $_POST["id_team"]) == -1)
		$id_team = -2;
	    else
		$id_team = -1;
	    $request = update_table("appointment_slot", $slot, ["id_team" => $id_team]);
	}
    }
    else if ($_POST["action"] == "take_appointment")
    {
	///////////////////////////////////////
	// Je suis ELEVE et je veux prendre rendez vous
	if ($activity->registered == false) // J'étais pas inscrit, je m'inscris
	{
	    if ($activity->can_subscribe == false)
		$request = new ErrorResponse("YouCannotTakeAppointment");
	    else
	    {
		$request = subscribe_to_instance($activity);
		$activity->build($activity->id);
		$registered = $activity->team;
		$team = $activity->user_team;
		$activity->build($activity->id, false, false, $activity->unique_session->id);
	    }
	}
	if ($request->is_error() == false && $activity->unique_session->slot[$_POST["id_slot"]]["id_team"] == -1)
	{
	    // On peut s'inscrire à partir du moment ou l'on étais inscrit...
	    if (0 && period($instance["registration_date"], $instance["close_date"]) == false)
		$request = new ErrorResponse("SubscriptionAreClosed");
	    else
	    {
		$slot = @$_POST["id_slot"];
		$request = update_table("appointment_slot", $_POST["id_slot"], ["id_team" => $activity->user_team["id"]]);
	    }
	}
    }
    else if ($_POST["action"] == "edit_date")
    {
	if ($activity->is_teacher)
	{
	    require ("edit_dates.php");

	    edit_dates($activity, $activity->unique_session, $_POST);
	}
    }
    else if ($_POST["action"] == "delete_session")
    {
	if ($activity->is_teacher && $activity->unique_session != NULL)
	{
	    mark_as_deleted("activity", $activity->unique_session->id);
	}
    }
    else if ($_POST["action"] == "delete_instance")
    {
	if ($activity->is_teacher)
	{
	    mark_as_deleted("activity", $activity->id);
	}
    }
    else if ($_POST["action"] == "link_course")
    {
	// Ce bout de code ne devrait pas être la mais sur la page des modèles d'activités
	if ($activity->is_teacher)
	{
	    if (($codename = resolve_codename("class_gallery", $_POST["course"]))->is_error())
		$request = $codename;
	    else if (!is_number($_POST["chapter"]))
		$request = new ErrorResponse("InvalidParameter");
	    else
	    {
		$id = $codename->value;
		$chapter = $_POST["chapter"];
		$sel = db_select_one("
                   id
                   FROM class_gallery_asset
                   WHERE id_class_gallery = $id AND chapter = $chapter
		");
		$request = add_link($instance["id_activity"], $sel["id"], "activity", "class_gallery_asset");
	    }
	}
    }
    else if ($_POST["action"] == "remove_course")
    {
	// Pareil, ca n'a rien a faire la
	if ($activity->is_teacher && is_number($_POST["link"]))
	{
	    $Database->query("DELETE FROM activity_class_gallery_asset WHERE id = ".$_POST["link"]);
	}
    }
    else if ($_POST["action"] == "subscribe_all")
    {
	if ($activity->is_teacher)
	{
	    // Il faudrait supprimer les inscriptions avant de faire ca.
	    $all = db_select_all("
               user_cycle.id_user as id
               FROM activity
               LEFT JOIN activity_cycle
                 ON activity.parent_activity = activity_cycle.id_activity
               LEFT JOIN user_cycle
                 ON user_cycle.id_cycle = activity_cycle.id_cycle
               WHERE activity.id = ".$activity->id."
	       ");
	    if (($size = $activity->team_size) <= 0)
		$size = 1;
	    $teams = [];
	    shuffle($all);
	    for ($i = 0; $i < count($all); ++$i)
		$teams[$i % (count($all) / $size)][] = $all[$i]["id"];
	    foreach ($teams as $t)
	    {
		$team_id = -1;
		for ($i = 0; $i < count($t); ++$i)
		{
		    if ($i == 0)
			$request = @subscribe_to_instance($activity, $t[$i], -1, true, true);
		    else
			$request = @subscribe_to_instance($activity, $t[$i], $team_id, true, true);
		    if ($request->is_error())
			break 2;
		    $request = $request->value;
		    $team_id = $request["id_team"];
		    $request = new ValueResponse("");
		}
	    }
	}
    }
    else if ($_POST["action"] == "join_team")
    {
	subscribe_to_instance($activity, $User["id"], $_POST["team"]);
    }
    else if ($_POST["action"] == "accept_member")
    {
	$team_id = (int)$_POST["team_id"];
	$adm = db_select_one("* FROM user_team WHERE id_team = $team_id AND id_user = {$User["id"]}");
	$can_id = (int)$_POST["user_id"];
	$can = db_select_one("* FROM user_team WHERE id_team = $team_id AND id_user = {$can_id}");
	if ($adm != NULL && $can != NULL && $adm["status"] == 2 && $can["status"] == 0)
	{
	    $Database->query("UPDATE user_team SET status = 1 WHERE id = {$can["id"]}");
	    $LogMsg = "TeamModified";
	}
	else
	    $request = new ErrorResponse("YouCannotEditThisTeam");
    }
    else if ($_POST["action"] == "refuse_member")
    {
	$team_id = (int)$_POST["team_id"];
	$adm = db_select_one("* FROM user_team WHERE id_team = $team_id AND id_user = {$User["id"]}");
	$can_id = (int)$_POST["user_id"];
	$can = db_select_one("* FROM user_team WHERE id_team = $team_id AND id_user = {$can_id}");
	if ($adm != NULL && $can != NULL && $adm["status"] == 2 && $can["status"] == 0)
	{
	    $Database->query("DELETE FROM user_team WHERE id = {$can["id"]}");
	    $LogMsg = "TeamModified";
	}
	else
	    $request = new ErrorResponse("YouCannotEditThisTeam");
    }
    else if ($_POST["action"] == "lockteam" || $_POST["action"] == "unlockteam")
    {
	$team_id = (int)$_POST["team_id"];
	$adm = db_select_one("* FROM user_team WHERE id_team = $team_id AND id_user = {$User["id"]}");
	if ($adm != NULL && $adm["status"] == 2)
	{
	    if ($_POST["action"] == "lockteam")
		$lock = 0;
	    else
		$lock = 1;
	    $Database->query("UPDATE team SET canjoin = $lock WHERE id = {$adm["id_team"]}");
	    $LogMsg = "TeamModified";
	}
	else
	    $request = new ErrorResponse("YouCannotEditThisTeam");
    }
    else if ($_POST["action"] == "declare_present")
    {
	if ($activity->registered && !$activity->registered_elsewhere && $activity->unique_session != NULL && $activity->user_team["present"] == 0 &&
	    period(date_to_timestamp($activity->unique_session->begin_date) - 2 * $five_minute, $activity->unique_session->end_date)
	)
	{
	    if (period(date_to_timestamp($activity->unique_session->begin_date) - 2 * $five_minute,
		       date_to_timestamp($activity->unique_session->begin_date) + $five_minute * 3))
		$request = @update_table("team", $activity->user_team["id"], ["present" => 1]);
	    else
		$request = @update_table("team", $activity->user_team["id"], ["present" => -1]);
	    $LogMsg = "PresenceDeclared";
	}
    }
    else if ($_POST["action"] == "add_room")
    {
	if ($activity->is_teacher)
	{
	    $request = add_links($_POST["session"], $_POST["rooms"], "session", "room", true);
	    $LogMsg = "RoomAdded";
	}
	else
	    $request = new ErrorResponse("PermissionDenied");
    }
    else if ($_POST["action"] == "remove_room")
    {
	if ($activity->is_teacher)
	{
	    $request = remove_links($_POST["session"], $_POST["rooms"], "session", "room", true);
	    $LogMsg = "RoomRemoved";
	}
	else
	    $request = new ErrorResponse("PermissionDenied");
    }
    else if ($_POST["action"] == "adm_declare_present")
    {
	if (!$activity->is_teacher)
	    return ;
	$request = @update_table("team", $_POST["team"], ["present" => 1]);
	$LogMsg = "PresenceDeclared";
    }
    else if ($_POST["action"] == "adm_declare_late")
    {
	if (!$activity->is_teacher)
	    return ;
	$request = @update_table("team", $_POST["team"], ["present" => -1]);
	$LogMsg = "PresenceDeclared";
    }
    else if ($_POST["action"] == "adm_declare_missing")
    {
	if (!$activity->is_teacher)
	    return ;
	$request = @update_table("team", $_POST["team"], ["present" => -2]);
	$LogMsg = "PresenceDeclared";
    }
    else if ($_POST["action"] == "raise_alert")
    {
	if (!$activity->is_assistant)
	    $request = new ErrorResponse("PermissionDenied");
	else
	    $request = raise_alert($_POST["user"], $_POST["message"], 1);
	$LogMsg = "AlertRaised";
    }
}

// Si on a fait quelque chose, on rafraichit l'object activity
if (isset($_POST["action"]))
{
    require ("build_activity.php");
}

