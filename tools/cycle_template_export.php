<?php

require_once (__DIR__."/export_activity_description.php");

function cycle_template_export_last_error($message = NULL)
{
    static $last_error = "";

    if ($message !== NULL)
        $last_error = $message;
    return ($last_error);
}

function cycle_template_export_clock($seconds)
{
    $seconds = ((int)$seconds % 86400 + 86400) % 86400;
    return (sprintf("%02d:%02d", intdiv($seconds, 3600), intdiv($seconds % 3600, 60)));
}

function cycle_template_export_duration($seconds)
{
    $seconds = max(0, (int)$seconds);
    return (sprintf("%02d:%02d:%02d", intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60));
}

function cycle_template_export_relative_parts($value, $base_timestamp)
{
    if ($value === NULL || $value === "")
        return (NULL);
    $timestamp = is_int($value) ? $value : date_to_timestamp($value);
    $offset = (int)$timestamp - (int)$base_timestamp;
    if ($offset < 0)
        throw new RuntimeException("Une date du cycle est antérieure à la base d'export et ne peut pas être représentée par CycleTemplate.");

    $day_number = intdiv($offset, 86400);
    $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
    return ([
        "week" => intdiv($day_number, 7),
        "day" => $days[$day_number % 7],
        "time" => cycle_template_export_clock($offset),
        "offset" => $offset,
    ]);
}

function cycle_template_export_relative_date($value, $base_timestamp)
{
    $parts = cycle_template_export_relative_parts($value, $base_timestamp);
    if ($parts === NULL)
        return (NULL);
    return ([
        "week" => $parts["week"],
        "day" => $parts["day"],
        "time" => $parts["time"],
    ]);
}

function cycle_template_export_set_if_not_null(array &$out, array $row, $field, $target = NULL)
{
    if (!array_key_exists($field, $row) || $row[$field] === NULL)
        return ;
    $out[$target === NULL ? $field : $target] = $row[$field];
}

function cycle_template_export_activity_identity(array $row)
{
    if (isset($row["template_codename"]) && trim((string)$row["template_codename"]) !== "")
        return (trim((string)$row["template_codename"]));
    return (trim((string)$row["codename"]));
}

function cycle_template_export_activity_row($activity_id)
{
    $activity_id = (int)$activity_id;
    $row = db_select_one("\n        activity.*,\n        template.codename AS template_codename\n        FROM activity\n        LEFT JOIN activity AS template ON template.id = activity.id_template\n        WHERE activity.id = $activity_id\n          AND activity.deleted IS NULL\n    ");
    if ($row === NULL)
        return (NULL);

    // Instances inherit every nullable business field from their template,
    // except dates: instance dates are already resolved on the real calendar.
    templated_fill("activity", $row, [
        "emergence_date", "registration_date", "close_date",
        "subject_appeir_date", "subject_disappeir_date", "pickup_date", "done_date"
    ]);
    $type = db_select_one("codename FROM activity_type WHERE id = ".((int)$row["type"]));
    $row["type_codename"] = $type === NULL ? "" : (string)$type["codename"];
    return ($row);
}

function cycle_template_export_relation_source_ids(array $row)
{
    $ids = [];
    if ((int)($row["id_template"] ?? -1) > 0 && !empty($row["template_link"]))
        $ids[] = (int)$row["id_template"];
    $ids[] = (int)$row["id"];
    return (array_values(array_unique(array_filter($ids))));
}

