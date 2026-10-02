<?php

require_once (__DIR__."/school_timeline.php");
require_once (__DIR__."/color_from_name.php");
require_once (__DIR__."/cycle_template_export.php");

function cycle_timeline_weekday_index($day)
{
    $days = [
        "Monday" => 0, "Tuesday" => 1, "Wednesday" => 2, "Thursday" => 3,
        "Friday" => 4, "Saturday" => 5, "Sunday" => 6,
    ];
    return ($days[(string)$day] ?? NULL);
}

function cycle_timeline_day_index($date)
{
    if (!is_array($date))
        return (NULL);
    $week = $date["week"] ?? NULL;
    $day = cycle_timeline_weekday_index($date["day"] ?? "");
    if ($day === NULL || (!is_int($week) && !(is_string($week) && preg_match('/^[0-9]+$/', $week))))
        return (NULL);
    return ((int)$week * 7 + $day);
}

function cycle_timeline_clock_seconds($value)
{
    if (!is_string($value) || !preg_match('/^([0-9]{1,2}):([0-5][0-9])(?::([0-5][0-9]))?$/', trim($value), $m))
        return (NULL);
    return ((int)$m[1] * 3600 + (int)$m[2] * 60 + (isset($m[3]) ? (int)$m[3] : 0));
}

function cycle_timeline_duration_seconds($value)
{
    if (!is_string($value) || !preg_match('/^([0-9]+):([0-5][0-9]):([0-5][0-9])$/', trim($value), $m))
        return (NULL);
    return ((int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3]);
}

function cycle_timeline_activity_name(array $activity)
{
    global $Language;

    $lang = isset($Language) && in_array($Language, ["fr", "en"], true) ? $Language : "fr";
    $name = trim((string)($activity[$lang]["name"] ?? ""));
    if ($name == "")
        $name = trim((string)($activity["fr"]["name"] ?? ""));
    if ($name == "")
        $name = trim((string)($activity["en"]["name"] ?? ""));
    if ($name == "")
        $name = trim((string)($activity["codename"] ?? ""));
    return ($name);
}

function cycle_timeline_short_code($codename)
{
    $codename = strtoupper(trim((string)$codename));
    if ($codename == "")
        return ("???");
    $first = explode("-", $codename)[0];
    if ($first == "")
        $first = $codename;
    if (function_exists("mb_substr"))
        return (mb_substr($first, 0, 3, "UTF-8"));
    return (substr($first, 0, 3));
}

function cycle_timeline_matter_catalog(array $data)
{
    $matters = [];
    foreach (($data["matters"] ?? []) as $matter)
    {
        if (!is_array($matter))
            continue ;
        $codename = trim((string)($matter["codename"] ?? ""));
        if ($codename == "")
            continue ;
        $key = trim((string)($matter["_key"] ?? $codename));
        if ($key == "")
            $key = $codename;
        $matters[$key] = [
            "key" => $key,
            "codename" => $codename,
            "name" => cycle_timeline_activity_name($matter),
        ];
    }
    return ($matters);
}

function cycle_timeline_find_matter(array $activity, array $matters)
{
    $matter_key = trim((string)($activity["_matter_key"] ?? ""));
    if ($matter_key != "" && isset($matters[$matter_key]))
        return ($matters[$matter_key]);

    // Compatibility with data produced by the Dabsic exporter and with the
    // pure unit-test fixtures: fall back to the historical codename-prefix
    // inference when no explicit database parent key is available.
    $activity_codename = trim((string)($activity["codename"] ?? ""));
    $ordered = array_values($matters);
    usort($ordered, function($a, $b) {
        return ((strlen($b["codename"]) <=> strlen($a["codename"]))
            ?: strcmp($a["codename"], $b["codename"]));
    });
    foreach ($ordered as $matter)
    {
        $codename = (string)$matter["codename"];
        if (strncmp($activity_codename, $codename."-", strlen($codename) + 1) === 0)
            return ($matter);
    }
    $fallback = cycle_timeline_short_code($activity_codename);
    return (["key" => $fallback, "codename" => $fallback, "name" => ""]);
}

