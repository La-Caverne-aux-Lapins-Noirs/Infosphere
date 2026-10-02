<?php

function school_timeline_xml($value)
{
    return (htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, "UTF-8"));
}

function school_timeline_column_name($index)
{
    $index = (int)$index;
    $name = "";
    while ($index > 0)
    {
        $index--;
        $name = chr(ord('A') + ($index % 26)).$name;
        $index = intdiv($index, 26);
    }
    return ($name);
}

function school_timeline_upper($value)
{
    $value = trim((string)$value);
    return (function_exists("mb_strtoupper") ? mb_strtoupper($value, "UTF-8") : strtoupper($value));
}

function school_timeline_month_label($month)
{
    $labels = [
        1 => "JAN", 2 => "FÉV", 3 => "MAR", 4 => "AVR",
        5 => "MAI", 6 => "JUN", 7 => "JUI", 8 => "AOÛ",
        9 => "SEP", 10 => "OCT", 11 => "NOV", 12 => "DÉC",
    ];
    return ($labels[(int)$month] ?? (string)$month);
}

function school_timeline_cycle_base_label(array $row)
{
    $year = intdiv(max(0, (int)($row["cycle_number"] ?? 0)), 4) + 1;
    $name = trim((string)($row["template_name"] ?? ""));
    $codename = trim((string)($row["template_codename"] ?? ""));

    // Prefer the compact template codename when the translated name is long.
    $label = $name;
    if ($label == "" || (function_exists("mb_strlen") ? mb_strlen($label, "UTF-8") : strlen($label)) > 24)
        $label = $codename;
    if ($label == "")
        return ("Année ".$year);

    // Templates are sometimes named EF1_A / EF1-T1 / "EF1 trimestre 1".
    // Strip only an obvious quarter suffix; never guess inside the name.
    $label = preg_replace('/(?:[ _.-]+(?:trimestre|trim|t)[ _.-]*[1-4]|[ _.-]+[ABCD])$/iu', '', $label);
    $label = trim((string)$label, " _.-\t\n\r\0\x0B");
    return ($label == "" ? "Année ".$year : $label);
}

function school_timeline_student_name(array $row)
{
    $first = trim((string)($row["first_name"] ?? ""));
    $family = trim((string)($row["family_name"] ?? ""));
    if ($family == "")
        $family = trim((string)($row["use_name"] ?? ""));
    $name = trim($first." ".$family);
    if ($name == "")
        $name = trim((string)($row["user_codename"] ?? ""));
    return (school_timeline_upper($name));
}

function school_timeline_mode(array $modes)
{
    $modes = array_values(array_unique(array_filter(array_map(function($mode) {
        $mode = trim((string)$mode);
        return (in_array($mode, ["school", "of", "ofa", "cfa"], true) ? $mode : "unknown");
    }, $modes))));
    if (!count($modes))
        return ("unknown");
    return (count($modes) == 1 ? $modes[0] : "mixed");
}

