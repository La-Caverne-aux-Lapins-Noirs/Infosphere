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

// Formulaire le plus souple pour l'ajout d'users: login custom, possibilité d'omettre des champs, etc.
function SubscribeUser($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1 || !isset($data["users"]))
	bad_request();
    $cnt = 0;
    $subs = [];
    foreach ($data["users"] as $usr)
    {
	$fake = false;
	if (isset($usr["prospect"]) && !!$usr["prospect"])
	    $fake = true;
	
	if (($request = @subscribe($usr["login"], @$usr["mail"], NULL, false, $fake))->is_error())
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
	    "objectives" => $Dictionnary["DefaultUserObjectives"],
	]);
	if ($request->is_error())
	{
	    ob_end_clean();
	    return ($request);
	}
	if (($request = add_default_user_todolist($id_user))->is_error())
	{
	    ob_end_clean();
	    return ($request);
	}
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

function GenerateScolarityContract($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();

    $extra = [];
    if (isset($data["fields"]))
    {
	if (!is_array($data["fields"]))
	    $data["fields"] = explode(" ", $data["fields"]);
	foreach ($data["fields"] as $field)
	{
	    if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_\.]*)=(.*)$/', $field, $match))
		return (new ErrorResponse("InvalidParameter", $field));
	    $extra[$match[1]] = $match[2];
	}
    }
    if (isset($data["output"]))
	$extra["Output"] = $data["output"];

    $ret = build_user_contract($id, document_builder_contract_kind($data), $extra);
    if ($ret->is_error())
	return ($ret);
    return (new ValueResponse([
	"msg" => "Contrat généré",
	"content" => document_builder_public_url($ret->value["output"])
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
	    $target .= "public/avatar.png";
	else
	    $target .= "admin/photo.png";

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
    refresh_user($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"]
    ]));
}

