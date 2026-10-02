<?php

require_once (__DIR__."/../tools/cycle_timeline.php");

function cycle_timeline_test_assert($condition, $message)
{
    if (!$condition)
        throw new RuntimeException($message);
}

$data = [
    "cycle" => ["codename" => "EF4C-JEU"],
    "matters" => [
        ["codename" => "GFX-JEU-04C", "fr" => ["name" => "Programmation graphique"]],
        ["codename" => "HIS-JEU-04C", "fr" => ["name" => "Culture et histoire"]],
    ],
    "calendar" => [
        [
            "codename" => "GFX-JEU-04C-TPE-000",
            "type" => "PracticalWork",
            "fr" => ["name" => "Sprites"],
            "sessions" => [
                ["week" => 0, "day" => "Monday", "begin" => "09:00", "end" => "13:00"],
                ["week" => 0, "day" => "Monday", "begin" => "14:00", "end" => "18:00"],
            ],
        ],
        [
            "codename" => "GFX-JEU-04C-PRJ-000",
            "type" => "Project",
            "fr" => ["name" => "Grand projet graphique"],
            "subject_appeir_date" => ["week" => 0, "day" => "Tuesday", "time" => "09:00"],
            "pickup_date" => ["week" => 0, "day" => "Friday", "time" => "23:42"],
        ],
        [
            "codename" => "HIS-JEU-04C-RUE-000",
            "type" => "Rush",
            "fr" => ["name" => "Ruée histoire"],
            "subject_appeir_date" => ["week" => 0, "day" => "Wednesday", "time" => "19:00"],
            "pickup_date" => ["week" => 0, "day" => "Friday", "time" => "23:42"],
        ],
        [
            "codename" => "GFX-JEU-04C-COL-000",
            "type" => "Challenge",
            "fr" => ["name" => "Colle graphisme"],
            "subject_appeir_date" => ["week" => 0, "day" => "Monday", "time" => "08:00"],
            "pickup_date" => ["week" => 0, "day" => "Monday", "time" => "18:00"],
            "sessions" => [["week" => 0, "day" => "Wednesday", "begin" => "14:00", "end" => "18:00"]],
        ],
        [
            "codename" => "HIS-JEU-04C-EXM-000",
            "type" => "Exam",
            "fr" => ["name" => "Examen histoire"],
            "sessions" => [["week" => 0, "day" => "Wednesday", "begin" => "10:00", "end" => "12:00"]],
        ],
        [
            "codename" => "GFX-JEU-04C-STE-000",
            "type" => "DemoMeeting",
            "fr" => ["name" => "Soutenance graphique"],
            "sessions" => [["week" => 0, "day" => "Friday", "begin" => "14:00", "end" => "18:00"]],
        ],
    ],
];

$model = cycle_timeline_build_model($data);
cycle_timeline_test_assert($model["tp_rows"] === 4, "global session area must keep four rows");
cycle_timeline_test_assert(count($model["tp_sessions"][0]) === 2, "Monday sessions must stay together in the global session area");
cycle_timeline_test_assert($model["tp_sessions"][0][0]["matter"] !== "", "session matter key missing");
cycle_timeline_test_assert(count($model["assessment_sessions"][2]) === 2, "challenge and exam must share the assessment row");
cycle_timeline_test_assert(count($model["defense_sessions"][4]) === 1, "demo meeting must be routed to the defense row");
cycle_timeline_test_assert(count($model["blocks"]) === 2, "long activities must be grouped in one block per matter");
$gfx = $model["blocks"][0];
$his = $model["blocks"][1];
cycle_timeline_test_assert($gfx["matter"]["codename"] === "GFX-JEU-04C", "GFX timeline block missing");
cycle_timeline_test_assert(count($gfx["spans"]) === 1, "GFX long activity missing");
cycle_timeline_test_assert($gfx["spans"][0]["text"] === "Grand projet graphique", "4-day project must show its full name");
cycle_timeline_test_assert($his["matter"]["codename"] === "HIS-JEU-04C", "HIS timeline block missing");
cycle_timeline_test_assert($his["spans"][0]["text"] === "HIS", "short rush must use its matter prefix");
cycle_timeline_test_assert(cycle_timeline_short_code("GFX-JEU-04C") === "GFX", "short code mismatch");

