<?php

require_once (__DIR__."/../tools/internship_calendar.php");
require_once (__DIR__."/../tools/internship_session_plan.php");

$configuration = [
    "Internship" => [
        "StartDate" => "10/11/2026",
        "EndDate" => "12/11/2026",
        "ScheduleCalendar" => [
            "Format" => "HalfDayV1",
            "StartDate" => "2026-11-10",
            "EndDate" => "2026-11-12",
            "Days" => [
                "D20261110" => ["Morning" => "T", "Afternoon" => "E"],
                "D20261111" => ["Morning" => "F", "Afternoon" => "T"],
                "D20261112" => ["Morning" => "T", "Afternoon" => "T"],
            ],
        ],
    ],
];

$plan = internship_session_plan($configuration, 42, "20260917153000_deadbeefdeadbeef", [
    "morning_start" => "08:30",
    "morning_end" => "12:30",
    "afternoon_start" => "13:30",
    "afternoon_end" => "17:30",
]);
if (!$plan["ok"])
    throw new RuntimeException("Planner failed");
if (count($plan["sessions"]) !== 4)
    throw new RuntimeException("Expected four T half-days");
if (isset($plan["sessions"]["internship:2026-11-10:afternoon"]))
    throw new RuntimeException("E half-day must not create a session");
$morning = $plan["sessions"]["internship:2026-11-10:morning"] ?? NULL;
if (!is_array($morning) || $morning["begin_date"] !== "2026-11-10 08:30:00" ||
    $morning["source_type"] !== "document" || $morning["id_user"] !== 42)
    throw new RuntimeException("Unexpected morning session");
$holiday_work = $plan["sessions"]["internship:2026-11-11:afternoon"] ?? NULL;
if (!is_array($holiday_work))
    throw new RuntimeException("Explicit T on a holiday must remain work");

echo "internship_session_plan: OK\n";

// Contractual dates are authoritative even if stale calendar nodes remain.
$shortened = $configuration;
$shortened["Internship"]["EndDate"] = "11/11/2026";
$shortened_plan = internship_session_plan($shortened, 42, "user-document:42:0123456789abcdef0123456789abcdef:3", [
    "morning_start" => "08:30",
    "morning_end" => "12:30",
    "afternoon_start" => "13:30",
    "afternoon_end" => "17:30",
]);
if (!$shortened_plan["ok"] || isset($shortened_plan["sessions"]["internship:2026-11-12:morning"]))
    throw new RuntimeException("Contractual end date must exclude stale calendar days");
$shortened_validation = internship_session_validate_configuration(
    $shortened,
    42,
    "user-document:42:0123456789abcdef0123456789abcdef:3",
    ["morning_start" => "08:30", "morning_end" => "12:30", "afternoon_start" => "13:30", "afternoon_end" => "17:30"]
);
if ($shortened_validation["ok"] || !count($shortened_validation["errors"]))
    throw new RuntimeException("Finalization must reject calendar/date metadata mismatch");

$empty = $configuration;
$empty["Internship"]["ScheduleCalendar"]["Days"] = [];
$empty_validation = internship_session_validate_configuration($empty, 42, "user-document:42:0123456789abcdef0123456789abcdef:3");
if ($empty_validation["ok"])
    throw new RuntimeException("Finalization must reject an internship calendar with no T half-day");

$fake_holiday = $configuration;
$fake_holiday["Internship"]["ScheduleCalendar"]["Days"]["D20261110"]["Morning"] = "F";
$fake_holiday_validation = internship_session_validate_configuration($fake_holiday, 42, "user-document:42:0123456789abcdef0123456789abcdef:3");
if ($fake_holiday_validation["ok"])
    throw new RuntimeException("F must only be accepted on an actual French public holiday");

$transport = internship_calendar_transport_encode([
    "start" => "2026-11-09",
    "end" => "2026-11-11",
    "days" => [
        "2026-11-09" => ["am" => "T", "pm" => "T"],
        "2026-11-10" => ["am" => "T", "pm" => "T"],
        "2026-11-11" => ["am" => "T", "pm" => "F"],
    ],
]);
// Default stage day: 09:00-12:30 + 13:30-17:00 = 7h. A T half-day
// is 3.5h and remains half a contractual day, so this week is exactly 2.5 days.
$metrics = internship_calendar_work_metrics($transport, 3.5, 3.5);
if (!is_array($metrics) || abs($metrics["days"] - 2.5) > 0.001 || abs($metrics["hours"] - 17.5) > 0.001)
    throw new RuntimeException("Unexpected internship calendar attendance metrics");
if (abs($metrics["hours_per_day"] - 7.0) > 0.001 || abs($metrics["days_per_week"] - 2.5) > 0.001)
    throw new RuntimeException("Unexpected internship calendar usual rhythm");
if (($metrics["monthly_hours"]["2026-11"] ?? 0) != 17.5)
    throw new RuntimeException("Unexpected monthly internship hours");
if (internship_calendar_format_number(17.5 * 4.35, 2, false) !== "76,13")
    throw new RuntimeException("Unexpected internship total payment formatting");
$payment_text = internship_calendar_payment_schedule($metrics, "4,35");
if (strpos($payment_text, "novembre 2026 : 17,5 h, soit 76,13 €") === false)
    throw new RuntimeException("Unexpected internship monthly payment schedule");

echo "internship_calendar_financials: OK\n";
