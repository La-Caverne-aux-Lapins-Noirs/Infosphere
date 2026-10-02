<?php

function export_activity_description_clean_value($value)
{
    if ($value === NULL)
	return ("");
    return ($value);
}


function export_activity_description_language_fields(array $row, array &$out, array $fields)
{
    global $LanguageList;

    foreach ($LanguageList as $lng => $unused)
    {
	$language = [];
	foreach ($fields as $field)
	{
	    $key = $lng."_".$field;
	    if (array_key_exists($key, $row))
		$language[$field] = export_activity_description_clean_value($row[$key]);
	}
	if (count($language))
	    $out[$lng] = $language;
    }
}

function export_activity_description_date_key($key)
{
    if (substr($key, -5) == "_date")
	$key = substr($key, 0, -5);
    return ($key);
}

function export_activity_description_is_date_key($key)
{
    return (substr($key, -5) == "_date");
}

function export_activity_description_add_date(array &$out, $key, $value)
{
    if (!isset($out["date"]))
	$out["date"] = [];
    $out["date"][export_activity_description_date_key($key)] =
	export_activity_description_clean_value($value);
}

function export_activity_description_add_dates(array $row, array &$out, array $fields)
{
    foreach ($fields as $field)
	if (array_key_exists($field, $row))
	    export_activity_description_add_date($out, $field, $row[$field]);
}

function export_activity_description_subscription($value)
{
    if ($value === NULL || $value === "")
	return ("");
    $values = [
	0 => "manual",
	1 => "mandatory",
	2 => "automatic"
    ];
    if (isset($values[(int)$value]))
	return ($values[(int)$value]);
    return (export_activity_description_clean_value($value));
}

function export_activity_description_activity_row(array $row)
{
    $out = [
	"kind" => (($row["parent_activity"] === NULL || (int)$row["parent_activity"] == -1) ? "matter" : "activity"),
	"codename" => export_activity_description_clean_value($row["codename"]),
	"type" => export_activity_description_clean_value($row["type_codename"]),
	"validated" => (int)$row["validated"],
	"disabled" => export_activity_description_clean_value($row["disabled"]),
	"deleted" => export_activity_description_clean_value($row["deleted"]),
	"is_template" => (int)$row["is_template"],
	"template" => export_activity_description_clean_value($row["template_codename"]),
	"template_link" => (int)$row["template_link"],
	"medal_template" => (int)$row["medal_template"],
	"support_template" => (int)$row["support_template"],
	"parent" => export_activity_description_clean_value($row["parent_codename"]),
	"reference_activity" => export_activity_description_clean_value($row["reference_codename"]),
	"min_team_size" => export_activity_description_clean_value($row["min_team_size"]),
	"max_team_size" => export_activity_description_clean_value($row["max_team_size"]),
	"hidden" => export_activity_description_clean_value($row["hidden"]),
	"mandatory" => export_activity_description_clean_value($row["mandatory"]),
	"maximum_subscription" => export_activity_description_clean_value($row["maximum_subscription"]),
	"money" => export_activity_description_clean_value($row["money"]),
	"subscription" => export_activity_description_subscription($row["subscription"]),
	"repository_name" => export_activity_description_clean_value($row["repository_name"]),
	"estimated_work_duration" => export_activity_description_clean_value($row["estimated_work_duration"]),
	"automatic_correction_frequency" => export_activity_description_clean_value($row["automatic_correction_frequency"]),
	"slot_duration" => export_activity_description_clean_value($row["slot_duration"]),
	"validation" => export_activity_description_clean_value($row["validation"]),
	"credit_a" => export_activity_description_clean_value($row["credit_a"]),
	"credit_b" => export_activity_description_clean_value($row["credit_b"]),
	"credit_c" => export_activity_description_clean_value($row["credit_c"]),
	"credit_d" => export_activity_description_clean_value($row["credit_d"]),
	"grade_a" => export_activity_description_clean_value($row["grade_a"]),
	"grade_b" => export_activity_description_clean_value($row["grade_b"]),
	"grade_c" => export_activity_description_clean_value($row["grade_c"]),
	"grade_d" => export_activity_description_clean_value($row["grade_d"]),
	"grade_bonus" => export_activity_description_clean_value($row["grade_bonus"]),
	"declaration_type" => export_activity_description_clean_value($row["declaration_type"]),
	"allow_unregistration" => export_activity_description_clean_value($row["allow_unregistration"])
    ];
    export_activity_description_add_dates($row, $out, [
	"emergence_date",
	"registration_date",
	"close_date",
	"subject_appeir_date",
	"subject_disappeir_date",
	"pickup_date",
	"done_date"
    ]);
    export_activity_description_language_fields($row, $out, [
	"name", "description", "objective", "method", "reference"
    ]);
    return ($out);
}

