<?php

function GenerateSessionSignInSheet($id, $data, $method, $output, $module)
{
    global $User, $Dictionnary;

    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $morning_end = (string)($data["morning_end"] ?? "13:00");
    $afternoon_start = (string)($data["afternoon_start"] ?? "14:00");
    $result = session_signin_pdf_generate($id, (int)($User["id"] ?? 0),
        $morning_end, $afternoon_start);
    if (!$result["ok"])
        return (new ErrorResponse("CannotExecute", $result["error"]));
    return (new ValueResponse(["msg" => $Dictionnary["SessionSignInGenerated"]]));
}

function DisplaySession($id, $data, $method, $output, $module)
{
    if ($id == -1)
        bad_request();
    $id = (int)$id;
    $row = db_select_one("id, id_activity, id_user, id_laboratory FROM session WHERE id = $id AND deleted IS NULL");
    if ($row == NULL)
        not_found();

    if (session_is_standalone($row))
    {
        $session = new FullSession;
        if ($session->build_session($id) === false)
            not_found();
        if ($output == "json")
            return (new ValueResponse(["content" => json_encode($session, JSON_UNESCAPED_SLASHES)]));
        ob_start();
        require ("./pages/activity/display_standalone_session.phtml");
        return (new ValueResponse(["content" => ob_get_clean()]));
    }

    $activity_id = (int)$row["id_activity"];
    if ($activity_id <= 0)
        not_found();
    $page = $module;
    ($module = new FullActivity)->build($activity_id);
    $template = $module->is_template;

    foreach ($module->session as $s)
    {
        if ($s->id == $id)
        {
            $session = $s;
            break ;
        }
    }
    if (!isset($session) || !is_object($session))
        not_found();
    if ($output == "json")
        return (new ValueResponse(["content" => json_encode($session, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    require ("./pages/activity/display_session.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function ExportSessionDescription($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (export_session_description_response($id));
}

function EditSession($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    ($session = new FullSession)->build_session($id);
    if (session_has_source($session))
        return (new ErrorResponse("SessionManagedBySource"));
    
    $data["id"] = $id;
    $data["activity"] = $session->id_activity;
    $data["team"] = $session->id_team;
    $data["laboratory"] = $session->id_laboratory;
    $data["user"] = $session->id_user;
    if ($session->standalone)
    {
        $data["name"] = isset($data["name"]) ? $data["name"] : $session->name;
        if (!isset($data["day"]) && !isset($data["begin_date"]) && !isset($data["begin"]))
            $data["begin_date"] = db_form_date($session->begin_date);
        if (!isset($data["day"]) && !isset($data["end_date"]) && !isset($data["end"]))
            $data["end_date"] = db_form_date($session->end_date);
    }
    
    if (isset($data["maximum_subscription"]))
	$data["maximum_subscription"] = (int)$data["maximum_subscription"];
    else
	$data["maximum_subscription"] = $session->maximum_subscription;

    $data = convert_date($data);

    if (($ret = edit_session($data))->is_error())
	return ($ret);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
    ]));
}

function SetSessionRoom($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1 || strlen(@$data["room"]) == 0)
	bad_request();
    if (($ret = handle_linksf([
	"left_field_name" => "session",
	"left_value" => $id,
	"right_field_name" => "room",
	"right_value" => $data["room"]
    ]))->is_error())
	return ($ret);
    if (!count($session = db_select_one("id_activity FROM session WHERE id = $id")))
	not_found();
    ($session = new FullSession)->build_session($id);
    ob_start();
    ?>
    <?=$Dictionnary["RoomCapacity"]; ?>: <?=$session->room_space != -1 ? $session->room_space : "/"; ?>
    <?php
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "session",
	    "hook_id" => $id,
	    "linked_name" => "room",
	    "linked_elems" => $session->room,
	    "admin_func" => "is_teacher_or_director_for_session",
	    "additional_html" => ob_get_clean()
    ])]));
}


function SessionJuryPanelResponse($id, $msg = "")
{
    $title_session = fetch_title_session_for_session((int)$id);
    if ($title_session == NULL)
        return (new ErrorResponse("SessionHasNoTitleSession"));

    ($session = new FullSession)->build_session($id);
    ensure_session_juries($session);
    $value = ["content" => list_of_linksb(session_jury_link_params($session))];
    if ($msg != "")
        $value["msg"] = $msg;
    return (new ValueResponse($value));
}

