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
	$profile_status = user_profile_status($usr["profile_status"] ?? ((isset($usr["prospect"]) && !!$usr["prospect"]) ? "prospect" : "member"));
	$fake = user_profile_status_is_fake($profile_status);
	
	if (($request = @subscribe($usr["login"], @$usr["mail"], NULL, false, $fake, $profile_status))->is_error())
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
    if (($extra = document_builder_request_extra_fields($data))->is_error())
	return ($extra);

    $ret = build_user_contract($id, document_builder_contract_kind($data), $extra->value);
    if ($ret->is_error())
	return ($ret);
    return (new ValueResponse([
	"msg" => "Contrat généré",
	"content" => document_builder_public_url($ret->value["output"])
    ]));
}

function GenerateUserLetter($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    if (!isset($data["model"]) || trim((string)$data["model"]) == "")
	return (new ErrorResponse("InvalidParameter", "model"));
    if (($extra = document_builder_request_extra_fields($data))->is_error())
	return ($extra);

    $ret = build_user_letter($id, $data["model"], $extra->value);
    if ($ret->is_error())
	return ($ret);
    return (new ValueResponse([
	"msg" => "Lettre générée",
	"content" => document_builder_public_url($ret->value["output"])
    ]));
}

function SendUserDocumentForm($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    $id = (int)$id;
    if (!is_director_for_student($id))
        forbidden();
    $target = db_select_one("* FROM user WHERE id = $id AND authority != -1");
    if ($target == NULL)
        return (new ErrorResponse("UserNotFound"));

    $result = registration_form_create_document_invitation(
        $id,
        $data["document_file"] ?? "",
        $data["model_hash"] ?? "",
        $data["target_year"] ?? 0,
        $data["document_label"] ?? "",
        (int)$User["id"],
        $data["signature_bindings"] ?? []
    );
    if (!$result["ok"])
        return (new ErrorResponse($result["error"], $result["details"] ?? ""));

    $name = trim(($target["first_name"] ?? "")." ".($target["family_name"] ?? ""));
    $label = trim((string)($data["document_label"] ?? ""));
    $title = sprintf(
        $Dictionnary["DocumentFormMailTitle"] ?? "Document à compléter : %s",
        $label != "" ? $label : ($Dictionnary["DocumentFormTitle"] ?? "document")
    );
    $body = sprintf(
        $Dictionnary["DocumentFormMailContent"] ?? "Bonjour %s,\n\nL'établissement vous demande de compléter le formulaire suivant :\n%s\n\nLe lien est valable quatorze jours. Vous pouvez sauvegarder un brouillon avant la validation définitive.",
        $name,
        $result["url"]
    );
    $sent = send_mail($target["mail"], $title, $body);
    if ($sent->is_error())
    {
        registration_form_revoke_token($result["token"]);
        return ($sent);
    }
    add_log(EDITING_OPERATION,
        "Document form sent to user $id for ".($label != "" ? $label : ($data["document_file"] ?? "document")),
        $id
    );
    return (new ValueResponse([
        "msg" => $Dictionnary["DocumentFormSent"] ?? "Formulaire documentaire envoyé.",
        "content" => $result["url"]
    ]));
}