function export_activity_description_activity($activity_id, $recursive = true)
{
    $activity_id = (int)$activity_id;
    $row = db_select_one("
        activity.*,
        activity_type.codename as type_codename,
        template.codename as template_codename,
        parent.codename as parent_codename,
        reference.codename as reference_codename
        FROM activity
        LEFT JOIN activity_type ON activity_type.id = activity.type
        LEFT JOIN activity AS template ON template.id = activity.id_template
        LEFT JOIN activity AS parent ON parent.id = activity.parent_activity
        LEFT JOIN activity AS reference ON reference.id = activity.reference_activity
        WHERE activity.id = $activity_id
    ");
    if ($row == NULL)
	return (NULL);

    $out = export_activity_description_activity_row($row);
    $out["medals"] = export_activity_description_medals($activity_id);
    $out["skills"] = export_activity_description_skills($activity_id);
    $out["softwares"] = export_activity_description_softwares($activity_id);
    $out["support_references"] = export_activity_description_support_references($activity_id);
    $out["sessions"] = [];

    foreach (db_select_all("
        id FROM session
        WHERE id_activity = $activity_id
        AND deleted IS NULL
        ORDER BY begin_date ASC, id ASC
    ") as $session)
	$out["sessions"][] = export_activity_description_session($session["id"]);

    if ($recursive)
    {
	$out["activities"] = [];
	foreach (db_select_all("
            id FROM activity
            WHERE parent_activity = $activity_id
            AND deleted IS NULL
            ORDER BY codename ASC
        ") as $activity)
	    $out["activities"][] = export_activity_description_activity($activity["id"], false);
    }

    return ($out);
}

function export_activity_description_medals($activity_id)
{
    $activity_id = (int)$activity_id;
    $out = [];
    foreach (db_select_all("
        medal.codename as medal,
        activity_medal.role,
        activity_medal.money,
        activity_medal.local
        FROM activity_medal
        LEFT JOIN medal ON medal.id = activity_medal.id_medal
        WHERE activity_medal.id_activity = $activity_id
        ORDER BY medal.codename ASC
    ") as $row)
    {
	$out[] = [
	    "medal" => export_activity_description_clean_value($row["medal"]),
	    "role" => export_activity_description_clean_value($row["role"]),
	    "money" => export_activity_description_clean_value($row["money"]),
	    "local" => export_activity_description_clean_value($row["local"])
	];
    }
    return ($out);
}

function export_activity_description_skills($activity_id)
{
    $activity_id = (int)$activity_id;
    $out = [];
    foreach (db_select_all("
        skill.codename as skill
        FROM activity_skill
        LEFT JOIN skill ON skill.id = activity_skill.id_skill
        WHERE activity_skill.id_activity = $activity_id
        ORDER BY skill.codename ASC
    ") as $row)
	$out[] = export_activity_description_clean_value($row["skill"]);
    return ($out);
}

function export_activity_description_softwares($activity_id)
{
    $activity_id = (int)$activity_id;
    $out = [];
    foreach (db_select_all("
        software,
        type
        FROM activity_software
        WHERE id_activity = $activity_id
        ORDER BY type ASC, software ASC
    ") as $row)
    {
	$out[] = [
	    "software" => export_activity_description_clean_value($row["software"]),
	    "type" => export_activity_description_clean_value($row["type"])
	];
    }
    return ($out);
}

function export_activity_description_support_references($activity_id)
{
    $activity_id = (int)$activity_id;
    $out = [];
    foreach (db_select_all("
        activity_support.chapter,
        linked.codename as activity,
        linked.parent_activity
        FROM activity_support
        LEFT JOIN activity AS linked ON linked.id = activity_support.id_subactivity
        WHERE activity_support.id_activity = $activity_id
        AND activity_support.id_subactivity IS NOT NULL
        AND activity_support.id_subactivity != ''
        ORDER BY activity_support.chapter ASC, linked.codename ASC
    ") as $row)
    {
	if ($row["activity"] == NULL)
	    continue ;
	$out[] = [
	    "kind" => (($row["parent_activity"] === NULL || (int)$row["parent_activity"] == -1) ? "matter" : "activity"),
	    "activity" => $row["activity"],
	    "chapter" => export_activity_description_clean_value($row["chapter"])
	];
    }
    return ($out);
}

function export_activity_description_session($session_id)
{
    $session_id = (int)$session_id;
    $session = db_select_one("
        session.*,
        activity.codename as activity_codename,
        laboratory.codename as laboratory_codename,
        user.codename as user_codename
        FROM session
        LEFT JOIN activity ON activity.id = session.id_activity
        LEFT JOIN laboratory ON laboratory.id = session.id_laboratory
        LEFT JOIN user ON user.id = session.id_user
        WHERE session.id = $session_id
    ");
    if ($session == NULL)
	return (NULL);

    $out = [];
    foreach ($session as $key => $value)
    {
	if ($key == "id" || substr($key, 0, 3) == "id_")
	    continue ;
	if ($key == "activity_codename")
	    $key = "activity";
	else if ($key == "laboratory_codename")
	    $key = "laboratory";
        else if ($key == "user_codename")
            $key = "user";
	if (export_activity_description_is_date_key($key))
	    export_activity_description_add_date($out, $key, $value);
	else
	    $out[$key] = export_activity_description_clean_value($value);
    }

    $out["rooms"] = [];
    foreach (db_select_all("
        room.codename as room
        FROM session_room
        LEFT JOIN room ON room.id = session_room.id_room
        WHERE session_room.id_session = $session_id
        ORDER BY room.codename ASC
    ") as $room)
    {
	if ($room["room"] !== NULL && $room["room"] !== "")
	    $out["rooms"][] = export_activity_description_clean_value($room["room"]);
    }

    $out["appointment_slots"] = [];
    foreach (db_select_all("
        appointment_slot.begin_date,
        appointment_slot.end_date
        FROM appointment_slot
        WHERE appointment_slot.id_session = $session_id
        ORDER BY appointment_slot.begin_date ASC, appointment_slot.id ASC
    ") as $slot)
    {
	$appointment_slot = [];
	export_activity_description_add_dates($slot, $appointment_slot, [
	    "begin_date",
	    "end_date"
	]);
	$out["appointment_slots"][] = $appointment_slot;
    }

    return ($out);
}

function export_activity_description_last_error($message = NULL)
{
    static $last_error = "";

    if ($message !== NULL)
	$last_error = $message;
    return ($last_error);
}

function export_activity_description_to_dabsic(array $data)
{
    if (function_exists("dabsic_pascalcase_array"))
	$data = dabsic_pascalcase_array($data);
    $json = json_encode(
	$data,
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES |
	JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($json === false)
    {
	export_activity_description_last_error(json_last_error_msg());
	return (false);
    }

    $process = proc_open(
	"mergeconf -if .json -of .dabsic",
	[
	    0 => ["pipe", "r"],
	    1 => ["pipe", "w"],
	    2 => ["pipe", "w"]
	],
	$pipes
    );
    if (!is_resource($process))
    {
	export_activity_description_last_error("Impossible de lancer mergeconf.");
	return (false);
    }

    fwrite($pipes[0], $json);
    fclose($pipes[0]);
    $content = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);

    if ($status != 0 || $content === false || trim($content) == "")
    {
	$details = trim($errors);
	if ($details == "")
	    $details = trim((string)$content);
	if ($details == "")
	    $details = "mergeconf a terminé avec le code ".$status." sans produire de Dabsic.";
	export_activity_description_last_error($details);
	return (false);
    }
    return ($content);
}

function export_activity_description_filename($prefix, $codename)
{
    $codename = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $codename);
    if ($codename == "")
	$codename = "description";
    return ($prefix."_".$codename.".dab");
}

function export_activity_description_response($activity_id, $recursive = true)
{
    if (($data = export_activity_description_activity($activity_id, $recursive)) == NULL)
	return (new ErrorResponse("NotFound"));
    if (($content = export_activity_description_to_dabsic($data)) === false)
	return (new ErrorResponse("CannotExport", export_activity_description_last_error()));
    return (new ValueResponse([
	"filename" => export_activity_description_filename($data["kind"], $data["codename"]),
	"content" => $content
    ]));
}

function export_session_description_response($session_id)
{
    if (($data = export_activity_description_session($session_id)) == NULL)
	return (new ErrorResponse("NotFound"));
    $codename = "session_".(int)$session_id;
    if (isset($data["activity"]) && $data["activity"] != "")
	$codename = $data["activity"]."_session_".(int)$session_id;
    if (($content = export_activity_description_to_dabsic($data)) === false)
	return (new ErrorResponse("CannotExport", export_activity_description_last_error()));
    return (new ValueResponse([
	"filename" => export_activity_description_filename("session", $codename),
	"content" => $content
    ]));
}
