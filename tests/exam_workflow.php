<?php

require_once __DIR__ . '/../tools/activity_delivery.php';
require_once __DIR__ . '/../tools/evaluation_history.php';
require_once __DIR__ . '/../tools/exam_workflow.php';

function exam_test_same($actual, $expected, string $message): void
{
    if ($actual !== $expected)
        throw new RuntimeException($message . ': ' . var_export($actual, true));
}

date_default_timezone_set('Europe/Paris');
$start = strtotime('2026-11-18 13:30:00');
$end = $start + 3600;
$team = [
    'begin_date' => '2026-11-18 13:30:00', 'deadline' => '2026-11-18 14:30:00',
    'frequency' => 15, 'subject_appeir_date' => '2026-11-18 13:46:00',
];
exam_test_same(exam_workflow_intermediate_due($team, $start + 900), null, 'before the subject appears');
exam_test_same(exam_workflow_intermediate_due($team, $start + 1800), $start + 1800, 'first slot after subject availability');
exam_test_same(exam_workflow_intermediate_due($team, $end - 1), $start + 2700, 'last intermediate slot');
exam_test_same(exam_workflow_intermediate_due($team, $end), null, 'the final pickup is not intermediate');
$team['frequency'] = 0;
exam_test_same(exam_workflow_intermediate_due($team, $end - 1), null, 'no cadence');

$marker = ['status' => 'automatic_pickup', 'observation' => 'Official exam pickup Exam_20261118_133000_official.tar.gz'];
exam_test_same(exam_workflow_archive_name($marker), 'Exam_20261118_133000_official.tar.gz', 'official archive bound to the pickup');
exam_test_same(exam_workflow_archive_name(['status' => 'missing_delivery']), null, 'missing delivery has no archive');
$report = '{Exercises [ Name="Exercise 1", Status="Success", Message="Private details" ] }';
$trace = exam_workflow_trace_text($start, $report);
exam_test_same(str_contains($trace, 'Exercise 1 — Réussi'), true, 'student sees the result');
exam_test_same(str_contains($trace, 'Private details'), false, 'teacher diagnostics are excluded');

echo "exam_workflow: OK\n";
