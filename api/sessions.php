<?php

function DisplaySession($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    $id = (int)$id;
    if (!count($session = db_select_one("id_activity FROM session WHERE id = $id")))
	not_found();
    $page = $module;
    ($module = new FullActivity)->build($session["id_activity"]);
    $template = $module->is_template;

    foreach ($module->session as $s)
    {
	if ($s->id == $id)
	{
	    $session = $s;
	    break ;
	}
    }	
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($session, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    // On récupère l'activité elle-même
    require ("./pages/activity/display_session.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function EditSession($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    ($session = new FullSession)->build_session($id);
    
    $data["id"] = $id;
    $data["activity"] = $session->id_activity;
    $data["team"] = $session->id_team;
    $data["laboratory"] = $session->id_laboratory;
    $data["user"] = $session->id_user;
    $data["begin_date"] = $session->begin_date;
    $data["end_date"] = $session->end_date;
    
    if (isset($data["maximum_subscription"]))
	$data["maximum_subscription"] = (int)$data["maximum_subscription"];
    if (isset($data["day"]) && isset($data["begin"]) && isset($data["end"]))
    {
	$data["begin_date"] = date_to_timestamp($data["day"]) + time_to_timestamp($data["begin"]);
	$data["end_date"] = date_to_timestamp($data["day"]) + time_to_timestamp($data["end"]);
    }
   
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

function AddSession($id, $data, $method, $output, $module)
{
    if ($id != -1)
	bad_request();
    ($module = new FullActivity)->build(@$data["activity"]);
    if ($module->type_type != 2)
	return (new ErrorResponse("ThisActivityCannotHaveSession"));
    if (($request = add_session(
	["activity" => $module->id], [], $module->is_template))->is_error()
    )
        return ($request);
    $_GET["sub"] = 1;
    return (DisplayActivity($module->id, [], "GET", $output, $module));
}

function DeleteSession($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($request = mark_as_deleted("session", $id, ""))->is_error())
	return ($request);
    return (new ValueResponse([
	"msg" => $Dictionnary["Deleted"],
    ]));
}