function SetSessionJury($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id == -1 || !isset($data["jury"]) || trim((string)$data["jury"]) == "")
        bad_request();
    $id = (int)$id;
    $title_session = fetch_title_session_for_session($id);
    if ($title_session == NULL)
        return (new ErrorResponse("SessionHasNoTitleSession"));

    $jury = trim((string)$data["jury"]);
    $jury_value = $jury;
    if (substr($jury_value, 0, 1) == "-")
        $jury_value = substr($jury_value, 1);
    if (($ret = resolve_codename("user", $jury_value, "codename", true))->is_error())
        return ($ret);
    $jury_user = $ret->value;
    if (($jury_user["profile_status"] ?? "") != "jury")
        return (new ErrorResponse("UserNotFound"));
    $id_user = (int)$jury_user["id"];
    $id_title_session = (int)$title_session["id"];

    if (!jury_can_certify_title($id_user, (int)$title_session["id_title"]))
        return (new ErrorResponse("JuryNotQualifiedForTitle"));

    if ($method == "DELETE" || substr($jury, 0, 1) == "-")
    {
        if ($Database->query("DELETE FROM title_session_jury WHERE id_title_session = $id_title_session AND id_user = $id_user") == false)
            return (new ErrorResponse("CannotEdit"));
        add_log(DESTRUCTIVE_OPERATION, "Title session #$id_title_session jury #$id_user removed", $id_user);
        return (SessionJuryPanelResponse($id, $Dictionnary["JuryRemovedFromTitleSession"] ?? "Jury retiré de la session de titre"));
    }

    if ($Database->query("
        INSERT IGNORE INTO title_session_jury (id_title_session, id_user)
        VALUES ($id_title_session, $id_user)
    ") == false)
        return (new ErrorResponse("CannotEdit"));

    add_log(CREATIVE_OPERATION, "Title session #$id_title_session jury #$id_user added", $id_user);
    return (SessionJuryPanelResponse($id, $Dictionnary["JuryAddedToTitleSession"] ?? "Jury ajouté à la session de titre"));
}

function AddSession($id, $data, $method, $output, $module)
{
    global $Database;
    global $User;

    if ($id != -1)
        bad_request();

    // Historical activity session creation remains unchanged from the caller's
    // point of view, but authorization is now checked here because POST
    // /api/session also accepts standalone personal/laboratory events.
    if (isset($data["activity"]) && trim((string)$data["activity"]) != "")
    {
        ($module = new FullActivity)->build($data["activity"]);
        if (!$module || $module->type_type != 2)
            return (new ErrorResponse("ThisActivityCannotHaveSession"));
        if (!is_teacher_or_director_for_activity($module->id))
            forbidden();
        if (($request = add_session(
            ["activity" => $module->id], [], $module->is_template))->is_error()
        )
            return ($request);
        $_GET["sub"] = 1;
        return (DisplayActivity($module->id, [], "GET", $output, $module));
    }

    // Compact API used by the calendar UI and, later, by document workflows.
    // standalone_target is optional; callers may also provide user/laboratory
    // directly.
    if (isset($data["standalone_target"]))
    {
        $target = trim((string)$data["standalone_target"]);
        if (preg_match('/^user:(.+)$/', $target, $m))
            $data["user"] = $m[1];
        else if (preg_match('/^laboratory:(.+)$/', $target, $m))
            $data["laboratory"] = $m[1];
        else
            return (new ErrorResponse("InvalidParameter", "standalone_target"));
    }

    if (isset($data["day"]) && isset($data["begin"]) && isset($data["end"]))
    {
        $day = trim((string)$data["day"]);
        $begin = trim((string)$data["begin"]);
        $end = trim((string)$data["end"]);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)
            || !preg_match('/^\d{2}:\d{2}$/D', $begin)
            || !preg_match('/^\d{2}:\d{2}$/D', $end))
            return (new ErrorResponse("InvalidDate"));
        $data["begin_date"] = $day." ".$begin.":00";
        $data["end_date"] = $day." ".$end.":00";
    }

    $scope_probe = [
        "id_activity" => -1,
        "id_user" => -1,
        "id_laboratory" => -1,
    ];
    if (isset($data["user"]) && trim((string)$data["user"]) != "")
    {
        if (($resolved = resolve_codename("user", $data["user"]))->is_error())
            return ($resolved);
        $scope_probe["id_user"] = (int)$resolved->value;
        $data["user"] = (int)$resolved->value;
    }
    if (isset($data["laboratory"]) && trim((string)$data["laboratory"]) != "")
    {
        if (($resolved = resolve_codename("laboratory", $data["laboratory"]))->is_error())
            return ($resolved);
        $scope_probe["id_laboratory"] = (int)$resolved->value;
        $data["laboratory"] = (int)$resolved->value;
    }
    if (!session_is_standalone($scope_probe))
        return (new ErrorResponse("InvalidParameter", "session scope"));

    if (session_standalone_kind($scope_probe) === "user")
    {
        if ((int)$scope_probe["id_user"] !== (int)$User["id"]
            && !is_director_for_student((int)$scope_probe["id_user"], false)
            && !is_admin())
            forbidden();
    }
    else if (!session_standalone_can_manage($scope_probe))
        forbidden();

    if (($request = add_session($data))->is_error())
        return ($request);
    $session_id = (int)$Database->insert_id;

    // A standalone session may have no room, exactly like an activity session.
    // The convenience field below handles one room at creation time; the
    // existing /api/session/<id>/room endpoint remains available afterwards.
    if (isset($data["room"]) && trim((string)$data["room"]) != "")
    {
        if (($ret = add_link(
            $session_id,
            $data["room"],
            "session",
            "room",
            false,
            [],
            "session_room"
        ))->is_error())
            return ($ret);
    }

    return (new ValueResponse([
        "id" => $session_id,
        "msg" => "Session créée",
    ]));
}

function DeleteSession($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id = (int)$id;
    $row = db_select_one("source_type, source_id, source_key FROM session WHERE id = $id");
    if ($row != NULL && session_has_source($row))
        return (new ErrorResponse("SessionManagedBySource"));
    if (($request = mark_as_deleted("session", $id, ""))->is_error())
	return ($request);
    return (new ValueResponse([
	"msg" => $Dictionnary["Deleted"],
    ]));
}
