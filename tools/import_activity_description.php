<?php

function import_activity_description_normalize_key($key)
{
    if (is_int($key) || is_numeric($key))
	return ($key);
    $key = str_replace(["-", " "], "_", $key);
    if (strtolower($key) !== $key)
	$key = preg_replace('/(?<!^)[A-Z]/', '_$0', $key);
    $key = strtolower($key);
    $key = preg_replace('/__+/', '_', $key);
    return (trim($key, "_"));
}

function import_activity_description_normalize($data)
{
    if (!is_array($data))
	return ($data);
    $out = [];
    foreach ($data as $key => $value)
	$out[import_activity_description_normalize_key($key)] =
	    import_activity_description_normalize($value);
    return ($out);
}

function import_activity_description_value(array $data, $keys, $default = "")
{
    if (!is_array($keys))
	$keys = [$keys];
    foreach ($keys as $key)
    {
	$key = import_activity_description_normalize_key($key);
	if (array_key_exists($key, $data))
	    return ($data[$key]);
    }
    return ($default);
}

function import_activity_description_is_empty($value)
{
    return ($value === NULL || $value === "" || $value === []);
}

function import_activity_description_scalar($value, $default = "")
{
    if (is_array($value))
	return ($default);
    if ($value === NULL)
	return ($default);
    return ($value);
}

function import_activity_description_int($value, $default = NULL)
{
    if (import_activity_description_is_empty($value))
	return ($default);
    return ((int)$value);
}

function import_activity_description_subscription($value)
{
    if (import_activity_description_is_empty($value))
	return (0);
    if (is_number($value))
	return ((int)$value);
    $values = [
	"manual" => 0,
	"mandatory" => 1,
	"automatic" => 2
    ];
    $value = strtolower(trim($value));
    if (isset($values[$value]))
	return ($values[$value]);
    return ((int)$value);
}

function import_activity_description_date(array $data, $short_name)
{
    $dates = import_activity_description_value($data, "date", []);
    if (is_array($dates))
    {
	$value = import_activity_description_value($dates, $short_name, NULL);
	if (!import_activity_description_is_empty($value))
	    return ($value);
    }
    $value = import_activity_description_value($data, $short_name."_date", NULL);
    if (!import_activity_description_is_empty($value))
	return ($value);
    return (NULL);
}

function import_activity_description_uploaded_file($field = "description_file")
{
    if (!isset($_FILES[$field]) || !isset($_FILES[$field]["tmp_name"]))
	return (new ErrorResponse("MissingFile"));
    if ($_FILES[$field]["error"] != UPLOAD_ERR_OK)
	return (new ErrorResponse("InvalidFile", $_FILES[$field]["name"]));
    if (!is_uploaded_file($_FILES[$field]["tmp_name"]) && !file_exists($_FILES[$field]["tmp_name"]))
	return (new ErrorResponse("InvalidFile", $_FILES[$field]["name"]));
    if (($conf = load_configuration($_FILES[$field]["tmp_name"]))->is_error())
	return ($conf);
    if (!is_array($conf->value))
	return (new ErrorResponse("InvalidFile", $_FILES[$field]["name"]));
    return (new ValueResponse(import_activity_description_normalize($conf->value)));
}

function import_activity_description_collect_codenames(array $data, array &$codenames, $path = "")
{
    $codename = import_activity_description_scalar(
	import_activity_description_value($data, "codename", "")
    );
    if ($codename != "")
    {
	if (isset($codenames[$codename]))
	    return (new ErrorResponse("DuplicateCodeName", $codenames[$codename]." / ".$path." ($codename)"));
	$codenames[$codename] = $path;
    }
    $activities = import_activity_description_value($data, "activities", []);
    if (is_array($activities))
    {
	$i = 0;
	foreach ($activities as $activity)
	{
	    if (is_array($activity))
	    {
		if (($ret = import_activity_description_collect_codenames(
		    $activity, $codenames, $path."/activities[$i]"
		))->is_error())
		    return ($ret);
	    }
	    $i += 1;
	}
    }
    return (new Response);
}

