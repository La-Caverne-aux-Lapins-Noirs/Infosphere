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

function GenerateDabsic($school)
{

}

function AddSchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;

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

function EditSchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = edit_school($id, $data))->is_error())
	return ($ret);
    return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
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

function SetRole($id, $data, $role)
{
    global $Dictionnary;

    $params = [
	"left_value" => $data[$role],
	"right_value" => $id,
	"left_field_name" => "user",
	"right_field_name" => "school",
	"properties" => [
	    "authority" => strtoupper($role)
	],
	"allow_duplicate" => true
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
		"name" => $role,
		"placeholder" => ucfirst($role),
		"" => $role,
	    ],
	    "linked_elems" => $school[$role],
	    "admin_func" => "only_admin",
    ])]));
}

function SetDirector($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "director"));
}

function SetCommercial($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "commercial"));
}

function SetLibrarian($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "librarian"));
}

function SetTeacher($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "teacher"));
}

function SetSecretariat($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "secretariat"));
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

$Tab = [
    "GET" => [
	"" => [
	    "is_teacher,is_director",
	    "DisplaySchool"
	]
    ],
    "PUT" => [
	"" => [
	    "is_director_for_school",
	    "EditSchool",
	],
	"director" => [
	    "only_admin",
	    "SetDirector"
	],
	"commercial" => [
	    "is_director_for_school",
	    "SetCommercial",
	],
	"librarian" => [
	    "is_director_for_school",
	    "SetLibrarian",
	],
	"teacher" => [
	    "is_director_for_school",
	    "SetTeacher",
	],
	"secretariat" => [
	    "is_director_for_school",
	    "SetSecretariat",
	],
	"user" => [
	    ["is_director_for_school", "is_commercial_for_school", "is_secretariat_for_school"],
	    "SetStudent",
	],
	"cycle" => [
	    ["is_director_for_school", "is_teacher_for_school"],
	    "SetCycle",
	]
    ],
    "POST" => [
	"" => [
	    "only_admin",
	    "AddSchool"
	]
    ],
    "DELETE" => [
	"" => [
	    "only_admin",
	    "DeleteSchool"
	],
	"commercial" => [
	    "is_director_for_school",
	    "SetCommercial",
	],
	"librarian" => [
	    "is_director_for_school",
	    "SetLibrarian",
	],
	"teacher" => [
	    "is_director_for_school",
	    "SetTeacher",
	],
	"secretariat" => [
	    "is_director_for_school",
	    "SetSecretariat",
	],
	"user" => [
	    ["is_director_for_school", "is_commercial_for_school", "is_secretariat_for_school"],
	    "SetStudent"
	],
	"director" => [
	    "only_admin",
	    "SetDirector"
	],
	"cycle" => [
	    ["is_director_for_school", "is_teacher_for_school"],
	    "SetCycle",
	]
    ]
];

