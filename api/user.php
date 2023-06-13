<?php

function DisplayUser($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    // Ce moyen ne permet pas de récupérer beaucoup d'informations.
    // Seulement celle par défaut de fetch_user
    // Car les préférences utilisateurs n'impactent pas cette page.
    $users = fetch_users([], $id);
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($users, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    if (count($users) == 0)
	echo $Dictionnary["NoUser"];
    else
	foreach ($users as $user)
	    require ("./pages/$module/display_user.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function SubscribeUser($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id != -1 || !isset($data["users"]))
	bad_request();
    $cnt = 0;
    $subs = [];
    foreach ($data["users"] as $usr)
    {
	if (($request = @subscribe($usr["login"], @$usr["mail"], NULL, false))->is_error())
	{
	    ob_end_clean();
	    return ($request);
	}
	$id_user = $request->value["id"];
	$request = @set_user_data($usr["login"], [
	    "first_name" => strtolower(@$usr["first_name"]),
	    "family_name" => strtolower(@$usr["family_name"]),
	    "birth_date" => db_form_date(@$usr["birth_date"]),
	    "phone" => @$usr["phone"],
	    "objectives" => $Dictionnary["DefaultUserObjectives"]
	]);
	if ($request->is_error())
	{
	    ob_end_clean();
	    return ($request);
	}
	$Database->query("
          INSERT INTO user_todolist (id_user, content) VALUES
	  ($id_user, '".$Database->real_escape_string($Dictionnary["ThisIsATodoList"])."'),
	  ($id_user, '".$Database->real_escape_string($Dictionnary["YouCanWriteAnything"])."')
	  ");
	$subs[] = $usr["login"];
	$cnt += 1;
    }
    $ret = DisplayUser(implode(";", $subs), [], "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["UserAdded"].": $cnt";
    return ($ret);
}

function SetStatus($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    if ($id == 1)
	forbidden();
    if (($request = set_user_data($id, ["authority" => $data["authority"]]))->is_error())
	return ($request);
    return ($request = new ValueResponse([
	"msg" => $Dictionnary["UserModified"]
    ]));
}

function RegeneratePassword($id, $data, $method, $output, $module)
{
    global $Dictionnary;
	    
    if ($id == -1)
	bad_request();
    if (($request = set_user_attributes($id, ["password" => generate_password()]))->is_error())
	return ($request);
    return (new ValueResponse([
	"msg" => $Dictionnary["PasswordEdited"]
    ]));
}

function SetUserProperties($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    if ($id == -1)
	bad_request();
    $id = (int)$id;

    $usr = db_select_one("codename, mail FROM user WHERE id = $id");
    if ($usr == NULL)
	bad_request();
    $codename = $usr["codename"];
    $mail = $usr["mail"];
    if (isset($data["mail"]) && $data["mail"] == $mail)
	unset($data["mail"]);
    unset($data["action"]);
    if (isset($data["birth_date"]))
	$data["birth_date"] = db_form_date($data["birth_date"]);
    if (isset($data["avatar"]))
    {
	if (!isset($data["type"]))
	    $data["type"] = "set_avatar";
	if (!is_admin() && $data["type"] != "set_avatar")
	    $data["type"] = "set_avatar";
	
	$target = $Configuration->UsersDir($codename);
	if ($data["type"] == "set_avatar")
	    $target .= "avatar.png";
	else
	    $target .= "photo.png";

	$data["avatar"] = base64_decode($data["avatar"][0]["content"]);
	if (file_put_contents($target, $data["avatar"]) === false)
	    return (new ErrorResponse("CannotWritePngFile"));
	unset($data["type"]);
	unset($data["avatar"]);
    }
    if (count($data))
    {
	if (($request = set_user_data($id, $data))->is_error())
	    return ($request);
    }
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"]
    ]));
}

function SetUserLink($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    foreach ([
	"user" => ["parent_child", "parent", "child", "Profile"],
	"school" => [],
	"cycle" => []
    ] as $link => $fields)
    {
	if (count($fields))
	{
	    $table = $fields[0];
	    $left = $fields[1];
	    $right = $fields[2];
	    $lnk = $fields[3];
	}
	else
	    $table = $left = $right = $lnk = "";
	
	if ($data["action"] != $link)
	    continue ;
	if (($request = handle_links(
	    $id, $data[$link], "user", $link, false, $table, false, $left, $right))->is_error()
	)
	    return ($request);

	$user = fetch_users([$link], $id);
	$user = array_shift($user);
	return (new ValueResponse([
	    "msg" => $Dictionnary["Edited"],
	    "content" => list_of_linksb([
		"hook_name" => "user",
		"hook_id" => $id,
		"linked_name" => $link,
		"linked_elems" => $user[$link],
		"method" => $method,
		"dislay_link" => $lnk,
		"full_formular" => false
	    ])
	]));
    }
    bad_request();
}

function DeleteUser($id, $data, $method, $output, $module)
{
    if ($id == 1)
	forbidden(); // On ne peut pas bannir Albedo
    return (update_table("user", $id, ["deleted" => db_form_date(now())]));
}

function UndeleteUser($id, $data, $method, $output, $module)
{
    return (update_table("user", $id, ["deleted" => NULL]));
}

function SetTodoEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;
    global $SUBID;
    global $User;
    
    if ($method == "DELETE")
    {
	$SUBID = abs($SUBID);
	$Database->query("
	    DELETE FROM user_todolist WHERE id_user = $id AND id = $SUBID
	");
	$msg = $Dictionnary["Deleted"];
    }
    else
    {
	$cnt = $Database->real_escape_string($data["content"]);
	$Database->query("
	    INSERT INTO user_todolist (id_user, content) VALUE (
		$id, '$cnt'
	    )
	");
	$msg = $Dictionnary["Added"];
    }
    ob_start();
    get_user_public_data($User);
    require_once ("./pages/home/todolist.php");
    return (new ValueResponse([
	"msg" => $msg,
	"content" => ob_get_clean(),
    ]));
}

$Tab = [
    // Récupération d'utilisateur(s)
    "GET" => [
	"" => [
	    "logged_in",
	    "DisplayUser"
	]
    ],
    "POST" => [
	"" => [
	    "only_admin",
	    "SubscribeUser"
	],
	"todolist" => [
	    "is_me_or_admin",
	    "SetTodoEntry"
	],
    ],
    "PUT" => [
	"set_status" => [
	    "only_admin",
	    "SetStatus",
	],
	"new_password" => [
	    "is_me_or_admin",
	    "RegeneratePassword"
	],
	"properties" => [
	    "is_me_or_admin",
	    "SetUserProperties",
	],
	"set_avatar" => [
	    "is_me_or_admin",
	    "SetUserProperties",
	],
	"user" => [
	    "only_admin",
	    "SetUserLink",
	],
	"school" => [
	    "only_admin",
	    "SetUserLink",
	],
	"cycle" => [
	    "only_admin",
	    "SetUserLink",
	],
	"" => [
	    "only_admin",
	    "UndeleteUser"
	],
    ],
    "DELETE" => [
	"" => [
	    "only_admin",
	    "DeleteUser"
	],
	"user" => [
	    "only_admin",

	    "SetUserLink",
	],
	"school" => [
	    "only_admin",
	    "SetUserLink",
	],
	"cycle" => [
	    "only_admin",
	    "SetUserLink"
	],
	"todolist" => [
	    "is_me_or_admin",
	    "SetTodoEntry"
	]
    ]
];