function import_activity_description_check_conflicts(array $data)
{
    $codenames = [];
    if (($ret = import_activity_description_collect_codenames($data, $codenames, "root"))->is_error())
	return ($ret);
    foreach ($codenames as $codename => $path)
    {
	$ret = resolve_codename("activity", $codename, "codename", true);
	if (!$ret->is_error())
	    return (new ErrorResponse("CodeNameAlreadyUsed", "$path: $codename"));
	if ($ret->label != "BadCodeName")
	    return ($ret);
    }
    return (new Response);
}

function import_activity_description_activity_fields(array $data, $parent_activity, $is_template)
{
    global $LanguageList;

    $fields = [];
    $fields["codename"] = import_activity_description_scalar(
	import_activity_description_value($data, "codename", "")
    );
    if ($fields["codename"] == "")
	return (new ErrorResponse("MissingCodeName"));
    $fields["parent_activity"] = $parent_activity;

    foreach ([
	"type", "min_team_size", "max_team_size", "hidden", "mandatory",
	"maximum_subscription", "money", "repository_name",
	"estimated_work_duration", "automatic_correction_frequency",
	"slot_duration", "validation", "credit_a", "credit_b", "credit_c",
	"credit_d", "allow_unregistration"
    ] as $key)
    {
	$value = import_activity_description_value($data, $key, NULL);
	if (!import_activity_description_is_empty($value))
	    $fields[$key] = import_activity_description_scalar($value);
    }

    if (!isset($fields["type"]) && $parent_activity === NULL)
	$fields["type"] = 18;

    $fields["subscription"] = import_activity_description_subscription(
	import_activity_description_value($data, "subscription", 0)
    );

    foreach ([
	"emergence", "registration", "close", "subject_appeir",
	"subject_disappeir", "pickup", "done"
    ] as $date)
    {
	$value = import_activity_description_date($data, $date);
	if (!import_activity_description_is_empty($value))
	    $fields[$date."_date"] = $value;
    }

    foreach ($LanguageList as $lng => $unused)
    {
	$scope = import_activity_description_value($data, $lng, []);
	foreach (["name", "description", "objective", "method", "reference"] as $field)
	{
	    $value = NULL;
	    if (is_array($scope))
		$value = import_activity_description_value($scope, $field, NULL);
	    if (import_activity_description_is_empty($value))
		$value = import_activity_description_value($data, $lng."_".$field, NULL);
	    if (!import_activity_description_is_empty($value))
		$fields[$lng."_".$field] = import_activity_description_scalar($value);
	}
    }

    return (new ValueResponse($fields));
}

function import_activity_description_extra_activity_fields(array $data)
{
    $fields = [];
    foreach ([
	"validated", "template_link", "medal_template", "support_template",
	"grade_bonus", "declaration_type"
    ] as $field)
    {
	$value = import_activity_description_value($data, $field, NULL);
	if (!import_activity_description_is_empty($value))
	    $fields[$field] = import_activity_description_scalar($value);
    }
    return ($fields);
}

function import_activity_description_update_row($table, $id, array $fields)
{
    global $Database;

    if (!count($fields))
	return (new Response);
    if (!is_symbol($table))
	return (new ErrorResponse("InvalidTableName", $table));
    $mods = [];
    foreach ($fields as $key => $value)
    {
	if (!is_symbol($key))
	    return (new ErrorResponse("InvalidParameter", $key));
	if ($value === NULL || $value === "")
	    $mods[] = "`$key` = NULL";
	else
	    $mods[] = "`$key` = '".$Database->real_escape_string($value)."'";
    }
    if ($Database->query("UPDATE `$table` SET ".implode(", ", $mods)." WHERE id = ".(int)$id) == false)
	return (new ErrorResponse("InvalidRequest"));
    return (new Response);
}