function school_timeline_collect($id_school)
{
    global $Language;

    $id_school = (int)$id_school;
    $language = in_array($Language, ["fr", "en"], true) ? $Language : "fr";
    return (db_select_all("
        user.id as id_user,
        user.codename as user_codename,
        user.first_name,
        user.family_name,
        user.use_name,
        user_cycle.enrollment_mode,
        user_cycle.hidden,
        cycle.id as id_cycle,
        cycle.codename as cycle_codename,
        cycle.first_day,
        cycle.cycle as cycle_number,
        cycle.id_template,
        template.codename as template_codename,
        template.{$language}_name as template_name
        FROM user_cycle
        INNER JOIN user ON user.id = user_cycle.id_user
        INNER JOIN cycle ON cycle.id = user_cycle.id_cycle
        INNER JOIN school_cycle
          ON school_cycle.id_cycle = cycle.id
         AND school_cycle.id_school = $id_school
        LEFT JOIN cycle as template ON template.id = cycle.id_template
        WHERE cycle.deleted IS NULL
          AND cycle.first_day IS NOT NULL
        ORDER BY cycle.first_day ASC, cycle.cycle ASC, user.id ASC
    "));
}

function school_timeline_quarter_token($cycle_number)
{
    $cycle_number = max(0, (int)$cycle_number);
    $year = intdiv($cycle_number, 4) + 1;
    $letter = ["A", "B", "C", "D"][$cycle_number % 4];
    return ($year.$letter);
}

function school_timeline_compress_quarters(array $tokens)
{
    $tokens = array_values(array_unique($tokens));
    if (!count($tokens))
        return ("");
    $parsed = [];
    foreach ($tokens as $token)
        if (preg_match('/^(\d+)([ABCD])$/', $token, $match))
            $parsed[] = ["year" => (int)$match[1], "letter" => $match[2], "rank" => strpos("ABCD", $match[2])];
    if (count($parsed) != count($tokens))
        return (implode("/", $tokens));
    usort($parsed, function($a, $b) {
        return (($a["year"] <=> $b["year"]) ?: ($a["rank"] <=> $b["rank"]));
    });
    $year = $parsed[0]["year"];
    foreach ($parsed as $item)
        if ($item["year"] != $year)
            return (implode("/", array_map(fn($item) => $item["year"].$item["letter"], $parsed)));
    $ranks = array_column($parsed, "rank");
    $consecutive = count($ranks) == (max($ranks) - min($ranks) + 1);
    if ($consecutive && count($parsed) > 1)
        return ($year.$parsed[0]["letter"]."–".$year.$parsed[count($parsed) - 1]["letter"]);
    return (implode("/", array_map(fn($item) => $item["year"].$item["letter"], $parsed)));
}

function school_timeline_nearest_absolute_slot(DateTimeImmutable $date)
{
    $year = (int)$date->format("Y");
    $best = NULL;
    foreach ([$year - 1, $year, $year + 1] as $candidate_year)
        foreach ([1, 4, 7, 9] as $month)
        {
            $candidate = DateTimeImmutable::createFromFormat(
                "!Y-m-d",
                sprintf("%04d-%02d-01", $candidate_year, $month)
            );
            if ($candidate === false)
                continue ;
            $distance = abs($candidate->getTimestamp() - $date->getTimestamp());
            if ($best === NULL
                || $distance < $best["distance"]
                || ($distance == $best["distance"]
                    && $candidate->getTimestamp() < $best["timestamp"]))
                $best = [
                    "key" => $candidate->format("Y-m"),
                    "date" => $candidate->format("Y-m-d"),
                    "year" => (int)$candidate->format("Y"),
                    "month" => (int)$candidate->format("n"),
                    "distance" => $distance,
                    "timestamp" => $candidate->getTimestamp(),
                ];
        }
    if ($best === NULL)
        return (NULL);
    unset($best["distance"], $best["timestamp"]);
    return ($best);
}

function school_timeline_build_model(array $rows, $requested_start_year = NULL, $requested_end_year = NULL)
{
    if (!count($rows))
        return (["slots" => [], "students" => [], "rows" => [], "blocks" => [], "undated" => 0]);

    // The timeline itself is absolute: every displayed calendar year has
    // exactly four reference columns, JAN / AVR / JUI / SEP. A cycle whose
    // first_day is not aligned on one of those dates is rounded to the nearest
    // reference point (including JAN of the following year when appropriate).
    $min_year = NULL;
    $max_year = NULL;
    foreach ($rows as $row)
    {
        $raw = substr((string)($row["first_day"] ?? ""), 0, 10);
        $date = DateTimeImmutable::createFromFormat("!Y-m-d", $raw);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors["warning_count"] || $errors["error_count"])))
            continue ;
        $slot = school_timeline_nearest_absolute_slot($date);
        if ($slot === NULL)
            continue ;
        $min_year = $min_year === NULL ? $slot["year"] : min($min_year, $slot["year"]);
        $max_year = $max_year === NULL ? $slot["year"] : max($max_year, $slot["year"]);
    }
    if ($min_year === NULL || $max_year === NULL)
        return (["slots" => [], "students" => [], "rows" => [], "blocks" => [], "undated" => count($rows)]);

    // An explicitly requested export period is a calendar-year window. Missing
    // bounds remain automatic, so the default button still exports all history.
    if ($requested_start_year !== NULL)
        $min_year = (int)$requested_start_year;
    if ($requested_end_year !== NULL)
        $max_year = (int)$requested_end_year;
    if ($min_year > $max_year)
        return (["slots" => [], "students" => [], "rows" => [], "blocks" => [], "invalid_range" => true]);

    $slots = [];
    $slot_index = [];
    foreach (range($min_year, $max_year) as $year)
        foreach ([1, 4, 7, 9] as $month)
        {
            $key = sprintf("%04d-%02d", $year, $month);
            $slot_index[$key] = count($slots);
            $slots[] = [
                "key" => $key,
                "date" => $key."-01",
                "year" => $year,
                "month" => $month,
            ];
        }

    $students = [];
    foreach ($rows as $row)
    {
        $raw = substr((string)($row["first_day"] ?? ""), 0, 10);
        $date = DateTimeImmutable::createFromFormat("!Y-m-d", $raw);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors["warning_count"] || $errors["error_count"])))
            continue ;
        $rounded_slot = school_timeline_nearest_absolute_slot($date);
        if ($rounded_slot === NULL)
            continue ;
        $key = $rounded_slot["key"];
        if (!isset($slot_index[$key]))
            continue ;
        $id_user = (int)$row["id_user"];
        if (!isset($students[$id_user]))
            $students[$id_user] = [
                "id" => $id_user,
                "name" => school_timeline_student_name($row),
                "codename" => (string)$row["user_codename"],
                "slots" => [],
            ];
        $index = $slot_index[$key];
        if (!isset($students[$id_user]["slots"][$index]))
            $students[$id_user]["slots"][$index] = [];
        $row["base_label"] = school_timeline_cycle_base_label($row);
        $row["quarter_token"] = school_timeline_quarter_token($row["cycle_number"]);
        $students[$id_user]["slots"][$index][] = $row;
    }

    // Compact several transparent cycles belonging to the same temporal slot.
    foreach ($students as &$student)
    {
        ksort($student["slots"]);
        foreach ($student["slots"] as $index => $entries)
        {
            $labels = [];
            $tokens = [];
            $years = [];
            $modes = [];
            foreach ($entries as $entry)
            {
                $labels[] = $entry["base_label"];
                $tokens[] = $entry["quarter_token"];
                $years[] = intdiv(max(0, (int)$entry["cycle_number"]), 4) + 1;
                $modes[] = $entry["enrollment_mode"] ?? "";
            }
            $student["slots"][$index] = [
                "labels" => array_values(array_unique($labels)),
                "tokens" => array_values(array_unique($tokens)),
                "years" => array_values(array_unique($years)),
                "mode" => school_timeline_mode($modes),
            ];
        }
    }
    unset($student);

    // Split each student's presence into contiguous time intervals. Rows may be
    // reused by another student when intervals do not overlap.
    $intervals = [];
    foreach ($students as $id_user => $student)
    {
        $indexes = array_keys($student["slots"]);
        if (!count($indexes))
            continue ;
        $start = $previous = $indexes[0];
        foreach (array_slice($indexes, 1) as $index)
        {
            if ($index != $previous + 1)
            {
                $intervals[] = ["id_user" => $id_user, "start" => $start, "end" => $previous, "name" => $student["name"]];
                $start = $index;
            }
            $previous = $index;
        }
        $intervals[] = ["id_user" => $id_user, "start" => $start, "end" => $previous, "name" => $student["name"]];
    }
    usort($intervals, function($a, $b) {
        return (($a["start"] <=> $b["start"])
            ?: (($b["end"] - $b["start"]) <=> ($a["end"] - $a["start"]))
            ?: strcmp($a["name"], $b["name"]));
    });

    $row_occupancy = [];
    $preferred_row = [];
    foreach ($intervals as &$interval)
    {
        $candidate_rows = [];
        if (isset($preferred_row[$interval["id_user"]]))
            $candidate_rows[] = $preferred_row[$interval["id_user"]];
        foreach (array_keys($row_occupancy) as $row_index)
            if (!in_array($row_index, $candidate_rows, true))
                $candidate_rows[] = $row_index;
        $candidate_rows[] = count($row_occupancy);

        $selected = NULL;
        foreach ($candidate_rows as $row_index)
        {
            $free = true;
            if (isset($row_occupancy[$row_index]))
                for ($slot = $interval["start"]; $slot <= $interval["end"]; ++$slot)
                    if (!empty($row_occupancy[$row_index][$slot]))
                    {
                        $free = false;
                        break ;
                    }
            if ($free)
            {
                $selected = $row_index;
                break ;
            }
        }
        if (!isset($row_occupancy[$selected]))
            $row_occupancy[$selected] = [];
        for ($slot = $interval["start"]; $slot <= $interval["end"]; ++$slot)
            $row_occupancy[$selected][$slot] = true;
        $interval["row"] = $selected;
        if (!isset($preferred_row[$interval["id_user"]]))
            $preferred_row[$interval["id_user"]] = $selected;
    }
    unset($interval);

    $blocks = [];
    foreach ($intervals as $interval)
    {
        $student = $students[$interval["id_user"]];
        $current = NULL;
        for ($slot = $interval["start"]; $slot <= $interval["end"]; ++$slot)
        {
            if (!isset($student["slots"][$slot]))
                continue ;
            $cell = $student["slots"][$slot];
            $key = implode("|", [
                $cell["mode"],
                implode("/", $cell["labels"]),
                implode("/", $cell["years"]),
            ]);
            if ($current === NULL || $current["key"] !== $key || $slot !== $current["end"] + 1)
            {
                if ($current !== NULL)
                    $blocks[] = $current;
                $current = [
                    "key" => $key,
                    "id_user" => $interval["id_user"],
                    "row" => $interval["row"],
                    "start" => $slot,
                    "end" => $slot,
                    "mode" => $cell["mode"],
                    "labels" => $cell["labels"],
                    "years" => $cell["years"],
                    "tokens" => $cell["tokens"],
                    "name" => $student["name"],
                ];
            }
            else
            {
                $current["end"] = $slot;
                $current["tokens"] = array_values(array_unique(array_merge($current["tokens"], $cell["tokens"])));
            }
        }
        if ($current !== NULL)
            $blocks[] = $current;
    }

    foreach ($blocks as &$block)
    {
        $labels = implode(" / ", $block["labels"]);
        if ($labels == "")
            $labels = count($block["years"]) == 1 ? "Année ".$block["years"][0] : "Parcours";
        $full_year = false;
        if (count($block["years"]) == 1)
        {
            $year = (int)$block["years"][0];
            $expected = [$year."A", $year."B", $year."C", $year."D"];
            $tokens = $block["tokens"];
            sort($tokens);
            $check = $expected;
            sort($check);
            $full_year = ($tokens === $check);
        }
        $suffix = $full_year ? "" : school_timeline_compress_quarters($block["tokens"]);
        $block["text"] = trim($block["name"]." ".$labels.($suffix == "" ? "" : " · ".$suffix));
    }
    unset($block);

    return ([
        "slots" => $slots,
        "students" => $students,
        "rows" => $row_occupancy,
        "blocks" => $blocks,
    ]);
}


