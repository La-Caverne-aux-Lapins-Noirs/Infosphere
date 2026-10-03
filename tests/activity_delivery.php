<?php

function date_to_timestamp($value)
{
    return (is_int($value) ? $value : strtotime((string)$value." UTC"));
}

function now()
{
    return (strtotime("2026-10-03 12:00:00 UTC"));
}

require_once (__DIR__."/../tools/activity_delivery.php");

function activity_delivery_test_assert($condition, $message)
{
    if (!$condition)
        throw new RuntimeException($message);
}

activity_delivery_test_assert(
    activity_delivery_is_missing_response(["result" => "ko", "message" => "NothingTurnedIn"]),
    "NothingTurnedIn must be a missing delivery"
);
activity_delivery_test_assert(
    activity_delivery_is_missing_response(["result" => "ko", "message" => "No such file or directory"]),
    "missing repository path must be a missing delivery"
);
activity_delivery_test_assert(
    !activity_delivery_is_missing_response(["result" => "ko", "message" => "ConnectionTimeout"]),
    "technical failures must not be a missing delivery"
);
activity_delivery_test_assert(
    activity_delivery_row_is_work(["status" => "automatic_pickup"]),
    "automatic pickup marker must count as delivered work"
);
activity_delivery_test_assert(
    !activity_delivery_row_is_work(["status" => "automatic_correction"]),
    "automatic correction must not count as delivered work"
);
activity_delivery_test_assert(
    !activity_delivery_row_is_work(["status" => "missing_delivery"]),
    "missing-delivery marker must not count as delivered work"
);
activity_delivery_test_assert(
    activity_delivery_team_status("2026-10-03 11:00:00", []) === "missing",
    "past pickup without work must be missing"
);
activity_delivery_test_assert(
    activity_delivery_team_status("2026-10-03 13:00:00", []) === "pending",
    "future pickup without work must be pending"
);
activity_delivery_test_assert(
    activity_delivery_team_status("2026-10-03 11:00:00", [["status" => "student"]]) === "delivered",
    "real pickup must be delivered"
);

echo "activity_delivery: OK\n";
