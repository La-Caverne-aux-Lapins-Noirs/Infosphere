<?php

function intercom_resolve_context($misc_type, $id)
{
    if ($misc_type == "common")
    {
        $id = (int)$id;
        if (!function_exists("intercom_common_channel_definition")
            || intercom_common_channel_definition($id) == NULL)
            not_found();
        return (new ValueResponse($id));
    }
    if ($misc_type == "school_staff")
        $misc_type = "school";
    if (($ret = resolve_codename($misc_type, $id))->is_error())
	return ($ret);
    $id = (int)$ret->value;

    if ($misc_type == "activity")
    {
	if (!($tmp = db_select_one("id_template FROM activity WHERE id = $id")))
	    not_found();
	if (($tmp = $tmp["id_template"]) != NULL && $tmp != -1)
	    $id = (int)$tmp;
    }
    return (new ValueResponse($id));
}

function intercom_activity_access($id)
{
    if (($ret = resolve_codename("activity", $id))->is_error())
        return (false);
    $id = (int)$ret->value;
    if (function_exists("intercom_can_moderate_context") && intercom_can_moderate_context("activity", $id))
        return (true);
    if (is_subscribed_or_assistant($id))
        return (true);

    foreach (db_select_all("
        id
        FROM activity
        WHERE id_template = $id
           OR reference_activity = $id
           OR parent_activity = $id
        LIMIT 100
    ") as $activity)
    {
        if (is_subscribed_or_assistant($activity["id"])
            || (function_exists("intercom_can_moderate_context") && intercom_can_moderate_context("activity", $activity["id"])))
            return (true);
    }
    return (false);
}

function intercom_team_access($id)
{
    if (($ret = resolve_codename("team", $id))->is_error())
        return (false);
    $id = (int)$ret->value;
    return (is_my_team($id)
        || is_assistant_for_team($id)
        || (function_exists("intercom_can_moderate_context") && intercom_can_moderate_context("team", $id)));
}


function intercom_live_last_page($count, $page_size)
{
    $count = (int)$count;
    $page_size = max(1, (int)$page_size);
    if ($count <= 0)
        return (0);
    return ((int)(($count - 1) / $page_size));
}

function intercom_render_template($template, $vars = [])
{
    extract($GLOBALS);
    foreach ($vars as $key => $value)
        $$key = $value;
    ob_start();
    require ("./tools/template/$template");
    return (ob_get_clean());
}

function intercom_live_subject_response($intercom, $data)
{
    $payload = [
        "mode" => "subject",
        "changed" => false,
        "hash" => $intercom["content_hash"],
        "page" => $intercom["page"],
        "page_size" => $intercom["page_size"],
        "on_last_page" => $intercom["on_last_page"] ? 1 : 0,
    ];

    if (isset($data["known_hash"]) && $data["known_hash"] == $intercom["content_hash"])
        return (new ValueResponse(["intercom" => $payload]));

    $payload["changed"] = true;
    $payload["header"] = intercom_render_template("intercom_subject_header.phtml", [
        "intercom" => $intercom,
    ]);
    $payload["list"] = intercom_render_template("intercom_subject_list.phtml", [
        "intercom" => $intercom,
    ]);
    return (new ValueResponse(["intercom" => $payload]));
}

function intercom_live_message_response($intercom, $data)
{
    if (count($intercom["subjects"]) == 0)
        not_found();

    $subject = $intercom["subjects"][0];
    $known_hash = try_get($data, "known_hash", "");
    $known_count = (int)try_get($data, "known_count", $subject["nbr_message"]);
    $known_on_last_page = (int)try_get($data, "known_on_last_page", $subject["on_last_page"] ? 1 : 0);
    $known_page = (int)try_get($data, "known_page", $subject["page"]);
    $since = (int)try_get($data, "since", $subject["last_message_id"]);
    $page_size = max(1, (int)$subject["page_size"]);

    $payload = [
        "mode" => "message",
        "changed" => false,
        "hash" => $intercom["content_hash"],
        "page" => $subject["page"],
        "page_size" => $page_size,
        "count" => (int)$subject["nbr_message"],
        "last_id" => (int)$subject["last_message_id"],
        "on_last_page" => $subject["on_last_page"] ? 1 : 0,
    ];

    if ($known_hash == $intercom["content_hash"])
        return (new ValueResponse(["intercom" => $payload]));

    $action =
        "/api/intercom/{$subject["id_misc"]}".
        "/{$subject["misc_type"]}".
        "/{$subject["id"]}"
    ;
    $msgbox = $intercom["div"]."_msg";
    $old_last_page = intercom_live_last_page($known_count, $page_size);
    $new_last_page = intercom_live_last_page($subject["nbr_message"], $page_size);
    $can_append =
        $known_on_last_page
        && $subject["on_last_page"]
        && $known_page == $old_last_page
        && $known_page == $new_last_page
        && $subject["nbr_message"] > $known_count
    ;

    $payload["changed"] = true;
    $payload["controls"] = intercom_render_template("intercom_message_controls.phtml", [
        "intercom" => $intercom,
        "subject" => $subject,
        "action" => $action,
    ]);

    if ($known_on_last_page != ($subject["on_last_page"] ? 1 : 0))
        $payload["composer"] = intercom_render_template("intercom_message_composer.phtml", [
            "intercom" => $intercom,
            "subject" => $subject,
            "action" => $action,
            "msgbox" => $msgbox,
        ]);

    if ($can_append)
    {
        $new_messages = db_select_all("\n            *\n            FROM message\n            WHERE id_message = {$subject["id"]}\n              AND id > $since\n            ORDER BY post_date ASC, id ASC\n        ");
        if (count($new_messages))
        {
            foreach ($new_messages as &$message)
                intercom_prepare_message_for_display($message);
            unset($message);
            $subject["message"] = $new_messages;
            /* intercom_live_append_filter_hidden_for_viewer */
            intercom_filter_hidden_messages_for_viewer($subject);
            if (count($subject["message"]) == 0)
                return (new ValueResponse(["intercom" => $payload]));
            $payload["mode"] = "append";
            $payload["messages"] = intercom_render_template("intercom_message_list.phtml", [
                "intercom" => $intercom,
                "subject" => $subject,
            ]);
            return (new ValueResponse(["intercom" => $payload]));
        }
    }

    $payload["mode"] = "replace_messages";
    $payload["messages"] = intercom_render_template("intercom_message_list.phtml", [
        "intercom" => $intercom,
        "subject" => $subject,
    ]);
    return (new ValueResponse(["intercom" => $payload]));
}

function intercom_live_response($intercom, $data, $id_subject)
{
    if ($id_subject == -1)
        return (intercom_live_subject_response($intercom, $data));
    return (intercom_live_message_response($intercom, $data));
}


function intercom_cycle_access($id)
{
    global $User;

    if (($ret = resolve_codename("cycle", $id))->is_error())
        return (false);
    $id = (int)$ret->value;
    if (is_admin() || is_director_for_cycle($id))
        return (true);
    return (db_select_one("
        id FROM user_cycle
        WHERE id_user = ".((int)$User["id"])."
          AND id_cycle = $id
    ") != NULL);
}

function intercom_laboratory_access($id)
{
    return (is_member_of_laboratory($id)
        || (function_exists("intercom_can_moderate_context") && intercom_can_moderate_context("laboratory", $id)));
}

function intercom_common_access($id)
{
    return (function_exists("intercom_common_channel_definition")
        && intercom_common_channel_definition($id) != NULL);
}

function intercom_api_school_access($id)
{
    if (($ret = resolve_codename("school", $id))->is_error())
        return (false);
    return (function_exists("intercom_school_access")
        && intercom_school_access($ret->value));
}

function intercom_api_school_staff_access($id)
{
    if (($ret = resolve_codename("school", $id))->is_error())
        return (false);
    return (function_exists("intercom_school_staff_access")
        && intercom_school_staff_access($ret->value));
}

function GetIntercom($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Dictionnary;
    global $User;

    if ($id == -1)
	bad_request();
    if (isset($data["intercom_action"]))
        return (HandleIntercomAction($id, $data, $method, $output, $module));
    if (!isset($data["action"]))
	bad_request();

    if (($ret = intercom_resolve_context($data["action"], $id))->is_error())
	return ($ret);
    $id = $ret->value;

    $SUBID = (int)$SUBID;
    $intercom = get_intercomf($data["action"], $id, [
	"id_subject" => (int)$SUBID,
	"recursive" => @boolval($data["recursive"]) || (int)$SUBID != -1,
	"page" => isset($data["page"]) ? (int)$data["page"] : NULL,
	"page_size" => isset($data["page_size"]) ? (int)$data["page_size"] : 10,
    ]);

    if (isset($data["update"]) && $data["update"] == 1)
	return (new ValueResponse(["content" => $intercom["content_hash"]]));
    if ($output == "json")
	return (new ValueResponse([
	    "content" => json_encode($intercom, JSON_UNESCAPED_SLASHES),
	]));

    if (!isset($data["div"]))
	$intercom["div"] = $intercom["misc_type"]."_intercom";
    else
	$intercom["div"] = $data["div"];
    $intercom["base_url"] = intercom_api_url($intercom);
    $intercom["default_visibility"] = intercom_visibility($intercom["misc_type"], try_get($data, "visibility", NULL));
    $intercom["allow_visibility"] = is_admin();
    $intercom["allow_laboratory"] = true;

    if (isset($data["live"]) && (int)$data["live"])
        return (intercom_live_response($intercom, $data, (int)$SUBID));

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
	if (count($intercom["subjects"]) == 0)
	    not_found();
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



function HandleIntercomAction($id, $data, $method, $output, $module)
{
    global $SUBID;

    if (!isset($data["intercom_action"]))
        bad_request();
    if ($SUBID == -1)
        bad_request();
    $id_message = (int)$SUBID;
    $current_subject = isset($data["current_subject"]) ? (int)$data["current_subject"] : $id_message;
    if ($data["intercom_action"] == "report")
        $ret = intercom_report_message($id_message, isset($data["reason"]) ? $data["reason"] : "");
    else if ($data["intercom_action"] == "moderate")
        $ret = intercom_set_message_moderation($id_message, true);
    else if ($data["intercom_action"] == "restore")
        $ret = intercom_set_message_moderation($id_message, false);
    else if ($data["intercom_action"] == "close_reports")
        $ret = intercom_close_message_reports($id_message);
    else
        return (new ErrorResponse("InvalidRequest"));
    if ($ret->is_error())
        return ($ret);
    $SUBID = $current_subject;
    unset($data["intercom_action"]);
    unset($data["reason"]);
    if (isset($data["full"]) && (int)$data["full"])
        $method = "GET";
    else if ($SUBID != -1)
        $method = "POST";
    else
        $method = "GET";
    return (GetIntercom($id, $data, $method, $output, $module));
}
function PostMessage($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $User;
    global $Database;

    if ($id == -1)
	bad_request();
        if (isset($data["intercom_action"]))
        return (HandleIntercomAction($id, $data, $method, $output, $module));
if (!isset($data["action"]) || !isset($data["message"]))
        bad_request();

    if (($ret = intercom_resolve_context($data["action"], $id))->is_error())
	return ($ret);
    $id = (int)$ret->value;
    $misc_type = $data["action"];

    if (($lab = intercom_laboratory_sql(try_get($data, "laboratory", NULL))) instanceof Response)
	return ($lab);
    if (($visibility = intercom_visibility($misc_type, try_get($data, "visibility", NULL))) === NULL)
	return (new ErrorResponse("InvalidParameter", try_get($data, "visibility", NULL)));
    if ($visibility == INTERCOM_ADMIN && !is_admin())
	return (new ErrorResponse("InvalidParameter", $visibility));

    if ($SUBID == -1)
    {
	if (!isset($data["title"]))
	    bad_request();
	$data["title"] = trim(str_replace("\n", " ", $data["title"]));
	if (strlen($data["title"]) < 3)
	    return (new ErrorResponse("TitleTooShort"));
	$id_message = "NULL";
	$id_misc = $id;
    }
    else
    {
	if (isset($data["title"]))
	    bad_request();
	$data["title"] = "";
	$id_message = (int)$SUBID;
	$chk = db_select_one("* FROM message WHERE id = $id_message AND id_message IS NULL");
	if ($chk == NULL || !intercom_subject_visible($chk))
	    not_found();
	$id_misc = (int)$chk["id_misc"];
	$misc_type = $chk["misc_type"];
	$visibility = $chk["visibility"] === NULL ? "NULL" : (int)$chk["visibility"];
	$lab = $chk["id_laboratory"] === NULL ? "NULL" : (int)$chk["id_laboratory"];
    }
    $title = intercom_message_text($data["title"]);
    $message = intercom_message_text($data["message"]);
    if (strlen($message) < 2)
	return (new ErrorResponse("InvalidRequest"));

    $parent_clause = $id_message === "NULL" ? "id_message IS NULL" : "id_message = $id_message";
    $last_message = db_select_one("\n        *\n        FROM message\n        WHERE misc_type = '$misc_type'\n          AND id_misc = $id_misc\n          AND $parent_clause\n        ORDER BY post_date DESC\n    ");
    if ($last_message != NULL && $last_message["id_user"] == $User["id"] && $id_message !== "NULL")
    {
	if ($method == "PUT")
	    $edit = "message = '$message',";
	else
	    $edit = "message = CONCAT(message, '\\n\\n$message'),";
	$Database->query("\n            UPDATE message SET\n                $edit\n                post_date = NOW()\n            WHERE id = {$last_message["id"]}\n        ");
    }
    else
    {
	$Database->query("\n            INSERT INTO message (\n              id_user, id_laboratory, visibility, misc_type, id_misc, id_message, title, message\n            ) VALUES (\n              {$User["id"]}, $lab, $visibility, '$misc_type', $id_misc,\n              $id_message, '$title', '$message'\n            )\n        ");
    }

    if ($id_message === "NULL")
	$SUBID = -1;
    else
	intercom_mark_subject_read((int)$id_message);
    if (isset($data["full"]) && (int)$data["full"])
	$method = "GET";
    return (GetIntercom($id, $data, $method, $output, $module));
}

function DeleteMessage($id, $data, $method, $output, $module)
{
    $data["intercom_action"] = "moderate";
    return (HandleIntercomAction($id, $data, $method, $output, $module));
}

$Tab = [
    "SUBGET" => [
	"cycle" => [
	    "intercom_cycle_access",
	    "GetIntercom",
	],
	"laboratory" => [
	    "intercom_laboratory_access",
	    "GetIntercom",
	],
	"activity" => [
	    "intercom_activity_access",
	    "GetIntercom",
	],
	"team" => [
	    "intercom_team_access",
	    "GetIntercom",
	],
	"user" => [
	    "logged_in",
	    "GetIntercom",
	],
    ],
    "GET" => [
	"cycle" => [
	    "intercom_cycle_access",
	    "GetIntercom",
	],
	"laboratory" => [
	    "intercom_laboratory_access",
	    "GetIntercom",
	],
	"activity" => [
	    "intercom_activity_access",
	    "GetIntercom",
	],
	"team" => [
	    "intercom_team_access",
	    "GetIntercom",
	],
	"user" => [
	    "logged_in",
	    "GetIntercom",
	],
    ],
    "POST" => [
	"cycle" => [
	    "intercom_cycle_access",
	    "PostMessage",
	],
	"laboratory" => [
	    "intercom_laboratory_access",
	    "PostMessage",
	],
	"activity" => [
	    "intercom_activity_access",
	    "PostMessage",
	],
	"team" => [
	    "intercom_team_access",
	    "PostMessage",
	],
	"user" => [
	    "logged_in",
	    "PostMessage",
	],
    ],
    "PUT" => [
	"cycle" => [
	    "intercom_cycle_access",
	    "PostMessage",
	],
	"laboratory" => [
	    "intercom_laboratory_access",
	    "PostMessage",
	],
	"activity" => [
	    "intercom_activity_access",
	    "PostMessage",
	],
	"team" => [
	    "intercom_team_access",
	    "PostMessage",
	],
	"user" => [
	    "logged_in",
	    "PostMessage",
	],
    ],
    "DELETE" => [
	"cycle" => [
	    "intercom_cycle_access",
	    "DeleteMessage",
	],
	"laboratory" => [
	    "intercom_laboratory_access",
	    "DeleteMessage",
	],
	"activity" => [
	    "intercom_activity_access",
	    "DeleteMessage",
	],
	"team" => [
	    "intercom_team_access",
	    "DeleteMessage",
	],
	"user" => [
	    "logged_in",
	    "DeleteMessage",
	],
    ],
];

// Intercoms transversaux et salons école.
foreach (["SUBGET", "GET", "POST", "PUT", "DELETE"] as $intercom_method)
{
    $Tab[$intercom_method]["common"] = ["intercom_common_access", $intercom_method == "DELETE" ? "DeleteMessage" : ($intercom_method == "POST" || $intercom_method == "PUT" ? "PostMessage" : "GetIntercom")];
    $Tab[$intercom_method]["school"] = ["intercom_api_school_access", $intercom_method == "DELETE" ? "DeleteMessage" : ($intercom_method == "POST" || $intercom_method == "PUT" ? "PostMessage" : "GetIntercom")];
    $Tab[$intercom_method]["school_staff"] = ["intercom_api_school_staff_access", $intercom_method == "DELETE" ? "DeleteMessage" : ($intercom_method == "POST" || $intercom_method == "PUT" ? "PostMessage" : "GetIntercom")];
}