function school_timeline_zip(array $files)
{
    $body = "";
    $central = "";
    $offset = 0;
    $count = 0;
    $now = getdate();
    $year = max(1980, min(2107, (int)$now["year"]));
    $dos_time = (((int)$now["hours"] & 31) << 11) | (((int)$now["minutes"] & 63) << 5) | (((int)$now["seconds"] >> 1) & 31);
    $dos_date = (($year - 1980) << 9) | (((int)$now["mon"] & 15) << 5) | ((int)$now["mday"] & 31);

    foreach ($files as $name => $content)
    {
        $name = str_replace("\\", "/", (string)$name);
        $content = (string)$content;
        $crc = crc32($content);
        if ($crc < 0)
            $crc += 4294967296;
        $compressed = function_exists("gzdeflate") ? @gzdeflate($content, 6) : false;
        if ($compressed === false)
        {
            $compressed = $content;
            $method = 0;
        }
        else
            $method = 8;
        $name_length = strlen($name);
        $compressed_length = strlen($compressed);
        $content_length = strlen($content);

        $local = pack(
            "VvvvvvVVVvv",
            0x04034b50, 20, 0, $method, $dos_time, $dos_date, $crc,
            $compressed_length, $content_length, $name_length, 0
        ).$name.$compressed;
        $body .= $local;

        $central .= pack(
            "VvvvvvvVVVvvvvvVV",
            0x02014b50, 20, 20, 0, $method, $dos_time, $dos_date, $crc,
            $compressed_length, $content_length, $name_length,
            0, 0, 0, 0, 0, $offset
        ).$name;
        $offset += strlen($local);
        ++$count;
    }
    $central_offset = strlen($body);
    $central_length = strlen($central);
    return ($body.$central.pack(
        "VvvvvVVv",
        0x06054b50, 0, 0, $count, $count, $central_length, $central_offset, 0
    ));
}