function SetUserLink($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

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

	if (!is_admin() && $link == "school")
	{
	    if (($schools = resolve_codename("school", $data["school"]))->is_error())
		return ($schools);
	    if (!is_array($schools = $schools->value))
		$schools = [$schools];
	    $fnd = false;
	    foreach ($User["school"] as $sc)
	    {
		foreach ($schools as $sc2)
		{
		    if (abs($sc["id_school"]) != abs($sc2))
			continue ;
		    $fnd = true;
		    break 2;
		}
	    }
	    if ($fnd == false)
		forbidden();
	}
	else if (!is_my_director($id))
	    forbidden();
	
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

/*
** Politique d'accès aux fichiers:
** => admin: seule la direction peut accéder à ces informations,
**           ainsi que les super administrateurs  
** => public: tout le monde a accès en lecture
** => autre: seul l'élève et les super administrateurs ont accès
*/

function file_access($id, $file, $public = false, $read = false)
{
    $file = resolve_path($file);
    if (strlen($file) && $file[0] == "/")
	$file = substr($file, 1);
    $filex = explode("/", $file);
    // On demande une modif "admin"
    if (!isset($filex[0]) || $filex[0] == "")
    {
	if (!is_me($id) && !is_admin() && $read == false)
	    forbidden();
	return ($file);
    }
    if ($filex[0] == "public")
	return ($file);
    if ($filex[0] == "admin")
    {
	if (!is_director_for_student($id))
	    forbidden();
	return ($file);
    }
    if (!is_me($id) && !is_admin())
	forbidden();
    return ($file);
}

function user_subscription_file_root()
{
    return ("admin/subscription");
}

function user_file_path_is_under($file, $base)
{
    $file = resolve_path($file);
    $base = resolve_path($base);

    return ($file == $base ||
            strncmp($file, $base."/", strlen($base) + 1) == 0);
}

function subscription_file_access($id, $file, $public = false, $read = false)
{
    $base = user_subscription_file_root();

    $file = resolve_path($file);
    if ($file == "")
	$file = $base;
    else if (!user_file_path_is_under($file, $base))
    {
	$filex = explode("/", $file);
	if (isset($filex[0]) && $filex[0] == "admin")
	    forbidden();
	$file = resolve_path($base."/".$file);
    }
    $file = file_access($id, $file, $public, $read);
    if (!user_file_path_is_under($file, $base))
	forbidden();
    return ($file);
}

function GetUserFileDir($id, $data, $method, $output, $module, $msg, $type, $access_function, $locked_path = "")
{
    global $Configuration;
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    if (!isset($data["path"]))
	$data["path"] = "";

    $id = (int)$id;
    if (($user = db_select_one("codename FROM user WHERE id = $id")) == NULL)
	not_found();

    $nocd = false;
    if (isset($data["nocd"]))
	$nocd = !!$data["nocd"];
    $fbid = "file_browser";
    if (isset($data["fbid"]))
	$fbid = $data["fbid"];
    $path_browser_can_cd = !$nocd;
    if (isset($data["path_browser_can_cd"]))
	$path_browser_can_cd = !!$data["path_browser_can_cd"];
    $language = "";
    if (isset($data["language"]))
	$language = $data["language"];

    $file = $access_function($id, $data["path"], false, true);
    $root = $Configuration->UsersDir($user["codename"]);
    $html = get_dir($root, $file, "user", $id, $type, $fbid, true, $language, $nocd, $locked_path, $path_browser_can_cd);
    $msg = $msg ? ["msg" => $msg] : [];
    return (new ValueResponse(array_merge($msg, [
	"content" => $html
    ])));
}

function GetFileDir($id, $data, $method, $output, $module, $msg = "")
{
    return (GetUserFileDir($id, $data, $method, $output, $module, $msg, "file", "file_access"));
}

function GetSubscriptionFileDir($id, $data, $method, $output, $module, $msg = "")
{
    $data["nocd"] = 0;
    $data["path_browser_can_cd"] = 1;
    return (GetUserFileDir(
	$id,
	$data,
	$method,
	$output,
	$module,
	$msg,
	"subscription_file",
	"subscription_file_access",
	user_subscription_file_root()
    ));
}

function AddUserFile($id, $data, $method, $output, $module, $access_function, $return_function)
{
    global $Configuration;
    global $User;

    if ($id == -1 || !isset($data["file"]) || !isset($data["path"]))
	bad_request();
    $id = (int)$id;
    $path = $access_function($id, $data["path"], false);
    $data["path"] = $path;
    if (($user = db_select_one("codename FROM user WHERE id = $id")) == NULL)
	not_found();
    $root = $Configuration->UsersDir($user["codename"]);
    $target = $root.$path."/";

    // On vérifie la taille disponible
    $admin_size = intval(shell_exec("du -c $root/admin | tail -n 1"));
    $total_size = intval(shell_exec("du -c $root | tail -n 1"));
    $user_size = $total_size - $admin_size;
    $add_size = 0;
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	if (in_array(pathinfo($files["name"], PATHINFO_EXTENSION), [
	    "php", "sh", "pl"
	]))
	    forbidden();
	$add_size += 0;
    }
    $required = $user_size + $add_size;
    $available = (int)$Configuration->Properties["account_space"];
    if ($required > $available)
    {
	$unit1 = 0;
	$unit2 = 0;
	$size = ["o", "ko", "mo", "go", "to", "po"];
	while ($required > 1024 && $unit1 < count($size) - 1)
	{
	    $unit1 += 1;
	    $required /= 1024;
	}
	while ($available > 1024 && $unit2 < count($size) - 1)
	{
	    $unit2 += 1;
	    $available /= 1024;
	}
	return (new ErrorResponse(
	    "NotEnoughSpace",
	    "Required $required".$size[$unit1],
	    "Available $available".$size[$unit2]
	));
    }

    // C'est parti.
    foreach ($data["file"] as $files)
    {

	$content = base64_decode($files["content"]);
	new_directory($target);
	$files["name"] = str_replace(" ", "_", $files["name"]);
	if ($files["name"][0] == ".")
	    $files["name"] = substr($files["name"], 1);
	file_put_contents($target.$files["name"], $content);
	system("chmod 640 ".$target.$files["name"]);
    }
    return ($return_function($id, $data, "GET", $output, $module, "FileAdded"));
}

function AddFile($id, $data, $method, $output, $module)
{
    return (AddUserFile($id, $data, $method, $output, $module, "file_access", "GetFileDir"));
}