function cycle_timeline_session_end_day(array $session, $start_day)
{
    $begin = cycle_timeline_clock_seconds($session["begin"] ?? "");
    if ($begin === NULL)
        $begin = 0;
    if (isset($session["duration"]))
    {
        $duration = cycle_timeline_duration_seconds((string)$session["duration"]);
        if ($duration !== NULL && $duration > 0)
            return ($start_day + intdiv($begin + $duration - 1, 86400));
    }
    return ($start_day);
}

function cycle_timeline_session_group($type)
{
    $type = trim((string)$type);
    if (in_array($type, ["Challenge", "Exam", "MCQ"], true))
        return ("assessment");
    if ($type === "DemoMeeting")
        return ("defense");
    return ("tp");
}

function cycle_timeline_is_long_activity($type)
{
    return (in_array(trim((string)$type), [
        "Project", "Rush", "MiniProject", "Product", "Exercise", "PickableExercise"
    ], true));
}

function cycle_timeline_compact_session_cell(array $entries)
{
    if (!count($entries))
        return (["text" => "", "comment" => "", "matter" => ""]);
    $codes = [];
    $comments = [];
    $matters = [];
    foreach ($entries as $entry)
    {
        $code = trim((string)($entry["short"] ?? ""));
        if ($code != "" && !in_array($code, $codes, true))
            $codes[] = $code;
        $matter = trim((string)($entry["matter"] ?? ""));
        if ($matter != "" && !in_array($matter, $matters, true))
            $matters[] = $matter;
        $comment = trim((string)($entry["comment"] ?? ""));
        if ($comment != "")
            $comments[] = $comment;
    }
    return ([
        "text" => implode(" / ", $codes),
        "comment" => implode("\n\n", $comments),
        "matter" => count($matters) === 1 ? $matters[0] : "",
    ]);
}