function school_timeline_xlsx_styles()
{
    return ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="3">'
        .'<font><sz val="10"/><name val="Calibri"/></font>'
        .'<font><b/><sz val="10"/><name val="Calibri"/></font>'
        .'<font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Calibri"/></font>'
        .'</fonts>'
        .'<fills count="9">'
        .'<fill><patternFill patternType="none"/></fill>'
        .'<fill><patternFill patternType="gray125"/></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFFF00"/><bgColor indexed="64"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FF2FD4E8"/><bgColor indexed="64"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FF43E35A"/><bgColor indexed="64"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/><bgColor indexed="64"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FF666666"/><bgColor indexed="64"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFB74D"/><bgColor indexed="64"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FFFF3B30"/><bgColor indexed="64"/></patternFill></fill>'
        .'</fills>'
        .'<borders count="2">'
        .'<border><left/><right/><top/><bottom/><diagonal/></border>'
        .'<border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom><diagonal/></border>'
        .'</borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="10">'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="2" fillId="6" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>'
        .'<xf numFmtId="0" fontId="1" fillId="5" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>'
        .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="1" fillId="5" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="1" fillId="7" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="1" fillId="8" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .'</cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'</styleSheet>');
}

function school_timeline_xlsx_cell($column, $row, $value, $style = 1)
{
    $reference = school_timeline_column_name($column).$row;
    $value = school_timeline_xml($value);
    return ('<c r="'.$reference.'" s="'.(int)$style.'" t="inlineStr"><is><t>'.$value.'</t></is></c>');
}

