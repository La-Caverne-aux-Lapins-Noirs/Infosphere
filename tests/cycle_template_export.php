<?php

function date_to_timestamp($value)
{
    return (strtotime((string)$value." UTC"));
}

require_once (__DIR__."/../tools/cycle_template_export.php");

function cycle_template_export_test_assert($condition, $message)
{
    if (!$condition)
        throw new RuntimeException($message);
}

$base = date_to_timestamp("2026-09-07 00:00:00");
$parts = cycle_template_export_relative_parts("2026-09-16 14:30:00", $base);
cycle_template_export_test_assert($parts["week"] === 1, "relative week mismatch");
cycle_template_export_test_assert($parts["day"] === "Wednesday", "relative day mismatch");
cycle_template_export_test_assert($parts["time"] === "14:30", "relative time mismatch");
cycle_template_export_test_assert(cycle_template_export_duration(10 * 60) === "00:10:00", "duration mismatch");
cycle_template_export_test_assert(
    cycle_template_export_filename("EF4C JEU/2026") === "cycle_EF4C_JEU_2026.dab",
    "filename sanitization mismatch"
);

$negative = false;
try
{
    cycle_template_export_relative_parts("2026-09-06 23:59:00", $base);
}
catch (RuntimeException $e)
{
    $negative = true;
}
cycle_template_export_test_assert($negative, "negative relative date was silently accepted");

echo "cycle_template_export: OK\n";
