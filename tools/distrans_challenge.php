<?php

function distrans_runtime_dirs()
{
    return ([
        "/run/distrans",
        "/dev/shm/distrans",
        sys_get_temp_dir()."/distrans",
    ]);
}

function distrans_runtime_dir($write = true)
{
    foreach (distrans_runtime_dirs() as $dir)
    {
        if (!is_dir($dir) && $write)
            @mkdir($dir, 0700, true);
        if (is_dir($dir) && $write)
            @chmod($dir, 0700);

        if (is_dir($dir) && ($write ? is_writable($dir) : is_readable($dir)))
            return ($dir);
    }
    return (NULL);
}

function distrans_challenge_file($write = true)
{
    if (($dir = distrans_runtime_dir($write)) === NULL)
        return (NULL);
    return ($dir."/albedo_code");
}

function distrans_write_challenge($challenge)
{
    if (($file = distrans_challenge_file(true)) === NULL)
        return (false);

    if (file_put_contents($file, $challenge, LOCK_EX) === false)
        return (false);
    @chmod($file, 0600);
    return ($file);
}

function distrans_read_challenge()
{
    if (($file = distrans_challenge_file(false)) === NULL)
        return (false);
    if (!is_file($file) || !is_readable($file))
        return (false);
    return (file_get_contents($file));
}

function distrans_clear_challenge($file, $challenge = NULL)
{
    if ($file === NULL || $file === false || !is_file($file))
        return ;

    if ($challenge !== NULL)
    {
        $content = @file_get_contents($file);
        if ($content !== $challenge)
            return ;
    }
    @unlink($file);
}

function distrans_serve_challenge()
{
    if (($challenge = distrans_read_challenge()) === false)
    {
        if (function_exists("add_log"))
            add_log(REPORT, "Distrans challenge requested but no readable challenge was available", 1);
        http_response_code(404);
        return ;
    }
    if (function_exists("add_log"))
        add_log(TRACE, "Distrans challenge served", 1);
    header("Content-Type: text/plain; charset=UTF-8");
    echo $challenge;
}
