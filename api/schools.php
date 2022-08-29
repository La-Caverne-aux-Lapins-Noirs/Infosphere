<?php

function DisplaySchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    $page = $module;
    $school = fetch_school($id);
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($school, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    require ("./pages/school/list_school.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddSchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1)
	bad_request();
    foreach ($data["schools"] as $school)
    {
	if (($ret = add_school($school["codename"], $school["icon"], $school))->is_error())
	    return ($ret);
    }
    $ret = DisplaySchool(-1, [], "GET", $output, $module);
    $ret->value = array_merge(["msg" => $Dictionnary["Added"]], $ret->value);
    return ($ret);
}

function DeleteSchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    if (($ret = mark_as_deleted("school", $id))->is_error())
	return ($ret);
    $ret = DisplaySchool(-1, [], "GET", $output, $module);
    $ret->value = array_merge(["msg" => $Dictionnary["Deleted"]], $ret->value);
    return ($ret);
}

function SetDirector($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    $params = [
	"left_value" => $data["director"],
	"right_value" => $id,
	"left_field_name" => "user",
	"right_field_name" => "school",
	"properties" => [
	    "authority" => 1
	]
    ];
    if (($ret = handle_linksf($params))->is_error())
	return ($ret);
    $school = fetch_school($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "school",
	    "hook_id" => $id,
	    "linked_name" => [
		"table" => "user",
		"name" => "director",
		"placeholder" => "Director",
		"" => "director",
	    ],
	    "linked_elems" => $school["director"],
	    "admin_func" => "only_admin",
    ])]));
}

function SetStudent($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    $params = [
	"left_value" => $data["user"],
	"right_value" => $id,
	"left_field_name" => "user",
	"right_field_name" => "school",
	"properties" => [
	    "authority" => 0
	]
    ];
    if (($ret = handle_linksf($params))->is_error())
	return ($ret);
    $school = fetch_school($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "school",
	    "hook_id" => $id,
	    "linked_name" => "user",
	    "linked_elems" => $school["user"],
	    "admin_func" => "is_director_for_school",
    ])]));
}

function SetCycle($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    $params = [
	"left_value" => $id,
	"right_value" => $data["cycle"],
	"left_field_name" => "school",
	"right_field_name" => "cycle",
    ];
    if (($ret = handle_linksf($params))->is_error())
	return ($ret);
    $school = fetch_school($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "school",
	    "hook_id" => $id,
	    "linked_name" => "cycle",
	    "linked_elems" => $school["cycle"],
	    "admin_func" => "is_director_for_school",
    ])]));
}