function SendUserAdministrativeForm($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    $id = (int)$id;
    $target = db_select_one("* FROM user WHERE id = $id AND authority != -1");
    if ($target == NULL)
        return (new ErrorResponse("UserNotFound"));
    if (!can_send_user_administrative_form($id))
        forbidden();

    $status = $target["profile_status"] ?? "";
    $is_relation_profile = user_relation_is_administrative_contact($id);
    // Une relation légal/finance est prioritaire sur un ancien profile_status
    // éventuellement resté à "prospect".
    if ($is_relation_profile)
        $result = registration_form_create_profile_invitation($id, (int)$User["id"]);
    else if ($status == "prospect")
        $result = registration_form_create_invitation($id, $data["kind"] ?? "", (int)$User["id"]);
    else
        return (new ErrorResponse("InvalidParameter", "profile_status"));
    if (!$result["ok"])
        return (new ErrorResponse($result["error"], $result["details"] ?? ""));

    $name = trim(($target["first_name"] ?? "")." ".($target["family_name"] ?? ""));
    if ($is_relation_profile)
    {
        $title = $Dictionnary["AdministrativeFormMailTitle"] ?? "Vos informations administratives";
        $body = sprintf(
            $Dictionnary["AdministrativeFormMailContent"] ?? "Bonjour %s,\n\nVous pouvez compléter ou vérifier vos informations administratives avec le lien suivant, valable quatorze jours :\n%s\n\nVous pouvez sauvegarder un brouillon avant la validation définitive.",
            $name,
            $result["url"]
        );
        $message = $Dictionnary["AdministrativeFormSent"] ?? "Formulaire administratif envoyé.";
    }
    else
    {
        $title = $Dictionnary["RegistrationFormMailTitle"] ?? "Votre dossier d'inscription";
        $body = sprintf(
            $Dictionnary["RegistrationFormMailContent"] ?? "Bonjour %s,\n\nVous pouvez compléter votre dossier d'inscription avec le lien suivant, valable quatorze jours :\n%s\n\nVous pouvez sauvegarder un brouillon avant la validation définitive.",
            $name,
            $result["url"]
        );
        $message = $Dictionnary["RegistrationFormSent"] ?? "Formulaire d'inscription envoyé.";
    }

    $sent = send_mail($target["mail"], $title, $body);
    if ($sent->is_error())
    {
        registration_form_revoke_token($result["token"]);
        return ($sent);
    }
    add_log(EDITING_OPERATION,
        "Administrative form sent to user $id".(!$is_relation_profile && $status == "prospect" ? " for ".strtoupper((string)($data["kind"] ?? "")) : ""),
        $id
    );
    return (new ValueResponse(["msg" => $message, "content" => $result["url"]]));
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

    $is_self = is_me($id);
    $identity_authority = is_identity_authority_for_user($id);
    $action = isset($data["action"]) ? (string)$data["action"] : "";
    $administrative_authority = function_exists("can_manage_user_administrative_profile")
        && can_manage_user_administrative_profile($id);
    if (!$is_self && !$identity_authority
        && !($action == "administrative_data" && $administrative_authority))
        forbidden();

    $codename = $usr["codename"];
    $mail = $usr["mail"];
    unset($data["action"]);

    if ($action == "administrative_data")
    {
        if (!$administrative_authority)
            forbidden();
        $request = user_identity_update_contract_administrative_fields($id, $data);
        if ($request->is_error())
            return ($request);
        refresh_user($id);
        $identity = user_identity_write_identity_dabsic($id);
        if ($identity->is_error())
            return ($identity);
        return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
    }

    if (isset($data["avatar"]))
    {
        if (!isset($data["type"]))
            $data["type"] = "set_avatar";
        if (!is_admin() || $is_self)
            $data["type"] = "set_avatar";
        $target = $Configuration->UsersDir($codename).
            ($data["type"] == "set_photo" ? "admin/photo.png" : "public/avatar.png");
        $data["avatar"] = base64_decode($data["avatar"][0]["content"]);
        if (file_put_contents($target, $data["avatar"]) === false)
            return (new ErrorResponse("CannotWritePngFile"));
        unset($data["type"], $data["avatar"]);
    }
    else
        unset($data["type"]);

    $allowed = ["nickname", "visibility"];
    if ($identity_authority)
        $allowed = array_merge($allowed, [
            "mail", "first_name", "use_name", "family_name", "gender",
            "birth_date", "nationality", "phone", "street_name",
            "postal_code", "city", "country"
        ]);
    foreach (array_keys($data) as $field)
        if (!in_array($field, $allowed, true))
            return (new ErrorResponse("InvalidParameter", $field));

    if (isset($data["mail"]) && $data["mail"] == $mail)
        unset($data["mail"]);
    if (isset($data["birth_date"]))
        $data["birth_date"] = trim((string)$data["birth_date"]) == "" ? NULL : db_form_date($data["birth_date"]);
    if (isset($data["mail"]) && trim((string)$data["mail"]) == "")
        return (new ErrorResponse("InvalidParameter", "mail"));
    if (isset($data["mail"]))
    {
        global $Database;
        $new_mail = $Database->real_escape_string($data["mail"]);
        if (db_select_one("id FROM user WHERE mail = '$new_mail' AND id != $id AND authority != -1"))
            return (new ErrorResponse("MailUsed"));
    }

    if (count($data) && ($request = set_user_data($id, $data))->is_error())
        return ($request);
    refresh_user($id);
    $identity = user_identity_write_identity_dabsic($id);
    if ($identity->is_error())
        return ($identity);
    return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
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

function user_documentation_file_root()
{
    return (document_builder_documentation_file_root());
}

function documentation_file_access($id, $file, $public = false, $read = false)
{
    $base = user_documentation_file_root();

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

function user_letter_file_root()
{
    return (document_builder_letter_file_root());
}

function letter_file_access($id, $file, $public = false, $read = false)
{
    $base = user_letter_file_root();

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

function GetDocumentationFileDir($id, $data, $method, $output, $module, $msg = "")
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
        "documentation_file",
        "documentation_file_access",
        user_documentation_file_root()
    ));
}