function cycle_timeline_build_model(array $data)
{
    $matters = cycle_timeline_matter_catalog($data);
    $tp_sessions = [];
    $assessment_sessions = [];
    $defense_sessions = [];
    $blocks = [];
    $max_day = 0;

    foreach ($matters as $key => $matter)
        $blocks[$key] = [
            "matter" => $matter,
            "spans" => [],
        ];

    foreach (($data["calendar"] ?? []) as $activity)
    {
        if (!is_array($activity))
            continue ;
        $codename = trim((string)($activity["codename"] ?? ""));
        if ($codename == "")
            continue ;
        $name = cycle_timeline_activity_name($activity);
        $type = trim((string)($activity["type"] ?? ""));
        $matter = cycle_timeline_find_matter($activity, $matters);
        $matter_key = (string)$matter["key"];
        if (!isset($blocks[$matter_key]))
        {
            $matters[$matter_key] = $matter;
            $blocks[$matter_key] = [
                "matter" => $matter,
                "spans" => [],
            ];
        }
        $short = cycle_timeline_short_code($matter["codename"]);

        foreach (($activity["sessions"] ?? []) as $session)
        {
            if (!is_array($session))
                continue ;
            $start_day = cycle_timeline_day_index($session);
            if ($start_day === NULL)
                continue ;
            $end_day = max($start_day, cycle_timeline_session_end_day($session, $start_day));
            $max_day = max($max_day, $end_day);
            $time = trim((string)($session["begin"] ?? ""));
            $end = trim((string)($session["end"] ?? ""));
            $duration = trim((string)($session["duration"] ?? ""));
            $time_label = $time;
            if ($end != "")
                $time_label .= "–".$end;
            else if ($duration != "")
                $time_label .= " (+".$duration.")";
            $comment = $name."\n".$codename;
            if ($time_label != "")
                $comment .= "\n".$time_label;
            if ($type != "")
                $comment .= "\nType : ".$type;
            $group = cycle_timeline_session_group($type);
            for ($day = $start_day; $day <= $end_day; ++$day)
            {
                $entry = [
                    "codename" => $codename,
                    "name" => $name,
                    "short" => $short,
                    "matter" => $matter_key,
                    "type" => $type,
                    "begin" => $time,
                    "comment" => $comment,
                ];
                if ($group === "assessment")
                    $assessment_sessions[$day][] = $entry;
                else if ($group === "defense")
                    $defense_sessions[$day][] = $entry;
                else
                    $tp_sessions[$day][] = $entry;
            }
        }

        $start = cycle_timeline_day_index($activity["subject_appeir_date"] ?? NULL);
        $end = cycle_timeline_day_index($activity["pickup_date"] ?? NULL);
        if (cycle_timeline_is_long_activity($type)
            && $start !== NULL && $end !== NULL && $end >= $start)
        {
            $max_day = max($max_day, $end);
            $duration = $end - $start + 1;
            $blocks[$matter_key]["spans"][] = [
                "codename" => $codename,
                "name" => $name,
                "short" => $short,
                "matter" => $matter_key,
                "type" => $type,
                "start" => $start,
                "end" => $end,
                "duration" => $duration,
                "text" => $duration >= 4 ? $name : $short,
                "comment" => $name."\n".$codename."\nJours ".($start + 1)." à ".($end + 1).($type != "" ? "\nType : ".$type : ""),
            ];
        }
    }

    foreach ([&$tp_sessions, &$assessment_sessions, &$defense_sessions] as &$session_group)
    {
        foreach ($session_group as &$day_sessions)
            usort($day_sessions, function($a, $b) {
                return ((strcmp((string)$a["begin"], (string)$b["begin"])) ?: strcmp($a["codename"], $b["codename"]));
            });
        unset($day_sessions);
    }
    unset($session_group);

    $tp_rows = 4;
    foreach ($tp_sessions as $day_sessions)
        $tp_rows = max($tp_rows, count($day_sessions));

    foreach ($blocks as &$block)
    {
        usort($block["spans"], function($a, $b) {
            return (($a["start"] <=> $b["start"])
                ?: ($a["end"] <=> $b["end"])
                ?: strcmp($a["codename"], $b["codename"]));
        });
        $lane_ends = [];
        foreach ($block["spans"] as &$span)
        {
            $lane = 0;
            while (isset($lane_ends[$lane]) && $span["start"] <= $lane_ends[$lane])
                ++$lane;
            $span["lane"] = $lane;
            $lane_ends[$lane] = $span["end"];
        }
        unset($span);
        $block["timeline_rows"] = count($block["spans"]) ? max(1, count($lane_ends)) : 0;
    }
    unset($block);

    $matter_blocks = array_values(array_filter($blocks, fn($block) => count($block["spans"]) > 0));
    usort($matter_blocks, function($a, $b) {
        return (strcmp((string)$a["matter"]["codename"], (string)$b["matter"]["codename"]));
    });
    $matter_order = array_values($matters);
    usort($matter_order, fn($a, $b) => strcmp((string)$a["codename"], (string)$b["codename"]));
    return ([
        "tp_sessions" => $tp_sessions,
        "assessment_sessions" => $assessment_sessions,
        "defense_sessions" => $defense_sessions,
        "tp_rows" => $tp_rows,
        "blocks" => $matter_blocks,
        "max_day" => $max_day,
        "matters" => $matter_order,
    ]);
}

function cycle_timeline_color($codename)
{
    $color = color_from_name((string)$codename);
    return ("FF".strtoupper(ltrim($color, "#")));
}

function cycle_timeline_light_font($rgb)
{
    $rgb = ltrim((string)$rgb, "#");
    if (strlen($rgb) == 8)
        $rgb = substr($rgb, 2);
    if (strlen($rgb) != 6)
        return (false);
    $r = hexdec(substr($rgb, 0, 2));
    $g = hexdec(substr($rgb, 2, 2));
    $b = hexdec(substr($rgb, 4, 2));
    return ((0.299 * $r + 0.587 * $g + 0.114 * $b) < 145);
}

