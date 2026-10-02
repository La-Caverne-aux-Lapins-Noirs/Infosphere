<?php
require_once (__DIR__.'/../tools/activity_scrum.php');

function scrum_assert($condition, $message)
{
    if (!$condition)
        throw new RuntimeException($message);
}

function scrum_ts($date)
{
    return ((new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp());
}

$plan = activity_scrum_plan(
    'GFX-01C-PRJ-000',
    scrum_ts('2026-09-07 09:00:00'), // Monday
    scrum_ts('2026-09-18 17:00:00'), // Friday, two weeks
    1,
    '14:00',
    '18:00'
);
scrum_assert($plan['ok'], 'basic plan rejected: '.implode(' | ', $plan['errors']));
$acts = $plan['activities'];
scrum_assert($acts[0]['codename'] === 'GFX-01C-PRJ-000-PLN-000', 'planning codename mismatch');
scrum_assert($acts[0]['slot_duration'] === 30, 'planning slot duration mismatch');
$daily = array_values(array_filter($acts, fn($a) => $a['kind'] === 'daily'));
$retro = array_values(array_filter($acts, fn($a) => $a['kind'] === 'retrospective'));
$planning = array_values(array_filter($acts, fn($a) => $a['kind'] === 'planning'));
scrum_assert(count($planning) === 2, 'expected two planning activities');
scrum_assert(count($daily) === 6, 'expected six daily activities');
scrum_assert(count($retro) === 2, 'expected two retrospectives');
scrum_assert($daily[0]['codename'] === 'GFX-01C-PRJ-000-TLJ-000', 'daily codename mismatch');
scrum_assert($daily[0]['slot_duration'] === 10, 'daily slots must be 10 minutes');
scrum_assert($retro[0]['codename'] === 'GFX-01C-PRJ-000-RET-000', 'retro codename mismatch');

$midweek = activity_scrum_plan(
    'GFX-01C-PRJ-000',
    scrum_ts('2026-09-09 15:00:00'), // Wednesday, inside 14-18 window
    scrum_ts('2026-09-18 17:00:00'),
    1,
    '14:00',
    '18:00'
);
scrum_assert($midweek['ok'], 'midweek plan rejected');
scrum_assert($midweek['activities'][0]['kind'] === 'planning', 'first meeting after subject must be planning');
scrum_assert($midweek['activities'][0]['begin'] === scrum_ts('2026-09-09 15:00:00'), 'first planning must not predate subject');
scrum_assert($midweek['activities'][1]['kind'] === 'daily', 'Thursday should be daily');
scrum_assert($midweek['activities'][2]['kind'] === 'retrospective', 'Friday should be retrospective');

$late = activity_scrum_plan(
    'GFX-01C-PRJ-000',
    scrum_ts('2026-09-11 17:45:00'), // Friday, not enough time for a 30m planning slot
    scrum_ts('2026-09-25 17:00:00'),
    1,
    '14:00',
    '18:00'
);
scrum_assert($late['ok'], 'late subject plan rejected');
scrum_assert($late['activities'][0]['begin'] === scrum_ts('2026-09-14 14:00:00'), 'late Friday subject should move planning to Monday');

$bad = activity_scrum_plan('GFX-01C-PRJ-000', scrum_ts('2026-09-07'), scrum_ts('2026-09-18'), 4, '14:00', '18:00');
scrum_assert(!$bad['ok'], 'invalid sprint length accepted');
$bad = activity_scrum_plan('GFX-01C-PRJ-000', scrum_ts('2026-09-07'), scrum_ts('2026-09-18'), 1, '18:00', '14:00');
scrum_assert(!$bad['ok'], 'invalid daily window accepted');

echo "activity_scrum: OK\n";