function import_activity_description_insert_activity(array $data, $parent_activity, $is_template, array &$post_links)
{
    if (($fields = import_activity_description_activity_fields($data, $parent_activity, $is_template))->is_error())
	return ($fields);
    $fields = $fields->value;

    if (($tfields = check_activity_field($fields, [], $is_template, -1))->is_error())
	return ($tfields);
    $tfields = $tfields->value;
    if (!isset($tfields["codename"]))
	return (new ErrorResponse("MissingCodeName"));
    $codename = $tfields["codename"];
    unset($tfields["codename"]);

    $ret = try_insert(
	"activity",
	$codename,
	$tfields,
	"",
	"",
	[
	    "name" => false,
	    "description" => false,
	    "objective" => false,
	    "method" => false,
	    "reference" => false
	],
	$fields
    );
    if ($ret->is_error())
	return ($ret);
    $id = $ret->value["id"];

    if (($ret = import_activity_description_update_row(
	"activity", $id, import_activity_description_extra_activity_fields($data)
    ))->is_error())
	return ($ret);

    if (($ret = import_activity_description_insert_activity_links($id, $data, $post_links))->is_error())
	return ($ret);

    $sessions = import_activity_description_value($data, "sessions", []);
    if (is_array($sessions))
	foreach ($sessions as $session)
	    if (is_array($session))
		if (($ret = import_activity_description_insert_session($session, $id))->is_error())
		    return ($ret);

    $activities = import_activity_description_value($data, "activities", []);
    if (is_array($activities))
	foreach ($activities as $activity)
	    if (is_array($activity))
		if (($ret = import_activity_description_insert_activity(
		    $activity, $id, $is_template, $post_links
		))->is_error())
		    return ($ret);

    $reference = import_activity_description_scalar(
	import_activity_description_value($data, "reference_activity", "")
    );
    if ($reference != "")
	$post_links[] = [
	    "kind" => "reference_activity",
	    "activity" => $id,
	    "target" => $reference
	];

    $support_references = import_activity_description_value($data, "support_references", []);
    if (is_array($support_references))
	foreach ($support_references as $support_reference)
	    if (is_array($support_reference))
		$post_links[] = [
		    "kind" => "support_reference",
		    "activity" => $id,
		    "target" => import_activity_description_scalar(
			import_activity_description_value($support_reference, "activity", "")
		    ),
		    "chapter" => import_activity_description_value($support_reference, "chapter", NULL)
		];

    return (new ValueResponse($id));
}

function import_activity_description_insert_activity_links($activity_id, array $data, array &$post_links)
{
    global $Database;

    $medals = import_activity_description_value($data, "medals", []);
    if (is_array($medals))
    {
	foreach ($medals as $medal)
	{
	    if (!is_array($medal))
		continue ;
	    $codename = import_activity_description_scalar(import_activity_description_value($medal, "medal", ""));
	    if ($codename == "")
		continue ;
	    if (($ret = add_link(
		$activity_id,
		$codename,
		"activity",
		"medal",
		false,
		[
		    "role" => import_activity_description_int(import_activity_description_value($medal, "role", 1), 1),
		    "money" => import_activity_description_int(import_activity_description_value($medal, "money", 0), 0),
		    "local" => import_activity_description_int(import_activity_description_value($medal, "local", 0), 0)
		],
		"activity_medal"
	    ))->is_error())
		return ($ret);
	}
    }

    $skills = import_activity_description_value($data, "skills", []);
    if (is_array($skills))
    {
	foreach ($skills as $skill)
	{
	    if (is_array($skill))
		$skill = import_activity_description_value($skill, "skill", "");
	    $skill = import_activity_description_scalar($skill, "");
	    if ($skill == "")
		continue ;
	    if (($ret = add_link($activity_id, $skill, "activity", "skill"))->is_error())
		return ($ret);
	}
    }

    $softwares = import_activity_description_value($data, "softwares", []);
    if (is_array($softwares))
    {
	foreach ($softwares as $software)
	{
	    if (is_array($software))
	    {
		$name = import_activity_description_scalar(import_activity_description_value($software, "software", ""));
		$type = import_activity_description_int(import_activity_description_value($software, "type", 0), 0);
	    }
	    else
	    {
		$name = import_activity_description_scalar($software, "");
		$type = 0;
	    }
	    if ($name == "")
		continue ;
	    $Database->query("\
	        INSERT INTO activity_software (id_activity, software, type)\
	        VALUES (".(int)$activity_id.", '".$Database->real_escape_string($name)."', ".(int)$type.")\
	    ");
	}
    }

    return (new Response);
}