function cycle_template_export_teachers(array $row)
{
    $teachers = [];
    $laboratories = [];
    foreach (cycle_template_export_relation_source_ids($row) as $id_activity)
    {
        foreach (db_select_all("\n            activity_teacher.teacher_pay, activity_teacher.assistant_pay,\n            user.codename AS user_codename,\n            laboratory.codename AS laboratory_codename\n            FROM activity_teacher\n            LEFT JOIN user ON user.id = activity_teacher.id_user\n            LEFT JOIN laboratory ON laboratory.id = activity_teacher.id_laboratory\n            WHERE activity_teacher.id_activity = $id_activity\n            ORDER BY COALESCE(user.codename, laboratory.codename), activity_teacher.id\n        ") as $link)
        {
            $target = $link["user_codename"] !== NULL ? "teachers" : "laboratories";
            $codename = $link["user_codename"] !== NULL ? $link["user_codename"] : $link["laboratory_codename"];
            if ($codename === NULL || $codename === "")
                continue ;
            $entry = ["codename" => $codename];
            if ($link["teacher_pay"] !== NULL)
                $entry["teacher_pay"] = (int)$link["teacher_pay"];
            if ($link["assistant_pay"] !== NULL)
                $entry["assistant_pay"] = (int)$link["assistant_pay"];
            $key = $codename."|".($entry["teacher_pay"] ?? "")."|".($entry["assistant_pay"] ?? "");
            if ($target == "teachers")
                $teachers[$key] = $entry;
            else
                $laboratories[$key] = $entry;
        }
    }
    return ([array_values($teachers), array_values($laboratories)]);
}

function cycle_template_export_skills(array $row)
{
    $out = [];
    foreach (cycle_template_export_relation_source_ids($row) as $id_activity)
        foreach (db_select_all("\n            skill.codename AS codename\n            FROM activity_skill\n            LEFT JOIN skill ON skill.id = activity_skill.id_skill\n            WHERE activity_skill.id_activity = $id_activity\n            ORDER BY skill.codename\n        ") as $link)
            if ($link["codename"] !== NULL && $link["codename"] !== "")
                $out[(string)$link["codename"]] = (string)$link["codename"];
    return (array_values($out));
}

function cycle_template_export_medals(array $row)
{
    $ids = [(int)$row["id"]];
    if ((int)($row["id_template"] ?? -1) > 0 && !empty($row["template_link"]) && !empty($row["medal_template"]))
        array_unshift($ids, (int)$row["id_template"]);
    $out = [];
    foreach (array_values(array_unique(array_filter($ids))) as $id_activity)
        foreach (db_select_all("\n            medal.codename AS codename,\n            activity_medal.role, activity_medal.money, activity_medal.local\n            FROM activity_medal\n            LEFT JOIN medal ON medal.id = activity_medal.id_medal\n            WHERE activity_medal.id_activity = $id_activity\n            ORDER BY medal.codename, activity_medal.id\n        ") as $link)
        {
            if ($link["codename"] === NULL || $link["codename"] === "")
                continue ;
            $entry = [
                "codename" => (string)$link["codename"],
                "role" => (int)$link["role"],
                "money" => (int)$link["money"],
                "local" => (int)$link["local"],
            ];
            $out[json_encode($entry)] = $entry;
        }
    return (array_values($out));
}

function cycle_template_export_supports(array $row, array $codename_by_id)
{
    $ids = [(int)$row["id"]];
    if ((int)($row["id_template"] ?? -1) > 0 && !empty($row["template_link"]) && !empty($row["support_template"]))
        array_unshift($ids, (int)$row["id_template"]);
    $out = [];
    foreach (array_values(array_unique(array_filter($ids))) as $id_activity)
    {
        foreach (db_select_all("\n            activity_support.chapter, activity_support.id_subactivity,\n            support.codename AS support_codename,\n            support_asset.codename AS asset_codename,\n            support_category.codename AS category_codename,\n            subactivity.codename AS activity_codename\n            FROM activity_support\n            LEFT JOIN support ON support.id = activity_support.id_support\n            LEFT JOIN support_asset ON support_asset.id = activity_support.id_support_asset\n            LEFT JOIN support_category ON support_category.id = activity_support.id_support_category\n            LEFT JOIN activity AS subactivity ON subactivity.id = activity_support.id_subactivity\n            WHERE activity_support.id_activity = $id_activity\n            ORDER BY activity_support.chapter, activity_support.id\n        ") as $link)
        {
            $entry = ["chapter" => (int)($link["chapter"] ?? 0)];
            if ($link["support_codename"] !== NULL)
            {
                $entry["kind"] = "Support";
                $entry["codename"] = $link["support_codename"];
            }
            else if ($link["asset_codename"] !== NULL)
            {
                $entry["kind"] = "Asset";
                $entry["codename"] = $link["asset_codename"];
            }
            else if ($link["category_codename"] !== NULL)
            {
                $entry["kind"] = "Category";
                $entry["codename"] = $link["category_codename"];
            }
            else if ($link["activity_codename"] !== NULL)
            {
                $entry["kind"] = "Activity";
                $sid = (int)$link["id_subactivity"];
                $entry["codename"] = $codename_by_id[$sid] ?? $link["activity_codename"];
            }
            else
                continue ;
            $out[json_encode($entry)] = $entry;
        }
    }
    return (array_values($out));
}