function GetLetterFileDir($id, $data, $method, $output, $module, $msg = "")
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
	"letter_file",
	"letter_file_access",
	user_letter_file_root()
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

function AddDocumentationFile($id, $data, $method, $output, $module)
{
    return (AddUserFile($id, $data, $method, $output, $module, "documentation_file_access", "GetDocumentationFileDir"));
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
    if ($access_function == "documentation_file_access" &&
        resolve_path($file) == user_documentation_file_root())
        forbidden();
    if ($access_function == "letter_file_access" &&
	resolve_path($file) == user_letter_file_root())
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

function RemoveDocumentationFile($id, $data, $method, $output, $module)
{
    return (RemoveUserFile($id, $data, $method, $output, $module, "documentation_file", "documentation_file_access", "GetDocumentationFileDir"));
}

function RemoveLetterFile($id, $data, $method, $output, $module)
{
    return (RemoveUserFile($id, $data, $method, $output, $module, "letter_file", "letter_file_access", "GetLetterFileDir"));
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
        "documentation_file" => [
            "is_director_for_student",
            "GetDocumentationFileDir",
        ],
	"letter_file" => [
	    "is_director_for_student",
	    "GetLetterFileDir",
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
        "documentation_file" => [
            "is_director_for_student",
            "AddDocumentationFile",
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
	"new_letter" => [
	    "is_director_for_student",
	    "GenerateUserLetter",
	],
	"properties" => [
	    "can_edit_user_profile",
	    "SetUserProperties",
	],
	"registration" => [
	    "can_send_user_administrative_form",
	    "SendUserAdministrativeForm",
	],
        "document_form" => [
            "is_director_for_student",
            "SendUserDocumentForm",
        ],
	"administrative_data" => [
	    "can_manage_user_administrative_profile",
	    "SetUserProperties",
	],
	"set_avatar" => [
	    "can_edit_user_profile",
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
        "documentation_file" => [
            "is_director_for_student",
            "GetDocumentationFileDir",
        ],
	"letter_file" => [
	    "is_director_for_student",
	    "GetLetterFileDir",
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
        "documentation_file" => [
            "is_director_for_student",
            "RemoveDocumentationFile",
        ],
	"letter_file" => [
	    "is_director_for_student",
	    "RemoveLetterFile",
	],
    ]
];



