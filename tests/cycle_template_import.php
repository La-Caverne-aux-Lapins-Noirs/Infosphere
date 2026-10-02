<?php
require_once (__DIR__."/../tools/cycle_template_import.php");

function cycle_template_import_test_assert($condition, $message)
{
    if (!$condition)
        throw new RuntimeException($message);
}

function cycle_template_import_test_fixture()
{
    return [
        "Format" => "CycleTemplate",
        "Version" => 1,
        "Cycle" => [
            "Codename" => "EF4-GAME-T1",
            "Cycle" => 4,
            "Objective" => 100,
            "Fr" => ["Name" => "Spécialisation jeu vidéo - trimestre 1"],
        ],
        "Calendar" => [
            "Activity01" => [
                "Codename" => "ALG-01A-TPE-000",
                "Type" => "PracticalWork",
                "Fr" => ["Name" => "Travaux pratiques 1"],
                "Sessions" => [
                    "Morning" => [
                        "Week" => 0,
                        "Day" => "Monday",
                        "Begin" => "09:00",
                        "End" => "12:00",
                    ],
                ],
            ],
            "Activity02" => [
                "Codename" => "GAME-01A-PRJ-000",
                "Type" => "Project",
                "Fr" => ["Name" => "Projet de spécialisation"],
                "Sessions" => [
                    "Kickoff" => [
                        "Week" => 1,
                        "Day" => "mardi",
                        "Start" => "14:00",
                        "Duration" => "03:30",
                    ],
                ],
            ],
            "Activity03" => [
                "Codename" => "ALG-01A-DEF-000",
                "Type" => "DemoMeeting",
                "Fr" => ["Name" => "Soutenance"],
                "Sessions" => [
                    "Defense" => [
                        "Week" => 4,
                        "Day" => "Friday",
                        "Begin" => "09:00",
                        "End" => "17:00",
                        "AppointmentSlots" => [
                            "Slot1" => ["Begin" => "09:00", "End" => "09:30"],
                            "Slot2" => ["Begin" => "09:30", "End" => "10:00"],
                        ],
                    ],
                ],
            ],
        ],
        "Matters" => [
            "Matter01" => [
                "Codename" => "ALG-01A",
                "Fr" => ["Name" => "Algorithmique"],
            ],
            "Matter02" => [
                "Codename" => "GAME-01A",
                "Fr" => ["Name" => "Gameplay"],
            ],
        ],
    ];
}

$valid = cycle_template_import_compile(cycle_template_import_test_fixture());
cycle_template_import_test_assert($valid["ok"], "valid fixture rejected: ".implode(" | ", $valid["errors"]));
cycle_template_import_test_assert(count($valid["plan"]["calendar"]) === 3, "calendar count mismatch");
cycle_template_import_test_assert(count($valid["plan"]["matters"]) === 2, "matter count mismatch");
cycle_template_import_test_assert(
    count($valid["plan"]["matters"]["ALG-01A"]["activities"]) === 2,
    "ALG-01A activities were not deduced from their codenames"
);
cycle_template_import_test_assert(
    $valid["plan"]["matters"]["ALG-01A"]["activities"][0]["codename"] === "ALG-01A-TPE-000",
    "activity codename was rewritten during matter deduction"
);
cycle_template_import_test_assert(
    $valid["plan"]["calendar"]["ALG-01A-TPE-000"]["sessions"][0]["date"]["begin"] === 9 * 3600,
    "week/day/time relative offset mismatch"
);
cycle_template_import_test_assert(
    $valid["plan"]["calendar"]["GAME-01A-PRJ-000"]["sessions"][0]["date"]["end"] ===
        (8 * 86400 + 17 * 3600 + 30 * 60),
    "duration normalization mismatch"
);
cycle_template_import_test_assert(
    count($valid["plan"]["calendar"]["ALG-01A-DEF-000"]["sessions"][0]["appointment_slots"]) === 2,
    "appointment slots missing"
);

$unassigned = cycle_template_import_test_fixture();
unset($unassigned["Matters"]["Matter02"]);
$ret = cycle_template_import_compile($unassigned);
cycle_template_import_test_assert(!$ret["ok"], "activity without matching matter was accepted");
cycle_template_import_test_assert(
    (bool)array_filter($ret["errors"], fn($x) => strpos($x, "aucune matière ne correspond") !== false),
    "missing matter-prefix error not reported"
);

$empty_matter = cycle_template_import_test_fixture();
$empty_matter["Matters"]["Unused"] = [
    "Codename" => "SYS-01A",
    "Fr" => ["Name" => "Système"],
];
$ret = cycle_template_import_compile($empty_matter);
cycle_template_import_test_assert(
    $ret["ok"],
    "empty matter was rejected: ".implode(" | ", $ret["errors"])
);
cycle_template_import_test_assert(
    isset($ret["plan"]["matters"]["SYS-01A"])
        && count($ret["plan"]["matters"]["SYS-01A"]["activities"]) === 0,
    "empty matter was not preserved in the compiled plan"
);