function cycle_template_export_scales(array $row)
{
    $groups = [0 => [], 1 => [], 2 => []];
    foreach (cycle_template_export_relation_source_ids($row) as $id_activity)
    {
        foreach (db_select_all("\n            scale.codename AS codename, activity_scale.chapter, activity_scale.type\n            FROM activity_scale\n            LEFT JOIN scale ON scale.id = activity_scale.id_scale\n            WHERE activity_scale.id_activity = $id_activity\n            ORDER BY activity_scale.type, activity_scale.chapter, scale.codename\n        ") as $link)
        {
            $type = (int)$link["type"];
            if (!isset($groups[$type]) || $link["codename"] === NULL || $link["codename"] === "")
                continue ;
            $entry = [
                "codename" => (string)$link["codename"],
                "chapter" => (int)($link["chapter"] ?? 0),
            ];
            $groups[$type][json_encode($entry)] = $entry;
        }
    }
    foreach ($groups as &$group)
        $group = array_values($group);
    unset($group);
    return ($groups);
}

function cycle_template_export_software(array $row)
{
    $out = [];
    foreach (cycle_template_export_relation_source_ids($row) as $id_activity)
        foreach (db_select_all("\n            software, type FROM activity_software\n            WHERE id_activity = $id_activity\n            ORDER BY type, software\n        ") as $link)
        {
            if ($link["software"] === NULL || trim((string)$link["software"]) === "")
                continue ;
            $entry = ["software" => (string)$link["software"], "type" => (int)($link["type"] ?? 0)];
            $out[json_encode($entry)] = $entry;
        }
    return (array_values($out));
}

function cycle_template_export_sessions($activity_id, $base_timestamp, $export_appointment_slots = true)
{
    $activity_id = (int)$activity_id;
    $out = [];
    $index = 0;
    foreach (db_select_all("\n        session.*, laboratory.codename AS laboratory_codename\n        FROM session\n        LEFT JOIN laboratory ON laboratory.id = session.id_laboratory\n        WHERE session.id_activity = $activity_id\n          AND session.deleted IS NULL\n        ORDER BY session.begin_date, session.end_date, session.id\n    ") as $session)
    {
        if ($session["begin_date"] === NULL || $session["end_date"] === NULL)
            continue ;
        $begin = cycle_template_export_relative_parts($session["begin_date"], $base_timestamp);
        $end = cycle_template_export_relative_parts($session["end_date"], $base_timestamp);
        $entry = [
            "week" => $begin["week"],
            "day" => $begin["day"],
            "begin" => $begin["time"],
        ];
        if (intdiv($begin["offset"], 86400) == intdiv($end["offset"], 86400))
            $entry["end"] = $end["time"];
        else
            $entry["duration"] = cycle_template_export_duration($end["offset"] - $begin["offset"]);
        if ($session["maximum_subscription"] !== NULL)
            $entry["maximum_subscription"] = (int)$session["maximum_subscription"];
        if ($session["laboratory_codename"] !== NULL && $session["laboratory_codename"] !== "")
            $entry["laboratory"] = (string)$session["laboratory_codename"];

        $rooms = [];
        foreach (db_select_all("\n            room.codename AS codename\n            FROM session_room\n            LEFT JOIN room ON room.id = session_room.id_room\n            WHERE session_room.id_session = ".((int)$session["id"])."\n            ORDER BY room.codename\n        ") as $room)
            if ($room["codename"] !== NULL && $room["codename"] !== "")
                $rooms[] = (string)$room["codename"];
        if ($rooms)
            $entry["rooms"] = $rooms;

        $slots = [];
        if ($export_appointment_slots)
        {
            $slot_index = 0;
            foreach (db_select_all("\n                begin_date, end_date\n                FROM appointment_slot\n                WHERE id_session = ".((int)$session["id"])."\n                ORDER BY begin_date, end_date, id\n            ") as $slot)
            {
                $sbegin = cycle_template_export_relative_parts($slot["begin_date"], $base_timestamp);
                $send = cycle_template_export_relative_parts($slot["end_date"], $base_timestamp);
                $sentry = [
                    "week" => $sbegin["week"],
                    "day" => $sbegin["day"],
                    "begin" => $sbegin["time"],
                ];
                if (intdiv($sbegin["offset"], 86400) == intdiv($send["offset"], 86400))
                    $sentry["end"] = $send["time"];
                else
                    $sentry["duration"] = cycle_template_export_duration($send["offset"] - $sbegin["offset"]);
                $slots[sprintf("Slot%03d", $slot_index++)] = $sentry;
            }
        }
        if ($slots)
            $entry["appointment_slots"] = $slots;
        $out[sprintf("Session%03d", $index++)] = $entry;
    }
    return ($out);
}

