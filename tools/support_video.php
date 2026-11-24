<?php

require_once (__DIR__."/support_video_tracks.php");

function support_video_upload_extension($extension)
{
    $extension = strtolower($extension);
    return (in_array($extension, [
        "mp4", "m4v", "mov", "webm", "ogv", "avi", "mkv"
    ]));
}

function support_video_output_extension($extension)
{
    if (support_video_upload_extension($extension))
        return ("mp4");
    return ($extension);
}

function support_video_ffmpeg_binary()
{
    foreach (["/usr/bin/ffmpeg", "/usr/local/bin/ffmpeg"] as $candidate)
        if (is_executable($candidate))
            return ($candidate);
    return ("ffmpeg");
}

function support_video_shell_command($argv)
{
    $cmd = [];
    foreach ($argv as $arg)
        $cmd[] = escapeshellarg($arg);
    return (implode(" ", $cmd));
}

function support_video_is_video_path($path)
{
    return (support_video_upload_extension(pathinfo($path, PATHINFO_EXTENSION)));
}

function support_video_hls_directory($target)
{
    return (preg_replace('/\.[^.]+$/', ".hls", $target));
}

function support_video_hls_manifest($target)
{
    return (support_video_hls_directory($target)."/index.m3u8");
}

function support_video_remove_tree($path)
{
    if (!file_exists($path))
        return (true);
    if (!is_dir($path))
        return (@unlink($path));

    $entries = scandir($path);
    if ($entries === false)
        return (false);
    foreach ($entries as $entry)
    {
        if ($entry == "." || $entry == "..")
            continue ;
        if (!support_video_remove_tree($path."/".$entry))
            return (false);
    }
    return (@rmdir($path));
}

function support_video_move_or_copy($source, $target, $consume_source = false)
{
    @unlink($target);
    if ($consume_source && @rename($source, $target) != false)
        return (true);
    if (@copy($source, $target) != false)
    {
        if ($consume_source)
            @unlink($source);
        return (true);
    }
    return (false);
}

function support_video_fallback($source, $target, $extension, $consume_source = true)
{
    $fallback = $target;

    support_video_remove_tree(support_video_hls_directory($target));
    if (strtolower(pathinfo($target, PATHINFO_EXTENSION)) != strtolower($extension))
        $fallback = preg_replace('/\.[^.]+$/', ".".$extension, $target);
    if (!support_video_move_or_copy($source, $fallback, $consume_source))
        return (new ErrorResponse("CannotWriteFile", $fallback));
    @chmod($fallback, 0640);
    return (new ValueResponse($fallback));
}

function support_video_create_hls($source, $target, $temporary_prefix, $original = NULL)
{
    return (support_video_package_tracks($source, $target, $temporary_prefix, $original ?? $source));
}


function support_video_encode_mp4($source, $temporary, $log)
{
    $argv = [
        support_video_ffmpeg_binary(),
        "-hide_banner",
        "-nostdin",
        "-y",
        "-i", $source,
        "-map", "0:v:0",
        "-map", "0:a?",
        "-sn",
        "-dn",
        "-c:v", "libx264",
        "-preset", "veryfast",
        "-crf", "23",
        "-pix_fmt", "yuv420p",
        "-vf", "scale=trunc(iw/2)*2:trunc(ih/2)*2",
        "-force_key_frames", "expr:gte(t,n_forced*2)",
        "-sc_threshold", "0",
        "-c:a", "aac",
        "-b:a", "128k",
        "-ac", "2",
        "-movflags", "+empty_moov+default_base_moof+frag_keyframe+global_sidx",
        "-frag_duration", "2000000",
        "-f", "mp4",
        $temporary
    ];

    $output = [];
    $status = 1;
    exec(support_video_shell_command($argv)." 2> ".escapeshellarg($log), $output, $status);
    return ($status == 0 && file_exists($temporary) && filesize($temporary) > 0);
}