function cycle_timeline_styles(array $matter_styles)
{
    $fills = [
        '<fill><patternFill patternType="none"/></fill>',
        '<fill><patternFill patternType="gray125"/></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FF3F4854"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FFE7EAEE"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FFD6D9DD"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill>',
    ];
    foreach ($matter_styles as $entry)
        $fills[] = '<fill><patternFill patternType="solid"><fgColor rgb="'.$entry["color"].'"/><bgColor indexed="64"/></patternFill></fill>';

    $xfs = [
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>',
        '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>',
        '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>',
        '<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>',
        '<xf numFmtId="0" fontId="1" fillId="5" borderId="1" xfId="0"><alignment horizontal="left" vertical="center"/></xf>',
        '<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>',
    ];
    $fill_id = 6;
    foreach ($matter_styles as $entry)
    {
        $font_id = $entry["light_font"] ? 2 : 1;
        $xfs[] = '<xf numFmtId="0" fontId="'.$font_id.'" fillId="'.$fill_id.'" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
        ++$fill_id;
    }

    return ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="3">'
        .'<font><sz val="9"/><name val="Calibri"/></font>'
        .'<font><b/><sz val="9"/><name val="Calibri"/></font>'
        .'<font><b/><color rgb="FFFFFFFF"/><sz val="9"/><name val="Calibri"/></font>'
        .'</fonts>'
        .'<fills count="'.count($fills).'">'.implode('', $fills).'</fills>'
        .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFB7BCC2"/></left><right style="thin"><color rgb="FFB7BCC2"/></right><top style="thin"><color rgb="FFB7BCC2"/></top><bottom style="thin"><color rgb="FFB7BCC2"/></bottom><diagonal/></border></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="'.count($xfs).'">'.implode('', $xfs).'</cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'</styleSheet>');
}

function cycle_timeline_cell($column, $row, $value, $style = 1)
{
    $reference = school_timeline_column_name($column).$row;
    return ('<c r="'.$reference.'" s="'.(int)$style.'" t="inlineStr"><is><t>'.school_timeline_xml($value).'</t></is></c>');
}

function cycle_timeline_comments_xml(array $comments)
{
    if (!count($comments))
        return ("");
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<comments xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><authors><author>Infosphere</author></authors><commentList>';
    foreach ($comments as $reference => $comment)
        $xml .= '<comment ref="'.school_timeline_xml($reference).'" authorId="0"><text><r><rPr><sz val="9"/><rFont val="Calibri"/></rPr><t xml:space="preserve">'.school_timeline_xml($comment).'</t></r></text></comment>';
    return ($xml.'</commentList></comments>');
}

function cycle_timeline_vml(array $comments)
{
    $xml = '<xml xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
        .'<o:shapelayout v:ext="edit"><o:idmap v:ext="edit" data="1"/></o:shapelayout>'
        .'<v:shapetype id="_x0000_t202" coordsize="21600,21600" o:spt="202" path="m,l,21600r21600,l21600,xe"><v:stroke joinstyle="miter"/><v:path gradientshapeok="t" o:connecttype="rect"/></v:shapetype>';
    $shape = 1025;
    foreach ($comments as $reference => $unused)
    {
        if (!preg_match('/^([A-Z]+)([0-9]+)$/', $reference, $m))
            continue ;
        $column_name = $m[1];
        $column = 0;
        for ($i = 0; $i < strlen($column_name); ++$i)
            $column = $column * 26 + ord($column_name[$i]) - ord('A') + 1;
        $row = (int)$m[2];
        $c0 = max(0, $column - 1);
        $r0 = max(0, $row - 1);
        $c1 = $c0 + 3;
        $r1 = $r0 + 4;
        $xml .= '<v:shape id="_x0000_s'.$shape.'" type="#_x0000_t202" style="position:absolute;margin-left:59.25pt;margin-top:1.5pt;width:180pt;height:72pt;z-index:1;visibility:hidden" fillcolor="#ffffe1" o:insetmode="auto">'
            .'<v:fill color2="#ffffe1"/><v:shadow color="black" obscured="t"/><v:path o:connecttype="none"/><v:textbox style="mso-direction-alt:auto"><div style="text-align:left"></div></v:textbox>'
            .'<x:ClientData ObjectType="Note"><x:MoveWithCells/><x:SizeWithCells/><x:Anchor>'.$c0.', 15, '.$r0.', 2, '.$c1.', 15, '.$r1.', 4</x:Anchor><x:AutoFill>False</x:AutoFill><x:Row>'.$r0.'</x:Row><x:Column>'.$c0.'</x:Column></x:ClientData></v:shape>';
        ++$shape;
    }
    return ($xml.'</xml>');
}