function school_timeline_xlsx_style_for_mode($mode)
{
    return (["school" => 4, "of" => 5, "cfa" => 6, "unknown" => 7, "mixed" => 8, "ofa" => 9][$mode] ?? 7);
}

function school_timeline_xlsx(array $school, array $model)
{
    $slots = $model["slots"] ?? [];
    if (!count($slots) || !count($model["blocks"] ?? []))
        return (new ErrorResponse("SchoolTimelineNoData"));

    $data_start_row = 9;
    $row_count = count($model["rows"] ?? []);
    $last_data_row = max($data_start_row, $data_start_row + $row_count - 1);
    $last_column = 1 + count($slots);
    $note_row = $last_data_row + 2;
    $cells = [];
    $merges = [];

    // Legend and timeline headers, following the compact layout used in the
    // historical planning sheet.
    $cells[1][1] = ["LÉGENDE", 2];
    $cells[2][1] = ["SCOLA", 4];
    $cells[3][1] = ["OF", 5];
    $cells[4][1] = ["OF ALT", 9];
    $cells[5][1] = ["CFA", 6];
    $cells[6][1] = ["NON RENSEIGNÉ", 7];
    $cells[7][1] = ["MIXTE", 8];

    $year_start = 0;
    while ($year_start < count($slots))
    {
        $year = $slots[$year_start]["year"];
        $year_end = $year_start;
        while ($year_end + 1 < count($slots) && $slots[$year_end + 1]["year"] == $year)
            ++$year_end;
        $start_column = 2 + $year_start;
        $end_column = 2 + $year_end;
        $cells[1][$start_column] = [(string)$year, 2];
        for ($column = $start_column + 1; $column <= $end_column; ++$column)
            $cells[1][$column] = ["", 2];
        if ($end_column > $start_column)
            $merges[] = school_timeline_column_name($start_column)."1:".school_timeline_column_name($end_column)."1";
        $year_start = $year_end + 1;
    }
    foreach ($slots as $index => $slot)
        $cells[2][2 + $index] = [school_timeline_month_label($slot["month"]), 3];
    for ($column = 2; $column <= $last_column; ++$column)
        for ($header_row = 3; $header_row <= 7; ++$header_row)
            if (!isset($cells[$header_row][$column]))
                $cells[$header_row][$column] = ["", 1];

    // Draw the empty allocation grid first.
    for ($row = $data_start_row; $row <= $last_data_row; ++$row)
    {
        $cells[$row][1] = ["", 1];
        for ($column = 2; $column <= $last_column; ++$column)
            $cells[$row][$column] = ["", 1];
    }

    foreach ($model["blocks"] as $block)
    {
        $row = $data_start_row + (int)$block["row"];
        $start_column = 2 + (int)$block["start"];
        $end_column = 2 + (int)$block["end"];
        $style = school_timeline_xlsx_style_for_mode($block["mode"]);
        for ($column = $start_column; $column <= $end_column; ++$column)
            $cells[$row][$column] = [$column == $start_column ? $block["text"] : "", $style];
        if ($end_column > $start_column)
            $merges[] = school_timeline_column_name($start_column).$row.":".school_timeline_column_name($end_column).$row;
    }

    $note = "Colonnes absolues : JAN / AVR / JUI / SEP. Les dates first_day des cycles sont arrondies au repère le plus proche. A–D = trimestres pédagogiques de l'année de formation (A = trimestre 1). Un changement de mode d'inscription coupe la barre et change sa couleur. Source : cycles et inscriptions user_cycle.";
    $cells[$note_row][1] = ["", 0];
    $cells[$note_row][2] = [$note, 0];
    if ($last_column > 2)
        $merges[] = "B".$note_row.":".school_timeline_column_name($last_column).$note_row;

    ksort($cells);
    $sheet_data = "";
    foreach ($cells as $row_number => $row_cells)
    {
        ksort($row_cells);
        $height = $row_number >= $data_start_row && $row_number <= $last_data_row ? 24 : ($row_number <= 7 ? 20 : 18);
        $sheet_data .= '<row r="'.$row_number.'" ht="'.$height.'" customHeight="1">';
        foreach ($row_cells as $column => $cell)
            $sheet_data .= school_timeline_xlsx_cell($column, $row_number, $cell[0], $cell[1]);
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
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<dimension ref="A1:'.school_timeline_column_name($last_column).$note_row.'"/>'
        .'<sheetViews><sheetView workbookViewId="0"><pane xSplit="1" ySplit="2" topLeftCell="B9" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>'
        .'<sheetFormatPr defaultRowHeight="18"/>'
        .'<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="'.$last_column.'" width="18" customWidth="1"/></cols>'
        .'<sheetData>'.$sheet_data.'</sheetData>'
        .$merge_xml
        .'<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
        .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
        .'</worksheet>';

    $content = school_timeline_zip([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Frise" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => school_timeline_xlsx_styles(),
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
    if ($content == "")
        return (new ErrorResponse("SchoolTimelineGenerationFailed"));

    $school_code = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($school["codename"] ?? "ecole"));
    $period = (int)$slots[0]["year"]."-".(int)$slots[count($slots) - 1]["year"];
    $filename = "frise_etudiants_".trim($school_code, "-")."_".$period."_".date("Y-m-d").".xlsx";
    return (new ValueResponse([
        "filename" => $filename,
        "content_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "content" => $content,
    ]));
}

function school_timeline_export_year($value)
{
    if ($value === NULL || trim((string)$value) === "")
        return (NULL);
    if (!preg_match('/^[0-9]{4}$/', trim((string)$value)))
        return (false);
    $year = (int)$value;
    return ($year >= 1900 && $year <= 2200 ? $year : false);
}

function school_timeline_generate($id_school, $start_year = NULL, $end_year = NULL)
{
    $id_school = (int)$id_school;
    $school = fetch_school($id_school);
    if (!is_array($school) || !isset($school["id"]))
        return (new ErrorResponse("InvalidParameter", "school"));
    $start_year = school_timeline_export_year($start_year);
    $end_year = school_timeline_export_year($end_year);
    if ($start_year === false || $end_year === false || ($start_year !== NULL && $end_year !== NULL && $start_year > $end_year))
        return (new ErrorResponse("InvalidParameter", "timeline period"));
    $rows = school_timeline_collect($id_school);
    if (!count($rows))
        return (new ErrorResponse("SchoolTimelineNoData"));
    $model = school_timeline_build_model($rows, $start_year, $end_year);
    if (!empty($model["invalid_range"]) || !count($model["blocks"] ?? []))
        return (new ErrorResponse("SchoolTimelineNoData"));
    return (school_timeline_xlsx($school, $model));
}