function import_activity_description_apply_post_links(array $post_links)
{
    global $Database;

    foreach ($post_links as $link)
    {
	if ($link["target"] == "")
	    continue ;
	if (($target = resolve_codename("activity", $link["target"], "codename", true))->is_error())
	    return ($target);
	$target = $target->value["id"];
	if ($link["kind"] == "reference_activity")
	{
	    if (($ret = import_activity_description_update_row(
		"activity", $link["activity"], ["reference_activity" => $target]
	    ))->is_error())
		return ($ret);
	}
	else if ($link["kind"] == "support_reference")
	{
	    $chapter = import_activity_description_int($link["chapter"], NULL);
	    if ($chapter === NULL)
		$chapter = "NULL";
	    else
		$chapter = (int)$chapter;
	    if ($Database->query("\
	        INSERT INTO activity_support (id_activity, id_subactivity, chapter)\
	        VALUES (".(int)$link["activity"].", ".(int)$target.", $chapter)\
	    ") == false)
		return (new ErrorResponse("InvalidRequest"));
	}
    }
    return (new Response);
}

function import_activity_description_session_fields(array $data, $activity_id)
{
    $fields = ["activity" => (int)$activity_id];

    $laboratory = import_activity_description_scalar(import_activity_description_value($data, "laboratory", ""));
    if ($laboratory != "")
    {
	if (($lab = resolve_codename("laboratory", $laboratory))->is_error())
	    return ($lab);
	$fields["laboratory"] = $lab->value;
    }
    $maximum_subscription = import_activity_description_value($data, "maximum_subscription", NULL);
    if (!import_activity_description_is_empty($maximum_subscription))
	$fields["maximum_subscription"] = import_activity_description_scalar($maximum_subscription);

    foreach (["begin", "end"] as $date)
    {
	$value = import_activity_description_date($data, $date);
	if (!import_activity_description_is_empty($value))
	    $fields[$date."_date"] = $value;
    }
    return (new ValueResponse($fields));
}

function import_activity_description_insert_session(array $data, $activity_id)
{
    global $Database;

    if (($fields = import_activity_description_session_fields($data, $activity_id))->is_error())
	return ($fields);
    $fields = $fields->value;
    if (($ret = add_session($fields))->is_error())
	return ($ret);
    $session_id = $Database->insert_id;

    $rooms = import_activity_description_value($data, "rooms", []);
    if (is_array($rooms))
    {
	foreach ($rooms as $room)
	{
	    if (is_array($room))
		$room = import_activity_description_value($room, "room", "");
	    $room = import_activity_description_scalar($room, "");
	    if ($room == "")
		continue ;
	    if (($ret = add_link(
		$session_id, $room, "session", "room", false, [], "session_room"
	    ))->is_error())
		return ($ret);
	}
    }

    $slots = import_activity_description_value($data, "appointment_slots", []);
    if (is_array($slots))
    {
	foreach ($slots as $slot)
	{
	    if (!is_array($slot))
		continue ;
	    $begin = import_activity_description_date($slot, "begin");
	    $end = import_activity_description_date($slot, "end");
	    if (import_activity_description_is_empty($begin) || import_activity_description_is_empty($end))
		continue ;
	    $begin = db_form_date($begin);
	    $end = db_form_date($end);
	    if ($Database->query("\
	        INSERT INTO appointment_slot (id_session, begin_date, end_date)\
	        VALUES (".(int)$session_id.", '".$Database->real_escape_string($begin)."', '".$Database->real_escape_string($end)."')\
	    ") == false)
		return (new ErrorResponse("InvalidRequest"));
	}
    }

    return (new ValueResponse($session_id));
}

