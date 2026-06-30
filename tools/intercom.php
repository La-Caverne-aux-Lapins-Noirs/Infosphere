<?php

function intercom_api_url($intercom, $id_subject = NULL, $parameters = [])
{
    $url =
	"/api/intercom/".
	$intercom["id_misc"].
	"/".
	$intercom["misc_type"]
    ;
    if ($id_subject !== NULL)
	$url .= "/".(int)$id_subject;
    if (isset($intercom["div"]))
	$parameters["div"] = $intercom["div"];
    if (isset($intercom["short_name"]) && $intercom["short_name"])
	$parameters["short_name"] = 1;
    if (count($parameters))
	$url .= "?".http_build_query($parameters);
    return ($url);
}

function intercom_api_url_html($intercom, $id_subject = NULL, $parameters = [])
{
    return (htmlentities(intercom_api_url($intercom, $id_subject, $parameters)));
}

function intercom_escape($value)
{
    global $Database;

    return ($Database->real_escape_string($value));
}

function intercom_message_text($value)
{
    $value = strip_tags($value);
    $value = trim($value);
    return (intercom_escape($value));
}

function intercom_visibility($misc_type, $visibility = NULL)
{
    if ($visibility === NULL || $visibility === "")
    {
	if ($misc_type == "user")
	    return (INTERCOM_PRIVATE);
	return (INTERCOM_PUBLIC);
    }
    $visibility = (int)$visibility;
    if ($visibility < INTERCOM_PUBLIC || $visibility > INTERCOM_PRIVATE)
	return (NULL);
    if ($visibility == INTERCOM_PRIVATE && $misc_type != "user")
	return (NULL);
    return ($visibility);
}

function intercom_laboratory_sql($laboratory)
{
    if ($laboratory === NULL || $laboratory === "" || $laboratory == -1)
	return ("NULL");
    if (($lab = resolve_codename("laboratory", $laboratory))->is_error())
	return ($lab);
    return ((int)$lab->value);
}

function intercom_common_channels()
{
    return ([
        1 => ["codename" => "announcements", "name_key" => "IntercomCommonAnnouncementsName", "description_key" => "IntercomCommonAnnouncementsDescription"],
        2 => ["codename" => "discussion", "name_key" => "IntercomCommonDiscussionName", "description_key" => "IntercomCommonDiscussionDescription"],
        3 => ["codename" => "creations", "name_key" => "IntercomCommonCreationsName", "description_key" => "IntercomCommonCreationsDescription"],
        4 => ["codename" => "events", "name_key" => "IntercomCommonEventsName", "description_key" => "IntercomCommonEventsDescription"],
        5 => ["codename" => "offtopic", "name_key" => "IntercomCommonOfftopicName", "description_key" => "IntercomCommonOfftopicDescription"],
        6 => ["codename" => "media", "name_key" => "IntercomCommonMediaName", "description_key" => "IntercomCommonMediaDescription"],
        7 => ["codename" => "ideas", "name_key" => "IntercomCommonIdeasName", "description_key" => "IntercomCommonIdeasDescription"],
        8 => ["codename" => "suggestions", "name_key" => "IntercomCommonSuggestionsName", "description_key" => "IntercomCommonSuggestionsDescription"],
        9 => ["codename" => "sel", "name_key" => "IntercomCommonSelName", "description_key" => "IntercomCommonSelDescription"],
    ]);
}

function intercom_common_channel_definition($id)
{
    $id = (int)$id;
    $channels = intercom_common_channels();
    return (isset($channels[$id]) ? $channels[$id] : NULL);
}

function intercom_common_channel_name($id)
{
    global $Dictionnary;

    $channel = intercom_common_channel_definition($id);
    if ($channel == NULL)
        return ("common#".(int)$id);
    if (isset($channel["name_key"])
        && isset($Dictionnary[$channel["name_key"]])
        && strlen($Dictionnary[$channel["name_key"]]))
        return ($Dictionnary[$channel["name_key"]]);
    return ($channel["codename"]);
}

function intercom_common_channel_description($id)
{
    global $Dictionnary;

    $channel = intercom_common_channel_definition($id);
    if ($channel == NULL)
        return ("");
    if (isset($channel["description_key"])
        && isset($Dictionnary[$channel["description_key"]])
        && strlen($Dictionnary[$channel["description_key"]]))
        return ($Dictionnary[$channel["description_key"]]);
    return ("");
}