function AddSubscriptionFile($id, $data, $method, $output, $module)
{
    return (AddUserFile($id, $data, $method, $output, $module, "subscription_file_access", "GetSubscriptionFileDir"));
}

function RemoveUserFile($id, $data, $method, $output, $module, $url_key, $access_function, $return_function)
{
    global $Configuration;

    // C'est file parceque c'est /api/user/id/file/etc.
    if ($id == -1 || !isset($data[$url_key]))
	bad_request();
    $id = (int)$id;
    if (($user = db_select_one("codename FROM user WHERE id = $id")) == NULL)
	not_found();
    $root = $Configuration->UsersDir($user["codename"]);
    $file = $data[$url_key];
    if ($file[0] == "-")
	$file = substr($file, 1);
    $file = str_replace("@", "/", $file);
    if (strncmp($root, $file, strlen($root)) != 0)
	bad_request();
    $file = substr($file, strlen($root));
    $file = $access_function($id, $file, false);
    if (strstr($file, "*"))
	forbidden();
    if (strstr($file, "["))
	forbidden();
    
    if (basename($file) == "admin")
	forbidden();
    if (basename($file) == "public")
	forbidden();
    if ($access_function == "subscription_file_access" &&
	resolve_path($file) == user_subscription_file_root())
	forbidden();
    $file = escapeshellarg($root.$file);
    system("rm -r $file");
    return ($return_function($id, $data, "GET", $output, $module, "FileRemoved"));
}

function RemoveFile($id, $data, $method, $output, $module)
{
    return (RemoveUserFile($id, $data, $method, $output, $module, "file", "file_access", "GetFileDir"));
}

function RemoveSubscriptionFile($id, $data, $method, $output, $module)
{
    return (RemoveUserFile($id, $data, $method, $output, $module, "subscription_file", "subscription_file_access", "GetSubscriptionFileDir"));
}

$Tab = [
    // Récupération d'utilisateur(s)
    "GET" => [
	"" => [
	    "logged_in",
	    "DisplayUser"
	],
	"file" => [
	    "logged_in",
	    "GetFileDir",
	],
	"subscription_file" => [
	    "is_director_for_student",
	    "GetSubscriptionFileDir",
	],
    ],
    "POST" => [
	"" => [
	    "is_director",
	    "SubscribeUser"
	],
	"todolist" => [
	    "is_me_or_admin",
	    "SetTodoEntry"
	],
	"file" => [
	    // Une vérification supplémentaire doit être faite
	    // car admin/ ne peut etre lu et écrit que par la direction
	    "is_me_or_director_for_student",
	    "AddFile",
	],
	"subscription_file" => [
	    "is_director_for_student",
	    "AddSubscriptionFile",
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
	"new_contract" => [
	    "only_admin",
	    "GenerateScolarityContract",
	],
	"properties" => [
	    "is_me_or_my_director",
	    "SetUserProperties",
	],
	"set_avatar" => [
	    "is_me_or_my_director",
	    "SetUserProperties",
	],
	"user" => [
	    "is_my_director",
	    "SetUserLink",
	],
	"school" => [
	    "am_i_director", // On est pas directeur avant de s'ajouter directeur
	    "SetUserLink",
	],
	"cycle" => [
	    "is_my_director",
	    "SetUserLink",
	],
	"file" => [
	    // Une vérification supplémentaire doit être faite
	    // car admin/ ne peut etre lu et écrit que par la direction
	    "logged_in",
	    "GetFileDir",
	],
	"subscription_file" => [
	    "is_director_for_student",
	    "GetSubscriptionFileDir",
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
	    "is_my_director",
	    "SetUserLink",
	],
	"school" => [
	    "is_my_director",
	    "SetUserLink",
	],
	"cycle" => [
	    "is_my_director",
	    "SetUserLink"
	],
	"todolist" => [
	    "is_me_or_admin",
	    "SetTodoEntry"
	],
	"file" => [
	    // Une vérification supplémentaire doit être faite
	    // car admin/ ne peut etre lu et écrit que par la direction
	    "is_me_or_director_for_student",
	    "RemoveFile",
	],
	"subscription_file" => [
	    "is_director_for_student",
	    "RemoveSubscriptionFile",
	],
    ]
];