function cycle_template_export_activity_block(array $row, $base_timestamp, array $codename_by_id, $with_sessions)
{
    $out = [
        "codename" => $codename_by_id[(int)$row["id"]] ?? cycle_template_export_activity_identity($row),
        "type" => (string)$row["type_codename"],
    ];
    foreach ([
        "min_team_size", "max_team_size", "hidden", "mandatory", "maximum_subscription",
        "money", "subscription", "repository_name", "estimated_work_duration",
        "automatic_correction_frequency", "slot_duration", "validation",
        "credit_a", "credit_b", "credit_c", "credit_d", "allow_unregistration",
        "validated", "template_link", "medal_template", "support_template",
        "grade_a", "grade_b", "grade_c", "grade_d", "grade_bonus",
        "declaration_type", "progressive_slot_opening", "team_based_slot_opening", "todolist"
    ] as $field)
        cycle_template_export_set_if_not_null($out, $row, $field);

    if ((int)($row["reference_activity"] ?? -1) > 0)
    {
        $ref_id = (int)$row["reference_activity"];
        $ref_codename = $codename_by_id[$ref_id] ?? NULL;
        if ($ref_codename === NULL)
        {
            $ref = db_select_one("activity.codename, template.codename AS template_codename FROM activity LEFT JOIN activity AS template ON template.id = activity.id_template WHERE activity.id = $ref_id");
            if ($ref !== NULL)
                $ref_codename = cycle_template_export_activity_identity($ref);
        }
        if ($ref_codename !== NULL && $ref_codename !== "")
            $out["reference_activity"] = $ref_codename;
    }

    foreach ([
        "emergence_date", "registration_date", "close_date", "subject_appeir_date",
        "subject_disappeir_date", "pickup_date", "done_date"
    ] as $field)
    {
        $date = cycle_template_export_relative_date($row[$field] ?? NULL, $base_timestamp);
        if ($date !== NULL)
            $out[$field] = $date;
    }

    foreach (["fr", "en"] as $lang)
    {
        $localized = [];
        foreach (["name", "description", "objective", "method", "reference"] as $field)
        {
            $key = $lang."_".$field;
            if (array_key_exists($key, $row) && $row[$key] !== NULL)
                $localized[$field] = $row[$key];
        }
        if ($localized)
            $out[$lang] = $localized;
    }

    [$teachers, $laboratories] = cycle_template_export_teachers($row);
    if ($teachers)
        $out["teachers"] = $teachers;
    if ($laboratories)
        $out["laboratories"] = $laboratories;
    if (($skills = cycle_template_export_skills($row)))
        $out["skills"] = $skills;
    if (($medals = cycle_template_export_medals($row)))
        $out["medals"] = $medals;
    if (($supports = cycle_template_export_supports($row, $codename_by_id)))
        $out["supports"] = $supports;
    [$scales, $mcqs, $satisfaction] = cycle_template_export_scales($row);
    if ($scales)
        $out["scales"] = $scales;
    if ($mcqs)
        $out["mcqs"] = $mcqs;
    if ($satisfaction)
        $out["satisfaction"] = $satisfaction;
    if (($software = cycle_template_export_software($row)))
        $out["software"] = $software;

    $export_slots = !empty($row["is_template"])
        || (empty($row["progressive_slot_opening"]) && empty($row["team_based_slot_opening"]));
    if ($with_sessions && ($sessions = cycle_template_export_sessions($row["id"], $base_timestamp, $export_slots)))
        $out["sessions"] = $sessions;
    return ($out);
}