function cycle_timeline_matter_base_timestamp($base_timestamp, $week_shift, $is_template)
{
    if (!$is_template)
        return ((int)$base_timestamp);
    return ((int)$base_timestamp - (int)$week_shift * 7 * 24 * 60 * 60);
}

function cycle_timeline_activity_data(array $row, $base_timestamp, $matter_key)
{
    $codename = cycle_template_export_activity_identity($row);
    if ($codename == "")
        $codename = trim((string)($row["codename"] ?? ""));
    $out = [
        "codename" => $codename,
        "_matter_key" => (string)$matter_key,
        "type" => (string)($row["type_codename"] ?? ""),
    ];
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
    foreach (["subject_appeir_date", "pickup_date"] as $field)
    {
        $date = cycle_template_export_relative_date($row[$field] ?? NULL, $base_timestamp);
        if ($date !== NULL)
            $out[$field] = $date;
    }
    $sessions = cycle_template_export_sessions((int)$row["id"], $base_timestamp, false);
    if ($sessions)
        $out["sessions"] = $sessions;
    return ($out);
}

function cycle_timeline_matter_rows($cycle_id)
{
    $cycle_id = (int)$cycle_id;
    $rows = [];
    foreach (db_select_all("\n        activity.id, activity_cycle.week_shift\n        FROM activity_cycle\n        LEFT JOIN activity ON activity.id = activity_cycle.id_activity\n        WHERE activity_cycle.id_cycle = $cycle_id\n          AND activity.deleted IS NULL\n          AND activity.disabled IS NULL\n        ORDER BY activity.codename, activity.id\n    ") as $link)
    {
        $matter = cycle_template_export_activity_row((int)$link["id"]);
        if ($matter === NULL || strcasecmp((string)$matter["type_codename"], "Module") != 0)
            continue ;
        $codename = cycle_template_export_activity_identity($matter);
        if ($codename == "")
            $codename = trim((string)$matter["codename"]);
        global $Language;
        $lang = isset($Language) && in_array($Language, ["fr", "en"], true) ? $Language : "fr";
        $name = trim((string)($matter[$lang."_name"] ?? ""));
        if ($name == "")
            $name = trim((string)($matter["fr_name"] ?? $matter["en_name"] ?? ""));
        $rows[] = [
            "id" => (int)$matter["id"],
            "codename" => $codename,
            "name" => $name,
            "week_shift" => (int)($link["week_shift"] ?? 0),
            "row" => $matter,
        ];
    }
    return ($rows);
}

function cycle_timeline_normalize_matter_filter($matter_ids)
{
    if ($matter_ids === NULL)
        return (NULL);
    if (!is_array($matter_ids))
        $matter_ids = [$matter_ids];
    $out = [];
    foreach ($matter_ids as $id)
        if ((is_int($id) || (is_string($id) && preg_match('/^[0-9]+$/', $id))) && (int)$id > 0)
            $out[(int)$id] = true;
    return (array_keys($out));
}