function import_activity_description_transaction($callback)
{
    global $Database;

    $Database->query("START TRANSACTION");
    $ret = $callback();
    if ($ret->is_error())
    {
	$Database->query("ROLLBACK");
	return ($ret);
    }
    $Database->query("COMMIT");
    return ($ret);
}

function import_activity_description_import_matter(array $data, $is_template)
{
    $kind = strtolower(import_activity_description_scalar(import_activity_description_value($data, "kind", "matter")));
    if ($kind != "matter" && $kind != "module")
	return (new ErrorResponse("InvalidDabsicKind", $kind));
    if (($ret = import_activity_description_check_conflicts($data))->is_error())
	return ($ret);
    return (import_activity_description_transaction(function () use ($data, $is_template) {
	$post_links = [];
	if (($inserted = import_activity_description_insert_activity($data, NULL, $is_template, $post_links))->is_error())
	    return ($inserted);
	if (($ret = import_activity_description_apply_post_links($post_links))->is_error())
	    return ($ret);
	return (new ValueResponse($inserted->value));
    }));
}

function import_activity_description_import_activity(array $data, $parent_activity)
{
    if (import_activity_description_is_empty($parent_activity))
	return (new ErrorResponse("MissingParameter", "module"));
    if (($parent = resolve_codename("activity", $parent_activity, "codename", true))->is_error())
	return ($parent);
    if ($parent->value["parent_activity"] !== NULL && (int)$parent->value["parent_activity"] != -1)
	return (new ErrorResponse("InvalidDabsicKind", "parent activity"));
    $kind = strtolower(import_activity_description_scalar(import_activity_description_value($data, "kind", "activity")));
    if ($kind == "matter" || $kind == "module")
	return (new ErrorResponse("InvalidDabsicKind", $kind));
    if (($ret = import_activity_description_check_conflicts($data))->is_error())
	return ($ret);
    return (import_activity_description_transaction(function () use ($data, $parent) {
	$post_links = [];
	if (($inserted = import_activity_description_insert_activity(
	    $data, $parent->value["id"], (bool)$parent->value["is_template"], $post_links
	))->is_error())
	    return ($inserted);
	if (($ret = import_activity_description_apply_post_links($post_links))->is_error())
	    return ($ret);
	return (new ValueResponse($inserted->value));
    }));
}

function import_activity_description_import_session(array $data, $activity)
{
    if (import_activity_description_is_empty($activity))
	return (new ErrorResponse("MissingParameter", "activity"));
    if (($activity = resolve_codename("activity", $activity, "codename", true))->is_error())
	return ($activity);
    return (import_activity_description_transaction(function () use ($data, $activity) {
	return (import_activity_description_insert_session($data, $activity->value["id"]));
    }));
}

function ImportModuleDescription($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id != -1)
	bad_request();
    if (($conf = import_activity_description_uploaded_file())->is_error())
	return ($conf);
    if (($ret = import_activity_description_import_matter($conf->value, $module == "template"))->is_error())
	return ($ret);
    return (DisplayModule(-1, [], "GET", $output, $module));
}

function ImportActivityDescription($id, $data, $method, $output, $module)
{
    if ($id != -1)
	bad_request();
    if (($conf = import_activity_description_uploaded_file())->is_error())
	return ($conf);
    if (($ret = import_activity_description_import_activity($conf->value, @$data["module"]))->is_error())
	return ($ret);
    $_GET["sub"] = 1;
    return (DisplayModule($data["module"], [], "GET", $output, $module));
}

function ImportSessionDescription($id, $data, $method, $output, $module)
{
    if ($id != -1)
	bad_request();
    if (($conf = import_activity_description_uploaded_file())->is_error())
	return ($conf);
    if (($ret = import_activity_description_import_session($conf->value, @$data["activity"]))->is_error())
	return ($ret);
    $_GET["sub"] = 1;
    return (DisplayActivity($data["activity"], [], "GET", $output, $module));
}
