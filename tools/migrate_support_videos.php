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

function support_video_migration_usage()
{
    echo "Usage: php tools/migrate_support_videos.php [--dry-run] [--force] [--path=dres/support] [--limit=N]\n";
    echo "\n";
    echo "Converts existing support videos into static HLS fMP4 VOD folders next to the original file.\n";
    echo "The original MP4/direct file is left untouched.\n";
    echo "\n";
    echo "Options:\n";
    echo "  --dry-run       List videos that would be converted without running ffmpeg.\n";
    echo "  --force         Rebuild HLS even when asset.hls/index.m3u8 already exists.\n";
    echo "  --path=PATH     Scan another directory. Default: dres/support.\n";
    echo "  --limit=N       Convert at most N files in this run. Useful for production batches.\n";
    echo "  --help          Show this help.\n";
}

function support_video_migration_bool_arg($arg, $name)
{
    return ($arg == $name);
}

function support_video_migration_value_arg($arg, $name)
{
    $prefix = $name."=";
    if (substr($arg, 0, strlen($prefix)) != $prefix)
        return (NULL);
    return (substr($arg, strlen($prefix)));
}

function support_video_migration_options($argv)
{
    $options = [
        "dry_run" => false,
        "force" => false,
        "path" => "dres/support",
        "limit" => 0
    ];

    for ($i = 1; isset($argv[$i]); ++$i)
    {
        $arg = $argv[$i];
        if ($arg == "--help" || $arg == "-h")
        {
            support_video_migration_usage();
            exit(0);
        }
        else if (support_video_migration_bool_arg($arg, "--dry-run"))
            $options["dry_run"] = true;
        else if (support_video_migration_bool_arg($arg, "--force"))
            $options["force"] = true;
        else if (($value = support_video_migration_value_arg($arg, "--path")) !== NULL)
            $options["path"] = $value;
        else if (($value = support_video_migration_value_arg($arg, "--limit")) !== NULL)
            $options["limit"] = max(0, (int)$value);
        else
        {
            fwrite(STDERR, "Unknown option: $arg\n\n");
            support_video_migration_usage();
            exit(2);
        }
    }
    return ($options);
}

function support_video_migration_scan($dir, &$files)
{
    $entries = @scandir($dir);
    if ($entries === false)
        return ;
    foreach ($entries as $entry)
    {
        if ($entry == "." || $entry == "..")
            continue ;
        $path = $dir."/".$entry;
        if (is_dir($path))
        {
            if (substr($entry, -4) == ".hls" || $entry == ".video_jobs")
                continue ;
            support_video_migration_scan($path, $files);
        }
        else if (is_file($path) && support_video_is_video_path($path))
        {
            if (preg_match('/\.hls_migration\.[^.]+\./', $path))
                continue ;
            if (preg_match('/\.encoding\.[^.]+\./', $path))
                continue ;
            $files[] = $path;
        }
    }
}

function support_video_migration_print_result($status, $path, $message = "")
{
    echo str_pad($status, 13).$path;
    if ($message != "")
        echo " — ".$message;
    echo "\n";
}

$options = support_video_migration_options($argv);
$root = rtrim($options["path"], "/");
if (!is_dir($root))
{
    fwrite(STDERR, "Directory not found: $root\n");
    exit(1);
}

$files = [];
support_video_migration_scan($root, $files);
sort($files);

$stats = [
    "seen" => 0,
    "converted" => 0,
    "already" => 0,
    "would" => 0,
    "errors" => 0,
    "skipped" => 0
];

foreach ($files as $path)
{
    $stats["seen"] += 1;
    $manifest = support_video_hls_manifest($path);
    if (!$options["force"] && file_exists($manifest))
    {
        $stats["already"] += 1;
        support_video_migration_print_result("already", $path);
        continue ;
    }

    if ($options["limit"] > 0 && $stats["converted"] >= $options["limit"])
    {
        $stats["skipped"] += 1;
        continue ;
    }

    if ($options["dry_run"])
    {
        $stats["would"] += 1;
        support_video_migration_print_result("would", $path);
        continue ;
    }

    support_video_migration_print_result("converting", $path);
    $ret = support_video_create_hls_from_existing($path, $options["force"]);
    if ($ret->is_error())
    {
        $stats["errors"] += 1;
        support_video_migration_print_result("error", $path, (string)$ret);
        continue ;
    }
    if (is_array($ret->value))
    {
        $status = $ret->value["status"];
        if ($status == "converted")
            $stats["converted"] += 1;
        else if ($status == "already-done")
            $stats["already"] += 1;
        else if ($status == "not-video")
            $stats["skipped"] += 1;
        else
            $stats["errors"] += 1;
        support_video_migration_print_result($status, $path, $ret->value["message"] ?? "");
    }
}

echo "\nSummary:\n";
echo "  seen:      ".$stats["seen"]."\n";
echo "  converted: ".$stats["converted"]."\n";
echo "  already:   ".$stats["already"]."\n";
echo "  would:     ".$stats["would"]."\n";
echo "  skipped:   ".$stats["skipped"]."\n";
echo "  errors:    ".$stats["errors"]."\n";

exit($stats["errors"] == 0 ? 0 : 1);
// @codeCoverageIgnoreEnd
