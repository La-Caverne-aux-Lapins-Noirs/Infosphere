<?php

function GetIntercom($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $User;
    global $Dictionnary;

    if ($id == -1)
	bad_request();
    if (!isset($data["action"]))
	bad_request();

    if (($ret = resolve_codename($data["action"], $id))->is_error())
	return ($ret);
    $id = $ret->value;

    // Traitements spécifiques
    if ($data["action"] == "activity")
    {
	if (!($tmp = db_select_one("
		id_template FROM {$data["action"]}
		WHERE id = $id
		")))
	    not_found();
	if (($tmp = $tmp["id_template"]) != NULL && $tmp != -1)
	    $id = $tmp;
    }

    $SUBID = (int)$SUBID;
    $intercom = get_intercomf($data["action"], $id, [
	"id_subject" => (int)$SUBID,
	"recursive" => @boolval($data["recursive"]) || (int)$SUBID != -1,
	"page" => isset($data["page"]) ? (int)$data["page"] : NULL,
	"page_size" => isset($data["page_size"]) ? (int)$data["page_size"] : 10
    ]);

    if (isset($data["update"]) && $data["update"] == 1)
	return (new ValueResponse(["content" => $intercom["content_hash"]]));
    if ($output == "json")
	return (new ValueResponse([
	    "content" => json_encode($intercom, JSON_UNESCAPED_SLASHES)
	]));

    if (!isset($data["div"]))
	$intercom["div"] = $intercom["misc_type"]."_intercom";
    else
	$intercom["div"] = $data["div"];
    $intercom["base_url"] = "/api/intercom/".$intercom["id_misc"]."/".$intercom["misc_type"];
    
    ob_start();
    if ($SUBID == -1)
    {
	if ($method == "GET")
	    require_once ("./tools/template/intercom_subject_page.phtml");
	else
	    require_once ("./tools/template/intercom_subject_list.phtml");
    }
    else
    {
	if ($method == "GET")
	    require_once ("./tools/template/intercom_message_page.phtml");
	else
	{
	    $subject = $intercom["subjects"][0];
	    require_once ("./tools/template/intercom_message_list.phtml");
	}
    }
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function PostMessage($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $User;
    global $Dictionnary;
    global $Database;

    if ($id == -1)
	bad_request();
    if (!isset($data["action"])
	|| !isset($data["message"]))
        bad_request();

    if (isset($data["laboratory"]) && $data["laboratory"] != -1)
    {
	if (($lab = resolve_codename("laboratory", $data["laboratory"]))->is_error())
	    return ($lab);
	$lab = $lab->value;
    }
    else
	$lab = "NULL";
    
    if ($SUBID == -1)
    {
	if (!isset($data["title"]))
	    bad_request();
	$data["title"] = str_replace("\n", " ", $data["title"]);
	$data["title"] = trim($data["title"]);
	if (strlen($data["title"]) < 3)
	    return (new ErrorResponse("TitleTooShort"));
	$id_message = "NULL";
	if (($ret = resolve_codename($data["action"], $id))->is_error())
	    return ($ret);
	$id_misc = $ret->value;
	$misc_type = $data["action"];
    }
    else
    {
	if (isset($data["title"]))
	    bad_request();
	$data["title"] = "";
	$id_message = (int)$SUBID;
	$chk = db_select_one("* FROM message WHERE id = $id_message");
	if ($chk == NULL)
	    not_found();
	if ($chk["id_message"] != -1 && $chk["id_message"] != NULL)
	    not_found();
	$id_misc = $chk["id_misc"];
	$misc_type = $chk["misc_type"];
    }
    $title = strip_tags($data["title"]);
    $title = $Database->real_escape_string($title);
    $message = strip_tags($data["message"]);
    $message = $Database->real_escape_string($message);

    $last_message = db_select_one("
	* FROM message WHERE id_message = $id_message
	ORDER BY post_date DESC
    ");
    if ($last_message != NULL && $last_message["id_user"] == $User["id"])
    {
	if ($method == "PUT")
	    $edit = "message = '$message'),";
	else
	    $edit = "message = CONCAT(message, '\n\n$message'),";
	$Database->query("
	    UPDATE message SET
		$edit
		post_date = NOW()
	    WHERE id = {$last_message["id"]}
	    ");
    }
    else
    {
	$Database->query("
        INSERT INTO message (
          id_user, id_laboratory, misc_type, id_misc, id_message, title, message
        ) VALUES (
          {$User["id"]}, $lab, '$misc_type', $id_misc,
	  $id_message, '$title', '$message'
	)
	  ");
    }

    return (GetIntercom($id, $data, $method, $output, $module));
}

function DeleteMessage($id, $data, $method, $output, $module)
{

}


$Tab = [
    "SUBGET" => [
	"activity" => [
	    "is_subscribed_or_assistant",
	    "GetIntercom"
	],
	"user" => [
	    "logged_in",
	    "GetIntercom"
	],
    ],
    "GET" => [
	"activity" => [
	    "is_subscribed_or_assistant",
	    "GetIntercom"
	],
	"user" => [
	    "logged_in",
	    "GetIntercom"
	],
    ],
    "POST" => [
	"activity" => [
	    "is_subscribed_or_assistant",
	    "PostMessage",
	],
	"user" => [
	    "logged_in",
	    "PostMessage",
	],
    ],
    "PUT" => [
	"activity" => [
	    "is_subscribed_or_assistant",
	    "PostMessage",
	],
	"user" => [
	    "logged_in",
	    "PostMessage",
	],
    ],
    "DELETE" => [
	"activity" => [
	    "is_subscribed_or_assistant",
	    "DeleteMessage",
	],
	"user" => [
	    "logged_in",
	    "DeleteMessage",
	],
    ],
];