function support_video_create_hls_from_existing($target, $force = false)
{
    if (!file_exists($target) || !is_file($target))
        return (new ValueResponse(["status" => "missing", "path" => $target]));
    if (!support_video_is_video_path($target))
        return (new ValueResponse(["status" => "not-video", "path" => $target]));
    if (!$force && file_exists(support_video_hls_manifest($target)))
        return (new ValueResponse(["status" => "already-done", "path" => $target]));
    if (!function_exists("exec"))
        return (new ValueResponse(["status" => "error", "path" => $target, "message" => "PHP exec is disabled"]));

    $token = function_exists("random_bytes") ? bin2hex(random_bytes(6)) : uniqid("", true);
    $prefix = $target.".hls_migration.".getmypid().".".$token;
    $temporary = $prefix.".encoded.mp4";
    $log = $prefix.".log";

    @unlink($temporary);
    @unlink($log);
    $original = support_video_original_source($target);
    if (!support_video_encode_mp4($original, $temporary, $log))
    {
        $details = @file_get_contents($log);
        if ($details !== false && $details != "")
            error_log("Infosphere support video migration transcode failed for $target:\n".$details);
        @unlink($temporary);
        @unlink($log);
        return (new ValueResponse(["status" => "error", "path" => $target, "message" => "ffmpeg transcode failed"]));
    }

    $hls = support_video_create_hls($temporary, $target, $prefix, $original);
    @unlink($temporary);
    @unlink($log);
    if ($hls->is_error())
        return ($hls);
    if ($hls->value !== true)
        return (new ValueResponse(["status" => "error", "path" => $target, "message" => "ffmpeg transcode failed"]));
    return (new ValueResponse(["status" => "converted", "path" => $target]));
}

function support_video_store_file($target, $source, $original_name, $consume_source = false)
{
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (!support_video_upload_extension($extension))
    {
        support_video_remove_tree(support_video_hls_directory($target));
        if (function_exists("support_video_encoding_marker"))
            @unlink(support_video_encoding_marker($target));
        if (!support_video_move_or_copy($source, $target, $consume_source))
            return (new ErrorResponse("CannotWriteFile", $target));
        @chmod($target, 0640);
        return (new ValueResponse($target));
    }

    if (function_exists("support_video_enqueue"))
        return (support_video_enqueue($source, $target, $original_name, $consume_source));

    $token = function_exists("random_bytes") ? bin2hex(random_bytes(6)) : uniqid("", true);
    $prefix = $target.".encoding.".getmypid().".".$token;
    $temporary = $prefix.".encoded.mp4";
    $log = $prefix.".log";

    if (!function_exists("exec"))
        return (support_video_fallback($source, $target, $extension, $consume_source));

    if (!support_video_encode_mp4($source, $temporary, $log))
    {
        $details = @file_get_contents($log);
        if ($details !== false && $details != "")
            error_log("Infosphere support video transcode failed for $target:\n".$details);
        @unlink($temporary);
        @unlink($log);
        return (support_video_fallback($source, $target, $extension, $consume_source));
    }

    $hls = support_video_create_hls($temporary, $target, $prefix, $source);
    if ($hls->is_error())
    {
        @unlink($temporary);
        @unlink($log);
        return (support_video_fallback($source, $target, $extension, $consume_source));
    }

    @unlink($target);
    if (@rename($temporary, $target) == false)
    {
        @unlink($temporary);
        @unlink($log);
        return (support_video_fallback($source, $target, $extension, $consume_source));
    }

    if ($consume_source)
        @unlink($source);
    @unlink($log);
    @chmod($target, 0640);
    return (new ValueResponse($target));
}

function support_video_store_optimized($target, $content, $original_name)
{
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $token = function_exists("random_bytes") ? bin2hex(random_bytes(6)) : uniqid("", true);
    $source = $target.".upload.".getmypid().".".$token.".".$extension;

    if (file_put_contents($source, $content) === false)
        return (new ErrorResponse("CannotWriteFile", $source));
    @chmod($source, 0640);
    return (support_video_store_file($target, $source, $original_name, true));
}

function support_video_store_uploaded($target, $upload, $original_name)
{
    if (!isset($upload["tmp_name"]) || !is_uploaded_file($upload["tmp_name"]))
        return (new ErrorResponse("CannotWriteFile", $target));
    return (support_video_store_file($target, $upload["tmp_name"], $original_name, true));
}