// Prefixes may themselves be nested. The most specific (longest) matter
// codename owns the activity.
$nested = cycle_template_import_test_fixture();
$nested["Matters"]["Broad"] = ["Codename" => "ALG", "Fr" => ["Name" => "Algorithmique générale"]];
$nested["Calendar"]["BroadActivity"] = [
    "Codename" => "ALG-TPE-000",
    "Type" => "PracticalWork",
    "Fr" => ["Name" => "TP général"],
];
$ret = cycle_template_import_compile($nested);
cycle_template_import_test_assert($ret["ok"], "nested matter prefixes rejected: ".implode(" | ", $ret["errors"]));
cycle_template_import_test_assert(
    count($ret["plan"]["matters"]["ALG-01A"]["activities"]) === 2,
    "specific matter prefix did not win over broader prefix"
);
cycle_template_import_test_assert(
    count($ret["plan"]["matters"]["ALG"]["activities"]) === 1,
    "broad matter did not retain its own activity"
);

// Same semantic file, but written with snake_case/lowercase aliases and a
// different object ordering: its canonical signature must stay identical.
$alternate = cycle_template_import_normalize(cycle_template_import_test_fixture());
$alternate = array_reverse($alternate, true);
$alternate["cycle"] = array_reverse($alternate["cycle"], true);
$ret = cycle_template_import_compile($alternate);
cycle_template_import_test_assert($ret["ok"], "alternate spelling rejected");
cycle_template_import_test_assert(
    $ret["plan"]["signature"] === $valid["plan"]["signature"],
    "canonical signature changes with harmless spelling/order differences"
);

// Dabsic scope labels are not Infosphere identities. Renaming them while
// keeping explicit Codenames must not change the compiled plan signature.
$scope_renamed = cycle_template_import_test_fixture();
$scope_renamed["Calendar"] = [
    "Foo" => $scope_renamed["Calendar"]["Activity01"],
    "Bar" => $scope_renamed["Calendar"]["Activity02"],
    "Baz" => $scope_renamed["Calendar"]["Activity03"],
];
$scope_renamed["Matters"] = [
    "Alpha" => $scope_renamed["Matters"]["Matter01"],
    "Beta" => $scope_renamed["Matters"]["Matter02"],
];
$ret = cycle_template_import_compile($scope_renamed);
cycle_template_import_test_assert($ret["ok"], "renamed Dabsic scopes rejected");
cycle_template_import_test_assert(
    $ret["plan"]["signature"] === $valid["plan"]["signature"],
    "Dabsic scope names leaked into semantic identity"
);

$missing_codename = cycle_template_import_test_fixture();
unset($missing_codename["Calendar"]["Activity01"]["Codename"]);
$ret = cycle_template_import_compile($missing_codename);
cycle_template_import_test_assert(!$ret["ok"], "calendar activity without Codename was accepted");

$legacy_assignment = cycle_template_import_test_fixture();
$legacy_assignment["Matters"]["Matter01"]["Activities"] = ["ALG-01A-TPE-000"];
$ret = cycle_template_import_compile($legacy_assignment);
cycle_template_import_test_assert(!$ret["ok"], "legacy Activities assignment syntax was silently accepted");
cycle_template_import_test_assert(
    (bool)array_filter($ret["errors"], fn($x) => strpos($x, "champ inconnu") !== false),
    "legacy assignment syntax was not reported as unsupported"
);