function cycle_template_export_cycle_relations($cycle_id)
{
    $teachers = [];
    $laboratories = [];
    foreach (db_select_all("\n        user.codename AS user_codename, laboratory.codename AS laboratory_codename\n        FROM cycle_teacher\n        LEFT JOIN user ON user.id = cycle_teacher.id_user\n        LEFT JOIN laboratory ON laboratory.id = cycle_teacher.id_laboratory\n        WHERE cycle_teacher.id_cycle = ".((int)$cycle_id)."\n        ORDER BY COALESCE(user.codename, laboratory.codename), cycle_teacher.id\n    ") as $row)
    {
        if ($row["user_codename"] !== NULL && $row["user_codename"] !== "")
            $teachers[(string)$row["user_codename"]] = (string)$row["user_codename"];
        if ($row["laboratory_codename"] !== NULL && $row["laboratory_codename"] !== "")
            $laboratories[(string)$row["laboratory_codename"]] = (string)$row["laboratory_codename"];
    }
    return ([array_values($teachers), array_values($laboratories)]);
}

function cycle_template_export_data($cycle_id)
{
    global $date0;

    $cycle_id = (int)$cycle_id;
    $cycle = db_select_one("* FROM cycle WHERE id = $cycle_id AND deleted IS NULL");
    if ($cycle === NULL)
        return (NULL);
    if (!empty($cycle["is_template"]))
        $base_timestamp = date_to_timestamp($date0);
    else
    {
        if ($cycle["first_day"] === NULL || trim((string)$cycle["first_day"]) === "")
            throw new RuntimeException("Le cycle n'a pas de date de début permettant de relativiser son calendrier.");
        $base_timestamp = date_to_timestamp(substr((string)$cycle["first_day"], 0, 10)." 00:00:00");
    }

    $cycle_block = [
        "codename" => (string)$cycle["codename"],
        "cycle" => (int)$cycle["cycle"],
        "objective" => (int)$cycle["objective"],
    ];
    foreach (["fr", "en"] as $lang)
        if (array_key_exists($lang."_name", $cycle) && $cycle[$lang."_name"] !== NULL)
            $cycle_block[$lang] = ["name" => $cycle[$lang."_name"]];
    [$cycle_teachers, $cycle_laboratories] = cycle_template_export_cycle_relations($cycle_id);
    if ($cycle_teachers)
        $cycle_block["teachers"] = $cycle_teachers;
    if ($cycle_laboratories)
        $cycle_block["laboratories"] = $cycle_laboratories;

    $matter_links = db_select_all("\n        activity_cycle.week_shift, activity_cycle.cursus, activity_cycle.replacement_subscription,\n        activity.id\n        FROM activity_cycle\n        LEFT JOIN activity ON activity.id = activity_cycle.id_activity\n        WHERE activity_cycle.id_cycle = $cycle_id\n          AND activity.deleted IS NULL\n          AND activity.disabled IS NULL\n        ORDER BY activity.codename, activity.id\n    ");

    $matters = [];
    $activities = [];
    $codename_by_id = [];
    $matter_rows = [];
    $activity_rows = [];
    foreach ($matter_links as $link)
    {
        $matter = cycle_template_export_activity_row($link["id"]);
        if ($matter === NULL)
            continue ;
        if (strcasecmp((string)$matter["type_codename"], "Module") != 0)
            throw new RuntimeException("L'élément ".$matter["codename"]." lié au cycle n'est pas une matière (Module).");
        $matter["_cycle_link"] = $link;
        $matter_rows[] = $matter;
        $codename_by_id[(int)$matter["id"]] = cycle_template_export_activity_identity($matter);
        foreach (db_select_all("\n            id FROM activity\n            WHERE parent_activity = ".((int)$matter["id"])."\n              AND deleted IS NULL\n              AND disabled IS NULL\n            ORDER BY codename, id\n        ") as $child)
        {
            $activity = cycle_template_export_activity_row($child["id"]);
            if ($activity === NULL)
                continue ;
            $activity_rows[] = $activity;
            $codename_by_id[(int)$activity["id"]] = cycle_template_export_activity_identity($activity);
        }
    }

    if (!$matter_rows)
        throw new RuntimeException("Le cycle ne contient aucune matière exportable.");

    $seen = [];
    foreach ($codename_by_id as $id_activity => $codename)
    {
        if ($codename === "" || isset($seen[$codename]))
            throw new RuntimeException("Collision de codename pendant l'export : ".$codename);
        $seen[$codename] = $id_activity;
    }

    $matter_index = 0;
    foreach ($matter_rows as $matter)
    {
        $block = cycle_template_export_activity_block($matter, $base_timestamp, $codename_by_id, false);
        unset($block["type"]); // Module est implicite dans Matters.
        $link = $matter["_cycle_link"];
        if (!empty($cycle["is_template"]) && (int)$link["week_shift"] != 0)
            $block["week_shift"] = (int)$link["week_shift"];
        if (trim((string)$link["cursus"]) !== "")
            $block["cursus"] = (string)$link["cursus"];
        if ($link["replacement_subscription"] !== NULL)
            $block["replacement_subscription"] = (int)$link["replacement_subscription"];
        $matters[sprintf("Matter%03d", $matter_index++)] = $block;
    }

    $activity_index = 0;
    foreach ($activity_rows as $activity)
    {
        $parent_id = (int)$activity["parent_activity"];
        $parent_codename = $codename_by_id[$parent_id] ?? "";
        $codename = $codename_by_id[(int)$activity["id"]] ?? "";
        if ($parent_codename === "" || strncmp($codename, $parent_codename."-", strlen($parent_codename) + 1) !== 0)
            throw new RuntimeException("Le codename ".$codename." ne commence pas par celui de sa matière ".$parent_codename.".");
        $activities[sprintf("Activity%03d", $activity_index++)] =
            cycle_template_export_activity_block($activity, $base_timestamp, $codename_by_id, true);
    }

    return ([
        "format" => "CycleTemplate",
        "version" => 1,
        "cycle" => $cycle_block,
        "calendar" => $activities,
        "matters" => $matters,
    ]);
}

function cycle_template_export_filename($codename)
{
    $codename = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', (string)$codename);
    if ($codename === "")
        $codename = "cycle";
    return ("cycle_".$codename.".dab");
}

function cycle_template_export_response($cycle_id)
{
    try
    {
        $data = cycle_template_export_data($cycle_id);
        if ($data === NULL)
            return (new ErrorResponse("NotFound"));
        if (($content = export_activity_description_to_dabsic($data)) === false)
            return (new ErrorResponse("CannotExport", export_activity_description_last_error()));
        return (new ValueResponse([
            "filename" => cycle_template_export_filename($data["cycle"]["codename"]),
            "content_type" => "application/octet-stream",
            "content" => $content,
        ]));
    }
    catch (Throwable $e)
    {
        cycle_template_export_last_error($e->getMessage());
        return (new ErrorResponse("CannotExport", $e->getMessage()));
    }
}
