<?php

if (PHP_SAPI !== 'cli')
    exit(1);

chdir(dirname(__DIR__));
$Language = 'fr';
require_once 'language.php';
require_once 'tools/index.php';
$runtime = distrans_runtime_dir();
$lock = $runtime === null ? false : @fopen($runtime . '/exam-corrections.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB))
    exit(0);
$admin = fetch_user(1);
if ($admin->is_error())
    exit(1);
$OriginalUser = $User = $admin->value;
exam_workflow_correct();