$rich = cycle_template_import_test_fixture();
$rich["Cycle"]["Teachers"] = ["jbrillante"];
$rich["Cycle"]["Laboratories"] = [];
$rich["Matters"]["Matter01"]["GradeA"] = 80;
$rich["Matters"]["Matter01"]["GradeB"] = 65;
$rich["Matters"]["Matter01"]["Validation"] = 3;
$rich["Matters"]["Matter01"]["Skills"] = ["skill-algo"];
$rich["Calendar"]["Activity01"]["ReferenceActivity"] = "ALG-01A-DEF-000";
$rich["Calendar"]["Activity01"]["ProgressiveSlotOpening"] = true;
$rich["Calendar"]["Activity01"]["TeamBasedSlotOpening"] = false;
$rich["Calendar"]["Activity01"]["Todolist"] = "Préparer les machines";
$rich["Calendar"]["Activity01"]["DeclarationType"] = "Local";
$rich["Calendar"]["Activity01"]["EmergenceDate"] = [
    "Week" => 1, "Day" => "Tuesday", "Time" => "09:30",
];
$rich["Calendar"]["Activity01"]["Teachers"] = [
    ["Codename" => "teacher-a", "TeacherPay" => 120, "AssistantPay" => 60],
];
$rich["Calendar"]["Activity01"]["Laboratories"] = ["LAB-GAME"];
$rich["Calendar"]["Activity01"]["Medals"] = [
    ["Codename" => "MEDAL-ALGO", "Role" => 1, "Money" => 2, "Local" => true],
];
$rich["Calendar"]["Activity01"]["Supports"] = [
    ["Type" => "Support", "Codename" => "SUP-ALGO", "Chapter" => 0],
];
$rich["Calendar"]["Activity01"]["Scales"] = ["SCALE-ALGO"];
$rich["Calendar"]["Activity01"]["Mcqs"] = ["MCQ-ALGO"];
$rich["Calendar"]["Activity01"]["Satisfaction"] = ["SAT-ALGO"];
$rich["Calendar"]["Activity01"]["Software"] = [
    ["Software" => "git@example/algo", "Type" => "Evaluator"],
];
$ret = cycle_template_import_compile($rich);
cycle_template_import_test_assert($ret["ok"], "rich métier fields rejected: ".implode(" | ", $ret["errors"]));
$activity = $ret["plan"]["calendar"]["ALG-01A-TPE-000"];
cycle_template_import_test_assert($activity["progressive_slot_opening"] === true, "progressive slot flag missing");
cycle_template_import_test_assert($activity["emergence_date"] === (8 * 86400 + 9 * 3600 + 30 * 60), "relative date mismatch");
cycle_template_import_test_assert($activity["_relations"]["teachers"][0]["teacher_pay"] === 120, "teacher relation fields missing");
cycle_template_import_test_assert($activity["_relations"]["medals"][0]["money"] === 2, "medal fields missing");
cycle_template_import_test_assert($activity["_relations"]["software"][0]["type"] === 0, "software type normalization mismatch");
cycle_template_import_test_assert(isset($ret["plan"]["cycle"]["_relations"]["teachers"]), "cycle teachers missing");


// Optional métier fields stay optional: the base fixture contains none of the
// following fields and must remain valid. Validation applies only when a field
// is explicitly present in the Dabsic source.
$optional = cycle_template_import_compile(cycle_template_import_test_fixture());
cycle_template_import_test_assert($optional["ok"], "optional métier fields became mandatory");

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity01"]["Subscription"] = "sometimes";
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "invalid subscription was accepted");
cycle_template_import_test_assert(
    (bool)array_filter($ret["errors"], fn($x) => strpos($x, "subscription") !== false),
    "invalid subscription was not reported"
);

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity01"]["ProgressiveSlotOpening"] = "maybe";
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "invalid boolean was accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Matters"]["Matter01"]["GradeA"] = 60;
$invalid["Matters"]["Matter01"]["GradeB"] = 70;
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "inverted grade thresholds were accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity01"]["Money"] = -1;
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "negative activity money was accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity01"]["RepositoryName"] = "repo with spaces";
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "repository name containing spaces was accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity01"]["MinTeamSize"] = 4;
$invalid["Calendar"]["Activity01"]["MaxTeamSize"] = 2;
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "inconsistent team-size bounds were accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity01"]["DeclarationType"] = 3;
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "invalid declaration type was accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity01"]["Teachers"] = [
    ["Codename" => "teacher-a", "TeacherPay" => "free"],
];
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "invalid teacher pay was accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity01"]["Sessions"]["Morning"]["MaximumSubscription"] = "many";
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "invalid session maximum subscription was accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity03"]["Sessions"]["Defense"]["AppointmentSlots"]["Outside"] = [
    "Begin" => "17:00", "End" => "18:00",
];
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "appointment slot outside its session was accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Matters"]["Matter01"]["ReplacementSubscription"] = 7;
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "invalid replacement subscription was accepted");

$invalid = cycle_template_import_test_fixture();
$invalid["Calendar"]["Activity01"]["Fr"] = ["Name" => "TP", "Unexpected" => "x"];
$ret = cycle_template_import_compile($invalid);
cycle_template_import_test_assert(!$ret["ok"], "unknown localized field was accepted");


$empty_calendar = cycle_template_import_test_fixture();
$empty_calendar["Calendar"] = [];
$empty_calendar["Matters"] = [
    "Empty" => [
        "Codename" => "EMPTY-01A",
        "Fr" => ["Name" => "Matière vide"],
    ],
];
$ret = cycle_template_import_compile($empty_calendar);
cycle_template_import_test_assert($ret["ok"], "explicit empty Calendar rejected: ".implode(" | ", $ret["errors"]));

echo "cycle_template_import: OK\n";