function cycle_timeline_data($cycle_id, $matter_ids = NULL)
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
            throw new RuntimeException("Le cycle n'a pas de date de début permettant de construire sa frise.");
        $base_timestamp = date_to_timestamp(substr((string)$cycle["first_day"], 0, 10)." 00:00:00");
    }

    $matter_filter = cycle_timeline_normalize_matter_filter($matter_ids);
    $matter_filter_set = $matter_filter === NULL ? NULL : array_fill_keys($matter_filter, true);
    $matters = [];
    $calendar = [];
    foreach (cycle_timeline_matter_rows($cycle_id) as $matter_info)
    {
        $matter = $matter_info["row"];
        if ($matter_filter_set !== NULL && !isset($matter_filter_set[(int)$matter["id"]]))
            continue ;
        $matter_key = "activity:".(int)$matter["id"];
        $matter_codename = (string)$matter_info["codename"];
        $matter_base_timestamp = cycle_timeline_matter_base_timestamp(
            $base_timestamp,
            (int)($matter_info["week_shift"] ?? 0),
            !empty($cycle["is_template"])
        );
        $matter_block = [
            "_key" => $matter_key,
            "codename" => $matter_codename,
        ];
        foreach (["fr", "en"] as $lang)
        {
            $name_key = $lang."_name";
            if (array_key_exists($name_key, $matter) && $matter[$name_key] !== NULL)
                $matter_block[$lang] = ["name" => $matter[$name_key]];
        }
        $matters[] = $matter_block;

        foreach (db_select_all("
            id FROM activity
            WHERE parent_activity = ".((int)$matter["id"])."
              AND deleted IS NULL
              AND disabled IS NULL
            ORDER BY codename, id
        ") as $child)
        {
            $activity = cycle_template_export_activity_row((int)$child["id"]);
            if ($activity === NULL)
                continue ;
            $calendar[] = cycle_timeline_activity_data($activity, $matter_base_timestamp, $matter_key);
        }
    }

    return ([
        "cycle" => ["codename" => (string)$cycle["codename"]],
        "calendar" => $calendar,
        "matters" => $matters,
    ]);
}

function cycle_timeline_xlsx(array $data, array $meta = [])
{
    $model = cycle_timeline_build_model($data);
    if (!count($data["calendar"] ?? []))
        return (new ErrorResponse("CycleTimelineNoData"));

    $last_day = max(0, (int)$model["max_day"]);
    $day_count = $last_day + 1;
    $last_column = 1 + $day_count;
    $tp_start = 4;
    $tp_end = $tp_start + (int)$model["tp_rows"] - 1;
    $assessment_row = $tp_end + 1;
    $defense_row = $assessment_row + 1;
    $separator_row = $defense_row + 1;

    $block_layout = [];
    $row_cursor = $separator_row + 1;
    foreach ($model["blocks"] as $block_index => $block)
    {
        $start = $row_cursor;
        $end = $start + (int)$block["timeline_rows"] - 1;
        $block_layout[$block_index] = [
            "start" => $start,
            "end" => $end,
        ];
        $row_cursor = $end + 1;
    }
    $last_row = max($separator_row, $row_cursor - 1);

    $matter_styles = [];
    $style_by_matter = [];
    foreach ($model["matters"] as $matter)
    {
        $color = cycle_timeline_color($matter["codename"]);
        $style_by_matter[$matter["key"]] = 7 + count($matter_styles);
        $matter_styles[] = [
            "codename" => $matter["codename"],
            "color" => $color,
            "light_font" => cycle_timeline_light_font($color),
        ];
    }

    $cells = [];
    $merges = [];
    $comments = [];
    $timeline_rows = [];
    $title = "Frise du cycle ".(string)($data["cycle"]["codename"] ?? "");
    $cells[1][1] = ["", 0];
    $cells[1][2] = [$title, 2];
    if ($last_column > 2)
        $merges[] = "B1:".school_timeline_column_name($last_column)."1";
    $cells[2][1] = ["JOUR", 5];
    $cells[3][1] = ["DATE", 5];

    $first_day = trim((string)($meta["first_day"] ?? ""));
    $base = $first_day != "" ? DateTimeImmutable::createFromFormat("!Y-m-d", substr($first_day, 0, 10)) : false;
    $weekday_short = ["LUN", "MAR", "MER", "JEU", "VEN", "SAM", "DIM"];
    for ($day = 0; $day < $day_count; ++$day)
    {
        $column = 2 + $day;
        $weekend = ($day % 7) >= 5;
        $header_style = $weekend ? 4 : 3;
        $cells[2][$column] = [(string)($day + 1), $header_style];
        if ($base !== false)
        {
            $date = $base->modify("+".$day." days");
            $label = $weekday_short[(int)$date->format("N") - 1]."\n".$date->format("d/m");
        }
        else
            $label = $weekday_short[$day % 7];
        $cells[3][$column] = [$label, $header_style];
    }

    for ($i = 0; $i < (int)$model["tp_rows"]; ++$i)
    {
        $row = $tp_start + $i;
        $cells[$row][1] = ["TP ".($i + 1), 5];
        for ($day = 0; $day < $day_count; ++$day)
            $cells[$row][2 + $day] = ["", (($day % 7) >= 5 ? 6 : 1)];
    }
    foreach ($model["tp_sessions"] as $day => $day_sessions)
    {
        if ($day < 0 || $day >= $day_count)
            continue ;
        foreach ($day_sessions as $index => $entry)
        {
            $row = $tp_start + $index;
            if ($row > $tp_end)
                break ;
            $column = 2 + $day;
            $style = $style_by_matter[$entry["matter"]] ?? 1;
            $cells[$row][$column] = [$entry["short"], $style];
            $comments[school_timeline_column_name($column).$row] = $entry["comment"];
        }
    }

    foreach ([
        [$assessment_row, "COLLES / EXAMENS", $model["assessment_sessions"]],
        [$defense_row, "SOUTENANCES", $model["defense_sessions"]],
    ] as $special)
    {
        [$row, $label, $by_day] = $special;
        $cells[$row][1] = [$label, 5];
        for ($day = 0; $day < $day_count; ++$day)
            $cells[$row][2 + $day] = ["", (($day % 7) >= 5 ? 6 : 1)];
        foreach ($by_day as $day => $entries)
        {
            if ($day < 0 || $day >= $day_count || !count($entries))
                continue ;
            $column = 2 + $day;
            $compact = cycle_timeline_compact_session_cell($entries);
            $style = $style_by_matter[$compact["matter"]] ?? 1;
            $cells[$row][$column] = [$compact["text"], $style];
            if ($compact["comment"] != "")
                $comments[school_timeline_column_name($column).$row] = $compact["comment"];
        }
    }

    $cells[$separator_row][1] = ["FRISE", 5];
    for ($column = 2; $column <= $last_column; ++$column)
        $cells[$separator_row][$column] = ["", 6];

    foreach ($model["blocks"] as $block_index => $block)
    {
        $layout = $block_layout[$block_index];
        $matter = $block["matter"];
        $matter_style = $style_by_matter[$matter["key"]] ?? 5;
        $label = cycle_timeline_short_code($matter["codename"]);
        if (trim((string)$matter["name"]) != "")
            $label .= "\n".$matter["name"];
        else
            $label .= "\n".$matter["codename"];
        $cells[$layout["start"]][1] = [$label, $matter_style];
        if ($layout["end"] > $layout["start"])
            $merges[] = "A".$layout["start"].":A".$layout["end"];

        for ($row = $layout["start"]; $row <= $layout["end"]; ++$row)
        {
            $timeline_rows[$row] = true;
            for ($day = 0; $day < $day_count; ++$day)
                $cells[$row][2 + $day] = ["", (($day % 7) >= 5 ? 6 : 1)];
        }

        foreach ($block["spans"] as $span)
        {
            $row = $layout["start"] + (int)$span["lane"];
            $start_column = 2 + max(0, (int)$span["start"]);
            $end_column = 2 + min($last_day, (int)$span["end"]);
            if ($end_column < $start_column)
                continue ;
            $style = $style_by_matter[$span["matter"]] ?? 1;
            for ($column = $start_column; $column <= $end_column; ++$column)
                $cells[$row][$column] = [$column == $start_column ? $span["text"] : "", $style];
            if ($end_column > $start_column)
                $merges[] = school_timeline_column_name($start_column).$row.":".school_timeline_column_name($end_column).$row;
            $comments[school_timeline_column_name($start_column).$row] = $span["comment"];
        }
    }

    ksort($cells);
    $sheet_data = "";
    foreach ($cells as $row_number => $row_cells)
    {
        ksort($row_cells);
        $height = 18;
        if ($row_number == 1)
            $height = 24;
        else if ($row_number == 3)
            $height = 30;
        else if ($row_number == $separator_row)
            $height = 8;
        else if (isset($timeline_rows[$row_number]))
            $height = 26;
        $sheet_data .= '<row r="'.$row_number.'" ht="'.$height.'" customHeight="1">';
        foreach ($row_cells as $column => $cell)
            $sheet_data .= cycle_timeline_cell($column, $row_number, $cell[0], $cell[1]);
        $sheet_data .= '</row>';
    }

    $merge_xml = "";
    if (count($merges))
    {
        $merge_xml = '<mergeCells count="'.count($merges).'">';
        foreach ($merges as $merge)
            $merge_xml .= '<mergeCell ref="'.school_timeline_xml($merge).'"/>';
        $merge_xml .= '</mergeCells>';
    }

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<dimension ref="A1:'.school_timeline_column_name($last_column).$last_row.'"/>'
        .'<sheetViews><sheetView workbookViewId="0"><pane xSplit="1" ySplit="3" topLeftCell="B4" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>'
        .'<sheetFormatPr defaultRowHeight="18"/>'
        .'<cols><col min="1" max="1" width="26" customWidth="1"/><col min="2" max="'.$last_column.'" width="5.2" customWidth="1"/></cols>'
        .'<sheetData>'.$sheet_data.'</sheetData>'
        .$merge_xml
        .'<pageMargins left="0.2" right="0.2" top="0.3" bottom="0.3" header="0.1" footer="0.1"/>'
        .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
        .(count($comments) ? '<legacyDrawing r:id="rId2"/>' : '')
        .'</worksheet>';

    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        .(count($comments) ? '<Default Extension="vml" ContentType="application/vnd.openxmlformats-officedocument.vmlDrawing"/><Override PartName="/xl/comments1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml"/>' : '')
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';

    $files = [
        '[Content_Types].xml' => $content_types,
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Frise" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => cycle_timeline_styles($matter_styles),
        'xl/worksheets/sheet1.xml' => $sheet,
    ];
    if (count($comments))
    {
        $files['xl/worksheets/_rels/sheet1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="../comments1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/vmlDrawing" Target="../drawings/vmlDrawing1.vml"/></Relationships>';
        $files['xl/comments1.xml'] = cycle_timeline_comments_xml($comments);
        $files['xl/drawings/vmlDrawing1.vml'] = cycle_timeline_vml($comments);
    }

    $content = school_timeline_zip($files);
    if ($content == "")
        return (new ErrorResponse("CycleTimelineGenerationFailed"));
    $codename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', (string)($data["cycle"]["codename"] ?? "cycle"));
    return (new ValueResponse([
        "filename" => "frise_cycle_".trim($codename, "-")."_".date("Y-m-d").".xlsx",
        "content_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "content" => $content,
    ]));
}

function cycle_timeline_response($cycle_id, $matter_ids = NULL, $filter_requested = false)
{
    try
    {
        $cycle_id = (int)$cycle_id;
        $cycle = db_select_one("id, codename, first_day FROM cycle WHERE id = $cycle_id AND deleted IS NULL");
        if ($cycle === NULL)
            return (new ErrorResponse("NotFound"));
        $matter_ids = cycle_timeline_normalize_matter_filter($matter_ids);
        if ($filter_requested && !count($matter_ids ?? []))
            return (new ErrorResponse("CycleTimelineNoMatterSelected"));
        $data = cycle_timeline_data($cycle_id, $matter_ids);
        if ($data === NULL)
            return (new ErrorResponse("NotFound"));
        return (cycle_timeline_xlsx($data, ["first_day" => $cycle["first_day"]]));
    }
    catch (Throwable $e)
    {
        return (new ErrorResponse("CannotExport", $e->getMessage()));
    }
}
