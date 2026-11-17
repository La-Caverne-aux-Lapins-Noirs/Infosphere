#!/usr/bin/env php
<?php
// Detached mailing process, started by Albedo. CLI only.
if (PHP_SAPI !== 'cli') exit(1);
chdir(dirname(__DIR__));
$Language = 'fr';
require_once ('language.php');
require_once ('tools/index.php');
require_once ('tools/bulk_mail_queue.php');

$dir = bulk_mail_queue_dir();
if (!is_dir($dir)) exit(0);
$lock = @fopen($dir.'/.daemon.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);
// Bound the lifetime: Albedo will restart if work remains after an hour.
$deadline = time() + 3300;
while (time() < $deadline)
{
    if (!bulk_mail_has_pending()) break;
    $remaining = (int)@file_get_contents($dir.'/.next_at') - time();
    if ($remaining > 0)
    {
        sleep(min($remaining, max(1, $deadline - time())));
        continue;
    }
    if (!bulk_mail_process_jobs(1)) sleep(2);
}
flock($lock, LOCK_UN);
fclose($lock);
