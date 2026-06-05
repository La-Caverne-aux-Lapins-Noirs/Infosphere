#!/usr/bin/env php
<?php
// @codeCoverageIgnoreStart

if (PHP_SAPI !== "cli")
{
    http_response_code(403);
    echo "This maintenance script must be run from the command line.\n";
    exit(1);
}

chdir(dirname(__DIR__));
@define("UNIT_TEST", 0);
@define("INSTALLATION", 0);
$BaseDir = "";
$Language = "fr";

require_once ("language.php");
require_once ("tools/response.php");
require_once ("tools/support_video.php");
require_once ("tools/support_video_jobs.php");

function support_video_worker_usage()
{
    echo "Usage: php tools/process_support_video_jobs.php [--limit=N] [--loop] [--sleep=N] [--max-runtime=N] [--quiet]\n";
    echo "\n";
    echo "Processes queued support video encodings outside the web request.\n";
    echo "\n";
    echo "Options:\n";
    echo "  --limit=N        Process at most N jobs per pass. Default: 1. Use 0 for unlimited.\n";
    echo "  --loop           Keep processing until max runtime is reached.\n";
    echo "  --sleep=N        Seconds to sleep between loop passes. Default: 5.\n";
    echo "  --max-runtime=N  Stop loop after N seconds. Default: 3300.\n";
    echo "  --quiet          Only print errors.\n";
    echo "  --help           Show this help.\n";
}

function support_video_worker_value_arg($arg, $name)
{
    $prefix = $name."=";
    if (substr($arg, 0, strlen($prefix)) != $prefix)
        return (NULL);
    return (substr($arg, strlen($prefix)));
}

$options = [
    "limit" => 1,
    "loop" => false,
    "sleep" => 5,
    "max_runtime" => 3300,
    "quiet" => false
];

for ($i = 1; isset($argv[$i]); ++$i)
{
    $arg = $argv[$i];
    if ($arg == "--help" || $arg == "-h")
    {
        support_video_worker_usage();
        exit(0);
    }
    else if ($arg == "--loop")
        $options["loop"] = true;
    else if ($arg == "--quiet")
        $options["quiet"] = true;
    else if (($value = support_video_worker_value_arg($arg, "--limit")) !== NULL)
        $options["limit"] = max(0, (int)$value);
    else if (($value = support_video_worker_value_arg($arg, "--sleep")) !== NULL)
        $options["sleep"] = max(1, (int)$value);
    else if (($value = support_video_worker_value_arg($arg, "--max-runtime")) !== NULL)
        $options["max_runtime"] = max(1, (int)$value);
    else
    {
        fwrite(STDERR, "Unknown option: $arg\n\n");
        support_video_worker_usage();
        exit(2);
    }
}

$start = time();
$total_processed = 0;
$total_errors = 0;

while (true)
{
    $result = support_video_process_jobs($options["limit"], !$options["quiet"]);
    $total_processed += $result["processed"];
    $total_errors += $result["errors"];

    if ($result["locked"])
    {
        if (!$options["quiet"])
            echo "worker already running\n";
        exit(0);
    }
    if (!$options["loop"])
        break ;
    if (time() - $start >= $options["max_runtime"])
        break ;
    sleep($options["sleep"]);
}

if (!$options["quiet"])
{
    echo "\nSummary:\n";
    echo "  processed: ".$total_processed."\n";
    echo "  errors:    ".$total_errors."\n";
}
exit($total_errors == 0 ? 0 : 1);
// @codeCoverageIgnoreEnd