$same_matter_overlap = $data;
$same_matter_overlap["calendar"][] = [
    "codename" => "GFX-JEU-04C-MPJ-001",
    "type" => "MiniProject",
    "fr" => ["name" => "Projet superposé"],
    "subject_appeir_date" => ["week" => 0, "day" => "Wednesday", "time" => "09:00"],
    "pickup_date" => ["week" => 0, "day" => "Friday", "time" => "23:42"],
];
$overlap_model = cycle_timeline_build_model($same_matter_overlap);
$overlap_gfx = $overlap_model["blocks"][0];
cycle_timeline_test_assert($overlap_gfx["timeline_rows"] === 2, "overlapping activities of the same matter need separate lanes");
cycle_timeline_test_assert($overlap_gfx["spans"][0]["lane"] !== $overlap_gfx["spans"][1]["lane"], "overlapping same-matter bars must not overwrite each other");
cycle_timeline_test_assert(count($overlap_model["tp_sessions"][0]) === 2, "timeline grouping must not split the global session area");

$duplicate_template_codes = [
    "cycle" => ["codename" => "EF4C-JEU"],
    "matters" => [
        ["_key" => "activity:10", "codename" => "GFX-JEU-04C", "fr" => ["name" => "Graphisme"]],
        ["_key" => "activity:20", "codename" => "HIS-JEU-04C", "fr" => ["name" => "Histoire"]],
    ],
    "calendar" => [
        [
            "codename" => "VAC-000",
            "_matter_key" => "activity:10",
            "type" => "Class",
            "fr" => ["name" => "Vacance A"],
            "sessions" => [["week" => 0, "day" => "Monday", "begin" => "09:00", "end" => "10:00"]],
        ],
        [
            "codename" => "VAC-000",
            "_matter_key" => "activity:20",
            "type" => "Class",
            "fr" => ["name" => "Vacance B"],
            "sessions" => [["week" => 0, "day" => "Tuesday", "begin" => "09:00", "end" => "10:00"]],
        ],
    ],
];
$duplicate_model = cycle_timeline_build_model($duplicate_template_codes);
cycle_timeline_test_assert(count($duplicate_model["blocks"]) === 0, "session-only matters must not reserve timeline blocks");
cycle_timeline_test_assert(count($duplicate_model["tp_sessions"][0]) === 1, "first duplicated template activity missing");
cycle_timeline_test_assert(count($duplicate_model["tp_sessions"][1]) === 1, "second duplicated template activity missing");
cycle_timeline_test_assert($duplicate_model["tp_sessions"][0][0]["matter"] === "activity:10", "explicit first matter key lost");
cycle_timeline_test_assert($duplicate_model["tp_sessions"][1][0]["matter"] === "activity:20", "explicit second matter key lost");

$compact = cycle_timeline_compact_session_cell($model["assessment_sessions"][2]);
cycle_timeline_test_assert($compact["text"] === "HIS / GFX" || $compact["text"] === "GFX / HIS", "assessment row must combine matter codes");
cycle_timeline_test_assert(strpos($compact["comment"], "Colle graphisme") !== false, "combined assessment comment missing challenge");
cycle_timeline_test_assert(strpos($compact["comment"], "Examen histoire") !== false, "combined assessment comment missing exam");
cycle_timeline_test_assert($compact["matter"] === "", "mixed-matter assessment cell must use a neutral style");
cycle_timeline_test_assert(cycle_timeline_normalize_matter_filter(["10", 20, "10", "x", 0]) === [10, 20], "matter filter normalization mismatch");


$base = strtotime("2026-09-07 00:00:00 UTC");
cycle_timeline_test_assert(
    cycle_timeline_matter_base_timestamp($base, 4, true) === $base - 4 * 7 * 24 * 60 * 60,
    "template matter week_shift must move its relative origin"
);
cycle_timeline_test_assert(
    cycle_timeline_matter_base_timestamp($base, 4, false) === $base,
    "instantiated cycles already contain the matter week_shift in their resolved dates"
);

$comments = cycle_timeline_comments_xml(["B4" => "Sprites\nGFX-JEU-04C-TPE-000"]);
cycle_timeline_test_assert(strpos($comments, 'ref="B4"') !== false, "comment cell reference missing");
cycle_timeline_test_assert(strpos($comments, "Sprites") !== false, "comment text missing");

echo "cycle_timeline: OK\n";
