#!/usr/bin/env php
<?php
if (PHP_SAPI !== "cli")
{
    http_response_code(403);
    exit("Command line only.\n");
}
chdir(dirname(__DIR__));
require_once (__DIR__."/install_uploads.php");
// Keep this maintenance command independent from the database/web installer.
function install_append_warning(&$warnings, $message) { $warnings[] = $message; }
function install_write_file_if_different($path, $content)
{
    return ((is_file($path) && file_get_contents($path) === $content) || @file_put_contents($path, $content) !== false);
}
function install_command_path($command)
{
    return (function_exists("shell_exec") ? trim((string)shell_exec("command -v ".escapeshellarg($command)." 2>/dev/null")) : "");
}
function install_exec($command, &$output, &$status)
{
    $output = [];
    $status = 127;
    if (function_exists("exec"))
        exec($command, $output, $status);
}
$max_mib = 4096;
$version = NULL;
$dry_run = false;
foreach (array_slice($argv, 1) as $argument)
{
    if ($argument == "--help")
        exit("Usage: php tools/configure_upload_limits.php [--max-mib=4096] [--php-version=8.4] [--dry-run]\n");
    if ($argument == "--dry-run")
        $dry_run = true;
    else if (preg_match('/^--max-mib=([0-9]+)$/', $argument, $matches))
        $max_mib = (int)$matches[1];
    else if (preg_match('/^--php-version=([0-9]+\.[0-9]+)$/', $argument, $matches))
        $version = $matches[1];
    else
    {
        fwrite(STDERR, "Unknown option: ".$argument."\n");
        exit(2);
    }
}
try
{
    $profile = install_upload_profile($max_mib);
    if ($dry_run)
        exit("PHP\n".$profile["php"]."\nApache\n".$profile["apache"]);
    $warnings = [];
    $success = install_upload_configuration($warnings, $max_mib, $version);
    foreach ($warnings as $message)
        fwrite(STDERR, $message."\n");
    exit($success ? 0 : 1);
}
catch (InvalidArgumentException $error)
{
    fwrite(STDERR, $error->getMessage()."\n");
    exit(2);
}
