<?php
require_once (__DIR__."/../tools/internship_calendar.php");
require_once (__DIR__."/../tools/internship_session_plan.php");
require_once (__DIR__."/../tools/internship_session_sync.php");

function check($condition, $message)
{
    if (!$condition)
        throw new RuntimeException($message);
}

$source = internship_session_sync_source_from_output("user-document:42:0123456789abcdef0123456789abcdef:3");
check(is_array($source) && $source["id_user"] === 42, "source parsing");
check($source["source_id"] === "user-document:42:0123456789abcdef0123456789abcdef:3", "stable source id");
check(internship_session_sync_source_from_output("prospect-admission:foreign:42") === NULL, "unrelated output ignored");

$desired = [
    "internship:2026-11-10:morning" => [
        "id_activity" => -1, "id_laboratory" => -1, "id_team" => -1, "id_user" => 42,
        "name" => "Stage en entreprise", "source_type" => "document", "source_id" => $source["source_id"],
        "source_key" => "internship:2026-11-10:morning",
        "begin_date" => "2026-11-10 09:00:00", "end_date" => "2026-11-10 13:00:00",
    ],
    "internship:2026-11-12:afternoon" => [
        "id_activity" => -1, "id_laboratory" => -1, "id_team" => -1, "id_user" => 42,
        "name" => "Stage en entreprise", "source_type" => "document", "source_id" => $source["source_id"],
        "source_key" => "internship:2026-11-12:afternoon",
        "begin_date" => "2026-11-12 14:00:00", "end_date" => "2026-11-12 18:00:00",
    ],
];
$existing = [
    "internship:2026-11-10:morning" => $desired["internship:2026-11-10:morning"] + ["id" => 10, "deleted" => NULL, "maximum_subscription" => NULL],
    "internship:2026-11-11:morning" => [
        "id" => 11, "deleted" => NULL, "id_activity" => -1, "id_laboratory" => -1, "id_team" => -1, "id_user" => 42,
        "name" => "Stage en entreprise", "source_type" => "document", "source_id" => $source["source_id"],
        "source_key" => "internship:2026-11-11:morning", "begin_date" => "2026-11-11 09:00:00", "end_date" => "2026-11-11 13:00:00", "maximum_subscription" => NULL,
    ],
    "internship:2026-11-12:afternoon" => [
        "id" => 12, "deleted" => "2026-11-01 10:00:00", "id_activity" => -1, "id_laboratory" => -1, "id_team" => -1, "id_user" => 42,
        "name" => "Ancien nom", "source_type" => "document", "source_id" => $source["source_id"],
        "source_key" => "internship:2026-11-12:afternoon", "begin_date" => "2026-11-12 13:00:00", "end_date" => "2026-11-12 17:00:00", "maximum_subscription" => NULL,
    ],
];
$diff = internship_session_sync_diff($desired, $existing);
check(count($diff["unchanged"]) === 1, "unchanged row");
check(count($diff["delete"]) === 1 && isset($diff["delete"]["internship:2026-11-11:morning"]), "removed T is deleted");
check(count($diff["update"]) === 1 && isset($diff["update"]["internship:2026-11-12:afternoon"]), "changed/deleted row is revived and updated");
check(count($diff["create"]) === 0, "no unexpected create");

$desired["internship:2026-11-13:morning"] = $desired["internship:2026-11-10:morning"];
$desired["internship:2026-11-13:morning"]["source_key"] = "internship:2026-11-13:morning";
$desired["internship:2026-11-13:morning"]["begin_date"] = "2026-11-13 09:00:00";
$desired["internship:2026-11-13:morning"]["end_date"] = "2026-11-13 13:00:00";
$diff = internship_session_sync_diff($desired, $existing);
check(isset($diff["create"]["internship:2026-11-13:morning"]), "new T is created");

echo "internship_session_sync: OK\n";

// A stale/wrong beneficiary on a generated row is repaired from the output
// owner instead of leaving the session on another student's calendar.
$wrong_owner = $desired["internship:2026-11-10:morning"] + ["id" => 20, "deleted" => NULL, "maximum_subscription" => NULL];
$wrong_owner["id_user"] = 99;
$owner_diff = internship_session_sync_diff(
    ["internship:2026-11-10:morning" => $desired["internship:2026-11-10:morning"]],
    ["internship:2026-11-10:morning" => $wrong_owner]
);
check(isset($owner_diff["update"]["internship:2026-11-10:morning"]), "wrong beneficiary is repaired");