function intercom_school_row($id_school)
{
    global $Language;

    $id_school = (int)$id_school;
    return (db_select_one("
        id,
        codename,
        $Language"."_name as name
        FROM school
        WHERE id = $id_school
          AND (deleted IS NULL OR deleted = 0)
    "));
}

function intercom_school_name($id_school)
{
    $school = intercom_school_row($id_school);
    if ($school == NULL)
        return ("school#".(int)$id_school);
    if (isset($school["name"]) && strlen($school["name"]))
        return ($school["name"]);
    return ($school["codename"]);
}


function support_asset_intercom_row($id_asset)
{
    global $Language;

    $id_asset = (int)$id_asset;
    if ($id_asset <= 0)
        return (NULL);
    $field = $Language."_name";
    return (db_select_one("
        support_asset.id,
        support_asset.codename,
        support_asset.$field as name,
        support.id as id_support,
        support.codename as support_codename,
        support.$field as support_name,
        support_category.id as id_support_category,
        support_category.codename as category_codename,
        support_category.$field as category_name
        FROM support_asset
        LEFT JOIN support ON support.id = support_asset.id_support
        LEFT JOIN support_category ON support_category.id = support.id_support_category
        WHERE support_asset.id = $id_asset
          AND (support_asset.deleted IS NULL OR support_asset.deleted = 0)
          AND support.id IS NOT NULL
          AND (support.deleted IS NULL OR support.deleted = 0)
          AND support_category.id IS NOT NULL
          AND (support_category.deleted IS NULL OR support_category.deleted = 0)
    "));
}

function support_asset_intercom_asset_name_from_row($row)
{
    if ($row == NULL)
        return ("");
    $asset = trim((string)try_get($row, "name", ""));
    if ($asset == "")
        $asset = trim((string)try_get($row, "codename", ""));
    if ($asset == "")
        $asset = "support_asset#".(int)$row["id"];
    return ($asset);
}

function support_asset_intercom_name_from_row($row)
{
    if ($row == NULL)
        return ("");
    $asset = support_asset_intercom_asset_name_from_row($row);
    $support = trim((string)try_get($row, "support_name", ""));
    if ($support == "")
        $support = trim((string)try_get($row, "support_codename", ""));
    if ($support != "")
        return ($support." — ".$asset);
    return ($asset);
}

function support_asset_intercom_asset_name($id_asset)
{
    $row = support_asset_intercom_row($id_asset);
    if ($row == NULL)
        return ("support_asset#".(int)$id_asset);
    return (support_asset_intercom_asset_name_from_row($row));
}

function support_asset_intercom_context_name($id_asset)
{
    $row = support_asset_intercom_row($id_asset);
    if ($row == NULL)
        return ("support_asset#".(int)$id_asset);
    return (support_asset_intercom_name_from_row($row));
}

function support_asset_intercom_can_moderate($id_asset = -1)
{
    return (function_exists("can_edit_supports") && can_edit_supports());
}

function support_asset_intercom_visible_asset_ids()
{
    static $cache = NULL;

    global $User;

    if (function_exists("can_edit_supports") && can_edit_supports())
        return (NULL); // NULL signifie: tous les assets existants.
    if ($cache !== NULL)
        return ($cache);
    $cache = [];
    if (!$User || !function_exists("fetch_my_support_category"))
        return ($cache);
    if (($categories = fetch_my_support_category(true))->is_error())
        return ($cache);
    foreach ($categories->value as $category)
    {
        if (empty($category["selected"]) || !isset($category["support"]))
            continue ;
        foreach ($category["support"] as $support)
        {
            if (empty($support["selected"]) || !isset($support["asset"]))
                continue ;
            foreach ($support["asset"] as $asset)
                if (!empty($asset["selected"]) && isset($asset["id"]))
                    $cache[(int)$asset["id"]] = true;
        }
    }
    return ($cache);
}

function support_asset_intercom_access($id_asset)
{
    $id_asset = (int)$id_asset;
    if ($id_asset <= 0)
        return (false);
    if (support_asset_intercom_row($id_asset) == NULL)
        return (false);
    if (support_asset_intercom_can_moderate($id_asset))
        return (true);
    $visible = support_asset_intercom_visible_asset_ids();
    return (is_array($visible) && isset($visible[$id_asset]));
}

function support_asset_intercom_visible_asset_sql_condition($alias = "support_asset")
{
    $visible = support_asset_intercom_visible_asset_ids();
    if ($visible === NULL)
        return ("1");
    if (!count($visible))
        return ("0");
    return ($alias.".id IN (".implode(",", array_map("intval", array_keys($visible))).")");
}

function support_asset_intercom_participation_filter($root_alias, $uid, $can_moderate)
{
    $uid = (int)$uid;
    if ($can_moderate)
        return ("");
    return ("
          AND EXISTS (
              SELECT 1
              FROM message as my_support_asset_message
              WHERE (my_support_asset_message.id = $root_alias.id
                     OR my_support_asset_message.id_message = $root_alias.id)
                AND my_support_asset_message.id_user = $uid
          )");
}

function support_asset_intercom_unread_count($id_asset)
{
    global $Database;
    global $User;

    static $cache = [];

    if (!$User)
        return (0);
    $id_asset = (int)$id_asset;
    if ($id_asset <= 0 || !support_asset_intercom_access($id_asset))
        return (0);
    if (isset($cache[$id_asset]))
        return ($cache[$id_asset]);

    $uid = (int)$User["id"];
    $can_moderate = support_asset_intercom_can_moderate($id_asset);
    $participation_filter = support_asset_intercom_participation_filter("root", $uid, $can_moderate);
    $hidden_root_filter = "";
    $hidden_item_filter = "";
    if (!$can_moderate)
    {
        $hidden_root_filter = "
          AND NOT EXISTS (
              SELECT 1 FROM message_report as hidden_root
              WHERE hidden_root.id_message = root.id
                AND hidden_root.status = -1
          )";
        $hidden_item_filter = "
                AND NOT EXISTS (
                    SELECT 1 FROM message_report as hidden_item
                    WHERE hidden_item.id_message = item.id
                      AND hidden_item.status = -1
                )";
    }

    $row = db_select_one("
        COUNT(DISTINCT root.id) as cnt
        FROM message as root
        LEFT JOIN message_user as viewed
          ON viewed.id_message = root.id
         AND viewed.id_user = $uid
        WHERE root.misc_type = 'support_asset'
          AND root.id_misc = $id_asset
          AND root.id_message IS NULL
          $hidden_root_filter
          $participation_filter
          AND EXISTS (
              SELECT 1
              FROM message as item
              WHERE (item.id = root.id OR item.id_message = root.id)
                AND item.id_user != $uid
                AND (viewed.view_date IS NULL OR item.post_date > viewed.view_date)
                $hidden_item_filter
          )
    ");
    $cache[$id_asset] = $row == NULL ? 0 : (int)$row["cnt"];
    return ($cache[$id_asset]);
}

function support_asset_intercom_unread_count_for_support($id_support)
{
    static $cache = [];

    $id_support = (int)$id_support;
    if ($id_support <= 0)
        return (0);
    if (isset($cache[$id_support]))
        return ($cache[$id_support]);

    $count = 0;
    foreach (db_select_all("
        id
        FROM support_asset
        WHERE id_support = $id_support
          AND (deleted IS NULL OR deleted = 0)
    ") as $asset)
        if (support_asset_intercom_unread_count($asset["id"]) > 0)
            $count += 1;
    $cache[$id_support] = $count;
    return ($count);
}

function support_asset_intercom_unread_assets($limit = 60)
{
    global $Language;

    $limit = max(1, (int)$limit);
    $field = $Language."_name";
    $visible = support_asset_intercom_visible_asset_sql_condition("support_asset");
    $rows = db_select_all("
        support_asset.id,
        support_asset.codename,
        support_asset.$field as name,
        support.id as id_support,
        support.codename as support_codename,
        support.$field as support_name,
        support_category.id as id_support_category,
        support_category.codename as category_codename,
        support_category.$field as category_name,
        (
            SELECT MAX(item.post_date)
            FROM message as root
            LEFT JOIN message as item
              ON item.id = root.id OR item.id_message = root.id
            WHERE root.misc_type = 'support_asset'
              AND root.id_misc = support_asset.id
              AND root.id_message IS NULL
        ) as last_post_date
        FROM support_asset
        LEFT JOIN support ON support.id = support_asset.id_support
        LEFT JOIN support_category ON support_category.id = support.id_support_category
        WHERE (support_asset.deleted IS NULL OR support_asset.deleted = 0)
          AND support.id IS NOT NULL
          AND (support.deleted IS NULL OR support.deleted = 0)
          AND support_category.id IS NOT NULL
          AND (support_category.deleted IS NULL OR support_category.deleted = 0)
          AND $visible
          AND EXISTS (
              SELECT 1
              FROM message as root
              WHERE root.misc_type = 'support_asset'
                AND root.id_misc = support_asset.id
                AND root.id_message IS NULL
          )
        ORDER BY last_post_date DESC, support_asset.id DESC
        LIMIT 500
    ");

    $out = [];
    foreach ($rows as $row)
    {
        $unread = support_asset_intercom_unread_count($row["id"]);
        if ($unread <= 0)
            continue ;
        $row["unread_count"] = $unread;
        $row["label"] = support_asset_intercom_name_from_row($row);
        $category = trim((string)try_get($row, "category_name", ""));
        if ($category == "")
            $category = trim((string)try_get($row, "category_codename", ""));
        $row["subtitle"] = $category;
        $out[] = $row;
        if (count($out) >= $limit)
            break ;
    }
    return ($out);
}

function intercom_school_staff_access($id_school)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    $uid = (int)$User["id"];
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return (false);
    if (db_select_one("
        id FROM user_school
        WHERE id_user = $uid
          AND id_school = $id_school
          AND authority > 0
    ") != NULL)
        return (true);
    if (db_select_one("
        cycle_teacher.id
        FROM cycle_teacher
        LEFT JOIN school_cycle ON school_cycle.id_cycle = cycle_teacher.id_cycle
        LEFT JOIN user_laboratory as cycle_laboratory
          ON cycle_laboratory.id_laboratory = cycle_teacher.id_laboratory
         AND cycle_laboratory.id_user = $uid
         AND cycle_laboratory.authority >= 1
        WHERE school_cycle.id_school = $id_school
          AND (cycle_teacher.id_user = $uid OR cycle_laboratory.id_user = $uid)
    ") != NULL)
        return (true);
    if (db_select_one("
        activity_teacher.id
        FROM activity_teacher
        LEFT JOIN activity_cycle ON activity_cycle.id_activity = activity_teacher.id_activity
        LEFT JOIN school_cycle ON school_cycle.id_cycle = activity_cycle.id_cycle
        LEFT JOIN user_laboratory as activity_laboratory
          ON activity_laboratory.id_laboratory = activity_teacher.id_laboratory
         AND activity_laboratory.id_user = $uid
         AND activity_laboratory.authority >= 1
        WHERE school_cycle.id_school = $id_school
          AND (activity_teacher.id_user = $uid OR activity_laboratory.id_user = $uid)
    ") != NULL)
        return (true);
    if (db_select_one("
        user_laboratory.id
        FROM user_laboratory
        LEFT JOIN school_laboratory
          ON school_laboratory.id_laboratory = user_laboratory.id_laboratory
        WHERE user_laboratory.id_user = $uid
          AND user_laboratory.authority >= 1
          AND school_laboratory.id_school = $id_school
    ") != NULL)
        return (true);
    return (false);
}

function intercom_school_access($id_school)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    $uid = (int)$User["id"];
    $id_school = (int)$id_school;
    if (db_select_one("
        id FROM user_school
        WHERE id_user = $uid
          AND id_school = $id_school
    ") != NULL)
        return (true);
    if (db_select_one("
        user_cycle.id
        FROM user_cycle
        LEFT JOIN school_cycle ON school_cycle.id_cycle = user_cycle.id_cycle
        WHERE user_cycle.id_user = $uid
          AND school_cycle.id_school = $id_school
    ") != NULL)
        return (true);
    return (intercom_school_staff_access($id_school));
}

function intercom_can_moderate_school($id_school)
{
    if (is_admin())
        return (true);
    if (function_exists("is_director_for_school") && is_director_for_school($id_school))
        return (true);
    return (intercom_school_staff_access($id_school));
}

function intercom_context_name($misc_type, $id_misc)
{
    global $Language;

    $id_misc = (int)$id_misc;
    if ($misc_type == "laboratory_public")
        return (intercom_laboratory_public_name($id_misc));
    if ($misc_type == "common")
        return (intercom_common_channel_name($id_misc));
    if ($misc_type == "school")
        return ("École — ".intercom_school_name($id_misc));
    if ($misc_type == "school_staff")
        return ("Équipe — ".intercom_school_name($id_misc));
    if ($misc_type == "support_asset")
        return ("Support — ".support_asset_intercom_context_name($id_misc));
    if ($misc_type == "team")
    {
	$team = db_select_one("team_name FROM team WHERE id = $id_misc");
	if ($team && @strlen($team["team_name"]))
	    return ($team["team_name"]);
	return ("team#$id_misc");
    }
    if ($misc_type == "user")
    {
	$user = db_select_one("nickname, codename FROM user WHERE id = $id_misc");
	if ($user == NULL)
	    return ("user#$id_misc");
	if (@strlen($user["nickname"]))
	    return ($user["nickname"]);
	return ($user["codename"]);
    }
    if ($misc_type == "activity")
    {
	$field = $Language."_name";
	$activity = db_select_one("$field as name, codename FROM activity WHERE id = $id_misc");
	if ($activity == NULL)
	    return ("activity#$id_misc");
	if (@strlen($activity["name"]))
	    return ($activity["name"]);
	return ($activity["codename"]);
    }
    if (is_symbol($misc_type)
	&& ($row = db_select_one("* FROM `$misc_type` WHERE id = $id_misc")) != NULL)
    {
	if (isset($row[$Language."_name"]) && @strlen($row[$Language."_name"]))
	    return ($row[$Language."_name"]);
	if (isset($row["name"]) && @strlen($row["name"]))
	    return ($row["name"]);
	if (isset($row["codename"]) && @strlen($row["codename"]))
	    return ($row["codename"]);
    }
    return ($misc_type."#".$id_misc);
}

function intercom_activity_visible_to_current_user($id_activity, $visibility)
{
    $id_activity = (int)$id_activity;
    if ($visibility == INTERCOM_ADMIN)
    {
	if (is_teacher_or_director_for_activity($id_activity)
	    || is_assistant_for_activity($id_activity))
	    return (true);
    }
    else if (is_subscribed_or_assistant($id_activity))
	return (true);

    foreach (db_select_all("
        id
        FROM activity
        WHERE id_template = $id_activity
           OR reference_activity = $id_activity
           OR parent_activity = $id_activity
        LIMIT 100
    ") as $activity)
    {
	if ($visibility == INTERCOM_ADMIN)
	{
	    if (is_teacher_or_director_for_activity($activity["id"])
		|| is_assistant_for_activity($activity["id"]))
		return (true);
	}
	else if (is_subscribed_or_assistant($activity["id"]))
	    return (true);
    }
    return (false);
}

function intercom_team_visible_to_current_user($id_team, $visibility)
{
    if ($visibility == INTERCOM_ADMIN)
	return (is_assistant_for_team($id_team));
    return (is_my_team($id_team) || is_assistant_for_team($id_team));
}

function intercom_subject_visible($subject)
{
    global $User;

    $visibility = $subject["visibility"];
    if ($visibility === NULL)
	$visibility = INTERCOM_PUBLIC;

    if ($subject["misc_type"] == "user")
    {
	if ($visibility == INTERCOM_PRIVATE)
	    return ($subject["id_user"] == $User["id"] || $subject["id_misc"] == $User["id"]);
	if ($visibility == INTERCOM_ADMIN)
	    return (is_director_for_student($subject["id_misc"])
		|| is_cycle_director_for_student($subject["id_misc"])
		|| is_teacher_for_student($subject["id_misc"]));
	return (true);
    }
    if ($subject["misc_type"] == "support_asset")
    {
        if ($visibility == INTERCOM_ADMIN)
            return (support_asset_intercom_can_moderate($subject["id_misc"]));
        return (support_asset_intercom_access($subject["id_misc"]));
    }
    if ($subject["misc_type"] == "activity")
	return (intercom_activity_visible_to_current_user($subject["id_misc"], $visibility));
    if ($subject["misc_type"] == "team")
	return (intercom_team_visible_to_current_user($subject["id_misc"], $visibility));
    if ($visibility == INTERCOM_ADMIN)
	return (is_admin());
    return (true);
}

function intercom_mark_subject_read($id_subject)
{
    global $Database;
    global $User;

    $id_subject = (int)$id_subject;
    $Database->query("\n        UPDATE message_user\n        SET view_date = NOW()\n        WHERE id_user = {$User["id"]}\n          AND id_message = $id_subject\n    ");
    if ($Database->affected_rows == 0)
	$Database->query("\n            INSERT INTO message_user (id_user, id_message, view_date)\n            VALUES ({$User["id"]}, $id_subject, NOW())\n        ");
}

function sort_by_last_message($a, $b)
{
    return (
	date_to_timestamp($b["last_post"]["post_date"])
	-
	date_to_timestamp($a["last_post"]["post_date"])
    );
}

function get_intercomf($misc_type, $id_misc, $conf = [])
{
    $cnf = [
	"id_subject" => -1,
	"recursive" => false,
	"page" => 0,
	"page_size" => 10,
    ];
    $cnf = array_merge($cnf, $conf);
    return (get_intercom(
	$misc_type, $id_misc,
	$cnf["id_subject"],
	$cnf["recursive"],
	$cnf["page"],
	$cnf["page_size"]
    ));
}



function intercom_message_is_root($message)
{
    return (!isset($message["id_message"])
        || $message["id_message"] == NULL
        || (int)$message["id_message"] == -1);
}

function intercom_context_from_message($message)
{
    if ($message == NULL)
        return (NULL);
    if (!intercom_message_is_root($message))
    {
        $root = db_select_one("* FROM message WHERE id = ".((int)$message["id_message"]));
        if ($root != NULL)
            return ($root);
    }
    return ($message);
}


function intercom_laboratory_authority_for_current_user($id_laboratory)
{
    global $User;

    if (!$User)
        return (0);
    if (is_admin())
        return (3);
    $id_laboratory = (int)$id_laboratory;
    $row = db_select_one("
        authority
        FROM user_laboratory
        WHERE id_user = ".((int)$User["id"])."
          AND id_laboratory = $id_laboratory
    ");
    if ($row == NULL)
        return (0);
    return ((int)$row["authority"]);
}

function intercom_can_moderate_laboratory($id_laboratory)
{
    return (intercom_laboratory_authority_for_current_user($id_laboratory) >= 2);
}

function intercom_can_moderate_team($id_team)
{
    $id_team = (int)$id_team;
    if (function_exists("is_assistant_for_team") && is_assistant_for_team($id_team))
        return (true);
    $team = db_select_one("id_activity FROM team WHERE id = $id_team");
    if ($team == NULL)
        return (false);
    return (intercom_can_moderate_context("activity", $team["id_activity"]));
}

function intercom_can_moderate_context($misc_type, $id_misc)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    $id_misc = (int)$id_misc;
    if ($misc_type == "common")
        return (is_admin());
    if ($misc_type == "school")
        return (intercom_can_moderate_school($id_misc));
    if ($misc_type == "school_staff")
        return (intercom_school_staff_access($id_misc));
    if ($misc_type == "support_asset")
        return (support_asset_intercom_can_moderate($id_misc));
    if ($misc_type == "activity")
        return (is_teacher_or_director_for_activity($id_misc)
            || is_assistant_for_activity($id_misc));
    if ($misc_type == "session")
        return (is_teacher_or_director_for_session($id_misc)
            || is_assistant_for_session($id_misc));
    if ($misc_type == "cycle")
        return (is_director_for_cycle($id_misc));
    if ($misc_type == "laboratory_public")
        return (intercom_can_moderate_laboratory_public($id_misc));
    if ($misc_type == "laboratory")
        return (intercom_can_moderate_laboratory($id_misc));
    if ($misc_type == "team")
        return (intercom_can_moderate_team($id_misc));
    if ($misc_type == "user")
        return (is_director_for_student($id_misc)
            || is_cycle_director_for_student($id_misc)
            || is_teacher_for_student($id_misc));
    return (false);
}

function intercom_can_moderate_message($id_message)
{
    $message = db_select_one("* FROM message WHERE id = ".((int)$id_message));
    if ($message == NULL)
        return (false);
    $context = intercom_context_from_message($message);
    if ($context == NULL)
        return (false);
    return (intercom_can_moderate_context($context["misc_type"], $context["id_misc"]));
}

function intercom_message_is_moderated($message)
{
    if (isset($message["is_moderated"]))
        return ((bool)$message["is_moderated"]);
    if (!isset($message["id"]))
        return (false);
    return (db_select_one("
        id FROM message_report
        WHERE id_message = ".((int)$message["id"])."
        AND status = -1
    ") != NULL);
}

function intercom_report_count($id_message)
{
    $report = db_select_one("
        COUNT(*) as cnt FROM message_report
        WHERE id_message = ".((int)$id_message)."
        AND status = 0
    ");
    if ($report == NULL)
        return (0);
    return ((int)$report["cnt"]);
}

function intercom_reported_by_user($id_message)
{
    global $User;

    if (!$User)
        return (false);
    return (db_select_one("
        id FROM message_report
        WHERE id_message = ".((int)$id_message)."
        AND id_user = ".((int)$User["id"])."
        AND status = 0
    ") != NULL);
}

function intercom_prepare_message_for_display(&$message)
{
    global $Dictionnary;

    $context = intercom_context_from_message($message);
    if ($context == NULL)
        return ;
    $can_moderate = intercom_can_moderate_context($context["misc_type"], $context["id_misc"]);
    $message["can_moderate"] = $can_moderate;
    $message["is_moderated"] = intercom_message_is_moderated($message);
    $message["reported_by_user"] = intercom_reported_by_user($message["id"]);
    $message["report_count"] = $can_moderate ? intercom_report_count($message["id"]) : 0;
    $message["hidden_for_viewer"] = $message["is_moderated"] && !$can_moderate;
    if ($message["hidden_for_viewer"])
    {
        // Fallback defensif: les templates recents sautent entierement ces messages.
        // Ces libelles ne doivent donc apparaitre que si un vieux fragment les rend encore.
        if (isset($message["title"]))
            $message["title"] = $Dictionnary["ModeratedIntercomSubject"];
        if (isset($message["message"]))
            $message["message"] = $Dictionnary["ModeratedIntercomMessage"];
    }
}



function intercom_message_hidden_for_viewer($message)
{
    if (!isset($message["id"]))
        return (false);
    if (isset($message["can_moderate"]) && $message["can_moderate"])
        return (false);
    if (isset($message["hidden_for_viewer"]) && $message["hidden_for_viewer"])
        return (true);
    if (!intercom_message_is_moderated($message))
        return (false);
    return (!intercom_can_moderate_message($message["id"]));
}

function intercom_filter_hidden_messages_for_viewer(&$subject)
{
    $viewer_can_moderate = false;
    if (isset($subject["id"]))
        $viewer_can_moderate = intercom_can_moderate_message($subject["id"]);
    else if (isset($subject["can_moderate"]))
        $viewer_can_moderate = $subject["can_moderate"];
    if ($viewer_can_moderate)
        return ;

    if (isset($subject["message"]) && is_array($subject["message"]))
    {
        $messages = [];
        foreach ($subject["message"] as $message)
        {
            if (isset($message["hidden_for_viewer"]) && $message["hidden_for_viewer"])
                continue ;
            $messages[] = $message;
        }
        $subject["message"] = $messages;
    }

    if (!isset($subject["id"]))
        return ;

    $id_parent = (int)$subject["id"];
    $visible_count = db_select_one("
        COUNT(*) as cnt
        FROM message
        WHERE id_message = $id_parent
          AND NOT EXISTS (
              SELECT id
              FROM message_report
              WHERE message_report.id_message = message.id
                AND message_report.status = -1
          )
    ");
    if ($visible_count != NULL)
        $subject["nbr_message"] = (int)$visible_count["cnt"];

    $visible_last = db_select_one("
        id, post_date, id_user
        FROM message
        WHERE id_message = $id_parent
          AND NOT EXISTS (
              SELECT id
              FROM message_report
              WHERE message_report.id_message = message.id
                AND message_report.status = -1
          )
        ORDER BY post_date DESC, id DESC
    ");
    if ($visible_last != NULL)
    {
        $subject["last_post"] = $visible_last;
        $subject["last_message_id"] = (int)$visible_last["id"];
    }
    else
    {
        $subject["last_post"] = [
            "id" => $subject["id"],
            "id_user" => $subject["id_user"],
            "post_date" => $subject["post_date"],
        ];
        $subject["last_message_id"] = (int)$subject["id"];
    }
}

function intercom_report_message($id_message, $reason = "")
{
    global $Database;
    global $User;

    if (!$User)
        return (new ErrorResponse("Forbidden"));
    $id_message = (int)$id_message;
    if (($message = db_select_one("* FROM message WHERE id = $id_message")) == NULL)
        return (new ErrorResponse("NotFound"));
    if (intercom_can_moderate_message($id_message))
        return (new ErrorResponse("ModeratorCannotReport"));
    if (intercom_message_is_moderated($message))
        return (new ErrorResponse("MessageAlreadyModerated"));
    $reason = trim(strip_tags($reason));
    $reason = $Database->real_escape_string($reason);
    $check = db_select_one("
        id FROM message_report
        WHERE id_message = $id_message
        AND id_user = ".((int)$User["id"])."
        AND status = 0
    ");
    if ($check == NULL)
        $Database->query("
            INSERT INTO message_report (id_message, id_user, status, reason, postdate)
            VALUES ($id_message, ".((int)$User["id"]).", 0, '$reason', NOW())
        ");
    else
        $Database->query("
            UPDATE message_report
            SET reason = '$reason', postdate = NOW()
            WHERE id = ".((int)$check["id"])."
        ");
    return (new ValueResponse(["msg" => "MessageReported"]));
}


function intercom_close_message_reports($id_message)
{
    global $Database;

    $id_message = (int)$id_message;
    if (!intercom_can_moderate_message($id_message))
        return (new ErrorResponse("Forbidden"));
    if (db_select_one("id FROM message WHERE id = $id_message") == NULL)
        return (new ErrorResponse("NotFound"));
    $Database->query("
        UPDATE message_report SET status = 3
        WHERE id_message = $id_message
        AND status = 0
    ");
    return (new ValueResponse(["msg" => "IntercomReportsClosed"]));
}

function intercom_set_message_moderation($id_message, $moderated)
{
    global $Database;
    global $User;

    $id_message = (int)$id_message;
    if (!intercom_can_moderate_message($id_message))
        return (new ErrorResponse("Forbidden"));
    if (db_select_one("id FROM message WHERE id = $id_message") == NULL)
        return (new ErrorResponse("NotFound"));
    if ($moderated)
    {
        $check = db_select_one("
            id FROM message_report
            WHERE id_message = $id_message
            AND status = -1
        ");
        if ($check == NULL)
            $Database->query("
                INSERT INTO message_report (id_message, id_user, status, reason, postdate)
                VALUES ($id_message, ".((int)$User["id"]).", -1, 'moderated', NOW())
            ");
        else
            $Database->query("
                UPDATE message_report
                SET id_user = ".((int)$User["id"]).", postdate = NOW()
                WHERE id = ".((int)$check["id"])."
            ");
        $Database->query("
            UPDATE message_report SET status = 1
            WHERE id_message = $id_message
            AND status = 0
        ");
    }
    else
        $Database->query("
            UPDATE message_report SET status = 2
            WHERE id_message = $id_message
            AND status = -1
        ");
    return (new ValueResponse(["msg" => $moderated ? "MessageModerated" : "MessageRestored"]));
}

function get_intercom($misc_type, $id_misc, $id_subject = -1, $recursive = false, $page = NULL, $page_size = NULL)
{
    global $User;
    global $Database;

    $misc_type = intercom_escape($misc_type);
    $id_misc = (int)$id_misc;
    $id_subject = (int)$id_subject;
    $subject_filter = $id_subject != -1 ? " AND message.id = $id_subject " : "";

    if ($page === NULL || $page_size === NULL)
    {
	$page = 0;
	$page_size = 10;
    }
    $page = (int)$page;
    $page_size = max(1, (int)$page_size);

    if ($page < 0)
    {
	if ($id_subject != -1)
	    $cnt = db_select_one("\n                COUNT(*) as cnt\n                FROM message\n                WHERE message.id_message = $id_subject\n            ")["cnt"];
	else
	    $cnt = db_select_one("\n                COUNT(*) as cnt\n                FROM message\n                WHERE message.misc_type = '$misc_type'\n                  AND message.id_misc = $id_misc\n                  AND message.id_message IS NULL\n                  $subject_filter\n            ")["cnt"];
	$page = (int)($cnt / $page_size);
	if ($cnt % $page_size == 0 && $page > 0)
	    $page -= 1;
    }
    $offset = $page * $page_size;
    $pagesql = " LIMIT $offset, $page_size ";

    $view_join = "\n        LEFT JOIN (\n            SELECT id_message, MAX(view_date) as view_date\n            FROM message_user\n            WHERE id_user = {$User["id"]}\n            GROUP BY id_message\n        ) as message_user\n          ON message_user.id_message = message.id\n    ";

    $subjectsx = db_select_all("\n        message.*, message_user.view_date\n        FROM message\n        $view_join\n        WHERE message.misc_type = '$misc_type'\n          AND message.id_misc = $id_misc\n          AND message.id_message IS NULL\n          $subject_filter\n        ORDER BY COALESCE((\n            SELECT MAX(child.post_date)\n            FROM message as child\n            WHERE child.id_message = message.id\n        ), message.post_date) DESC\n        $pagesql\n    ");

    if ($id_subject != -1 && $recursive)
	$nbr_post = db_select_one("\n            COUNT(*) as cnt\n            FROM message\n            WHERE message.id_message = $id_subject\n        ")["cnt"];
    else
	$nbr_post = db_select_one("\n            COUNT(*) as cnt\n            FROM message\n            WHERE message.misc_type = '$misc_type'\n              AND message.id_misc = $id_misc\n              AND message.id_message IS NULL\n              $subject_filter\n        ")["cnt"];

    $labs = [];
    foreach (get_user_laboratories($User)["laboratories"] as $lab)
	$labs[$lab["id"]] = true;

    $content_hash = "";
    $subjects = [];
    foreach ($subjectsx as $subject)
    {
	if (!intercom_subject_visible($subject))
	    continue ;
	if (!is_admin()
	    && $subject["id_laboratory"] !== NULL
	    && !isset($labs[$subject["id_laboratory"]]))
	    continue ;

	$id_parent = (int)$subject["id"];
	$subject["message"] = ($offset == 0) ? [$subject] : [];
	if ($recursive)
	{
	    $subject["message"] = array_merge($subject["message"], db_select_all("\n                *\n                FROM message\n                WHERE id_message = $id_parent\n                ORDER BY post_date ASC\n                $pagesql\n            "));
	    intercom_mark_subject_read($id_parent);
	}
	$subject["nbr_message"] = db_select_one("\n            COUNT(*) as cnt\n            FROM message\n            WHERE id_message = $id_parent\n        ")["cnt"];
	if ($subject["nbr_message"])
	{
	    $subject["last_post"] = db_select_one("\n                id, post_date, id_user\n                FROM message\n                WHERE id_message = $id_parent\n                ORDER BY post_date DESC, id DESC\n            ");
	    $subject["last_message_id"] = (int)$subject["last_post"]["id"];
	}
	else
	{
	    $subject["last_post"] = [
		"id" => $subject["id"],
		"id_user" => $subject["id_user"],
		"post_date" => $subject["post_date"],
	    ];
	    $subject["last_message_id"] = (int)$subject["id"];
	}

	$content_hash .= $subject["id"]."/".$subject["nbr_message"]."/".$subject["last_post"]["post_date"]."/".$subject["last_message_id"];
	if (!$recursive)
	    if (function_exists("intercom_prepare_message_for_display"))
	    {
	    	foreach ($subject["message"] as &$message)
	    		intercom_prepare_message_for_display($message);
	    	unset($message);
	    	intercom_prepare_message_for_display($subject);
        intercom_filter_hidden_messages_for_viewer($subject);
    /* intercom_hidden_root_subject_filter */
    if (isset($subject["is_moderated"]) && $subject["is_moderated"]
        && (!isset($subject["can_moderate"]) || !$subject["can_moderate"]))
        continue ;
	    }

	    $content_hash .=
	    	$subject["id"]."/".
	    	$subject["nbr_message"]."/".
	    	$subject["last_post"]["post_date"]."/".
	    	$subject["last_message_id"]."/".
	    	(isset($subject["is_moderated"]) && $subject["is_moderated"] ? 1 : 0)."/".
	    	(isset($subject["reported_by_user"]) && $subject["reported_by_user"] ? 1 : 0)."/".
	    	(isset($subject["report_count"]) ? (int)$subject["report_count"] : 0);
	    if (!$recursive)
	    	$content_hash .= "/".$subject["view_date"];
	    foreach ($subject["message"] as $message)
	    	$content_hash .=
	    	    "/".$message["id"].".".
	    	    (isset($message["is_moderated"]) && $message["is_moderated"] ? 1 : 0).".".
	    	    (isset($message["reported_by_user"]) && $message["reported_by_user"] ? 1 : 0).".".
	    	    (isset($message["report_count"]) ? (int)$message["report_count"] : 0);

	$subject["page"] = $page;
	$subject["page_size"] = $page_size;
	$subject["on_last_page"] = ($offset + $page_size) >= $subject["nbr_message"];
	$subject["page_count"] = max(1, (int)ceil(max(1, (int)$subject["nbr_message"]) / $page_size));
	$subjects[] = $subject;
    }

    uasort($subjects, "sort_by_last_message");
    $subjects = array_values($subjects);

    return ([
	"id_misc" => $id_misc,
	"misc_type" => $misc_type,
	"name" => intercom_context_name($misc_type, $id_misc),
	"subjects" => $subjects,
	"content_hash" => hash("md5", $content_hash),
	"page" => $page,
	"page_size" => $page_size,
	"on_last_page" => ($offset + $page_size) >= $nbr_post,
    ]);
}


function intercom_laboratory_public_name($id_laboratory)
{
    global $Language;
    global $Dictionnary;

    $id_laboratory = (int)$id_laboratory;
    $field = $Language."_name";
    $lab = db_select_one("\n        codename, $field as name\n        FROM laboratory\n        WHERE id = $id_laboratory\n    ");
    if ($lab == NULL)
        return ("laboratory_public#$id_laboratory");
    $name = isset($lab["name"]) && strlen($lab["name"]) ? $lab["name"] : $lab["codename"];
    $suffix = isset($Dictionnary["IntercomLaboratoryPublicSuffix"])
        ? $Dictionnary["IntercomLaboratoryPublicSuffix"]
        : "public";
    return ($name." (".$suffix.")");
}

function intercom_laboratory_public_access($id_laboratory)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    $uid = (int)$User["id"];
    $id_laboratory = (int)$id_laboratory;
    if ($id_laboratory <= 0)
        return (false);
    if (function_exists("is_member_of_laboratory") && is_member_of_laboratory($id_laboratory))
        return (true);
    if (db_select_one("\n        id FROM user_laboratory\n        WHERE id_user = $uid\n          AND id_laboratory = $id_laboratory\n    ") != NULL)
        return (true);
    if (db_select_one("\n        school_laboratory.id_laboratory\n        FROM school_laboratory\n        LEFT JOIN user_school\n          ON user_school.id_school = school_laboratory.id_school\n         AND user_school.id_user = $uid\n        LEFT JOIN school_cycle\n          ON school_cycle.id_school = school_laboratory.id_school\n        LEFT JOIN user_cycle\n          ON user_cycle.id_cycle = school_cycle.id_cycle\n         AND user_cycle.id_user = $uid\n        WHERE school_laboratory.id_laboratory = $id_laboratory\n          AND (user_school.id_user IS NOT NULL OR user_cycle.id_user IS NOT NULL)\n    ") != NULL)
        return (true);
    return (false);
}

function intercom_can_moderate_laboratory_public($id_laboratory)
{
    global $User;

    if (!$User)
        return (false);
    if (is_admin())
        return (true);
    $id_laboratory = (int)$id_laboratory;
    $uid = (int)$User["id"];
    return (db_select_one("\n        id FROM user_laboratory\n        WHERE id_user = $uid\n          AND id_laboratory = $id_laboratory\n    ") != NULL);
}

function intercom_get($table, $id_misc, $ref = -1, $id = -1)
{
    if (($id_misc = resolve_codename($table == "laboratory_public" ? "laboratory" : $table, $id_misc))->is_error())
	return ($id_misc);
    $intercom = get_intercom($table, $id_misc->value, $id == -1 ? $ref : $id, $ref != -1);
    return (new ValueResponse($intercom["subjects"]));
}

function intercom_add_com($table, $id_misc, $ref, $msg)
{
    global $Database;
    global $User;

    if (($id_misc = resolve_codename($table == "laboratory_public" ? "laboratory" : $table, $id_misc))->is_error())
	return ($id_misc);
    $id_misc = (int)$id_misc->value;
    $ref = (int)$ref;
    if ($ref == -1)
	return (new ErrorResponse("NotAnId", $ref));
    $up = db_select_one("\n        *\n        FROM message\n        WHERE id = $ref\n          AND id_message IS NULL\n    ");
    if ($up == NULL || !intercom_subject_visible($up))
	return (new ErrorResponse("IntercomDenied"));
    $msg = intercom_message_text($msg);
    if (strlen($msg) < 2)
	return (new ErrorResponse("InvalidRequest"));
    $Database->query("\n        INSERT INTO message\n        (id_user, id_laboratory, visibility, misc_type, id_misc, id_message, message)\n        VALUES\n        ({$User["id"]}, ".($up["id_laboratory"] === NULL ? "NULL" : (int)$up["id_laboratory"]).",\n         ".($up["visibility"] === NULL ? "NULL" : (int)$up["visibility"]).",\n         '{$up["misc_type"]}', {$up["id_misc"]}, $ref, '$msg')\n    ");
    intercom_mark_subject_read($ref);
    return (new ValueResponse($Database->insert_id));
}

function intercom_add_topic($table, $id_misc, $title, $msg, $visibility = NULL, $labs = NULL)
{
    global $Database;
    global $User;

    if (($id_misc = resolve_codename($table == "laboratory_public" ? "laboratory" : $table, $id_misc))->is_error())
	return ($id_misc);
    $id_misc = (int)$id_misc->value;
    if (($visibility = intercom_visibility($table, $visibility)) === NULL)
	return (new ErrorResponse("InvalidParameter", $visibility));
    if ($visibility == INTERCOM_ADMIN && !is_admin())
	return (new ErrorResponse("InvalidParameter", $visibility));
    if (($labs = intercom_laboratory_sql($labs)) instanceof Response)
	return ($labs);

    $title = intercom_message_text(str_replace("\n", " ", $title));
    $msg = intercom_message_text($msg);
    if (strlen($title) < 3 || strlen($msg) < 2)
	return (new ErrorResponse("InvalidRequest"));

    $Database->query("\n        INSERT INTO message\n        (id_user, id_laboratory, visibility, misc_type, id_misc, id_message, title, message)\n        VALUES\n        ({$User["id"]}, $labs, $visibility, '$table', $id_misc, NULL, '$title', '$msg')\n    ");
    $id_message = $Database->insert_id;
    intercom_mark_subject_read($id_message);
    return (new ValueResponse($id_message));
}

function intercom_handle_request($table, $id_misc)
{
    if (!isset($_POST["action"]))
	return (new Response);
    if ($_POST["action"] != "add_message" || !isset($_POST["ref"]) || !isset($_POST["message"]))
	return (new ErrorResponse("InvalidRequest"));
    if ($_POST["ref"] == -1)
    {
	if (!isset($_POST["title"]))
	    return (new ErrorResponse("InvalidRequest"));
	return (intercom_add_topic(
	    $table,
	    $id_misc,
	    $_POST["title"],
	    $_POST["message"],
	    try_get($_POST, "visibility", NULL),
	    try_get($_POST, "laboratory", NULL)
	));
    }
    return (intercom_add_com($table, $id_misc, $_POST["ref"], $_POST["message"]));
}

function intercom_display($table, $id_misc, $public = false, $ref = -1, $labs = true)
{
    global $Dictionnary;
    global $User;

    if ($table == "common")
    {
        $id_misc = (int)$id_misc;
        if (!function_exists("intercom_common_channel_definition")
            || intercom_common_channel_definition($id_misc) == NULL)
        {
            echo $Dictionnary["IntercomIsCurrentlyNotAvailable"];
            return (new ErrorResponse("IntercomDenied"));
        }
    }
    else
    {
        $resolve_table = $table == "school_staff" ? "school" : ($table == "laboratory_public" ? "laboratory" : $table);
        if (($ret = resolve_codename($resolve_table, $id_misc))->is_error())
        {
            echo $Dictionnary["IntercomIsCurrentlyNotAvailable"];
            return ($ret);
        }
        $id_misc = (int)$ret->value;
    }
    $ref = try_get($_GET, "ref", $ref);
    $ref = (int)$ref;
    $intercom = get_intercomf($table, $id_misc, [
	"id_subject" => $ref,
	"recursive" => $ref != -1,
	"page" => try_get($_GET, "page", 0),
	"page_size" => 10,
    ]);
    if ($ref != -1 && count($intercom["subjects"]) == 0)
    {
	echo $Dictionnary["IntercomDenied"];
	return (new Response);
    }
    $intercom["div"] = $table."_".$id_misc."_intercom";
    $intercom["base_url"] = "/api/intercom/".$intercom["id_misc"]."/".$intercom["misc_type"];
    $intercom["default_visibility"] = $public ? INTERCOM_PUBLIC : intercom_visibility($table);
    $intercom["allow_visibility"] = !$public && is_admin();
    $intercom["allow_laboratory"] = $labs !== false;
    ?>
    <script>
     setTimeout(check_update, 5000, "<?=$intercom["div"]; ?>");
    </script>
    <div
	id="<?=$intercom["div"]; ?>"
	style="position: relative; min-height: 360px; height: 55vh; width: 100%;"
    >
	<?php
	if ($ref == -1)
	    require ("./tools/template/intercom_subject_page.phtml");
	else
	    require ("./tools/template/intercom_message_page.phtml");
	?>
    </div>
    <?php
    return (new Response);
}
