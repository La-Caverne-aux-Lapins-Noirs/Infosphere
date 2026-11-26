<?php

// Build one HLS video rendition and one rendition per audio track. Subtitles
// are external WebVTT tracks, usable with native HLS as well as hls.js.
function support_video_probe($path)
{
    $binary = "ffprobe";
    foreach (["/usr/bin/ffprobe", "/usr/local/bin/ffprobe"] as $candidate)
        if (is_executable($candidate))
        {
            $binary = $candidate;
            break ;
        }
    $argv = [$binary, "-v", "error", "-show_streams", "-show_format", "-of", "json", $path];
    $output = [];
    $status = 1;
    exec(support_video_shell_command($argv)." 2>/dev/null", $output, $status);
    $probe = json_decode(implode("\n", $output), true);
    if ($status != 0 || !isset($probe["streams"]) || !is_array($probe["streams"]))
    {
        error_log("Infosphere: ffprobe could not inspect support video ".$path);
        return (false);
    }
    return ($probe);
}

function support_video_streams($probe, $type)
{
    return (array_values(array_filter($probe["streams"], function($stream) use ($type) {
        return (($stream["codec_type"] ?? "") == $type);
    })));
}

function support_video_track_language($stream)
{
    $language = strtolower(str_replace("_", "-", $stream["tags"]["language"] ?? "und"));
    $aliases = ["fra" => "fr", "fre" => "fr", "eng" => "en", "deu" => "de", "ger" => "de",
        "spa" => "es", "ita" => "it", "por" => "pt", "nld" => "nl", "dut" => "nl",
        "ara" => "ar", "zho" => "zh", "chi" => "zh", "jpn" => "ja", "rus" => "ru",
        "kor" => "ko", "pol" => "pl", "ukr" => "uk", "hin" => "hi"];
    $language = $aliases[$language] ?? $language;
    return (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $language) ? $language : "und");
}

function support_video_track_label($stream, $number)
{
    $language = support_video_track_language($stream);
    $names = ["fr" => "Français", "en" => "Anglais", "de" => "Allemand", "es" => "Espagnol",
        "it" => "Italien", "pt" => "Portugais", "nl" => "Néerlandais", "ar" => "Arabe",
        "zh" => "Chinois", "ja" => "Japonais", "ru" => "Russe", "ko" => "Coréen"];
    $name = $names[$language] ?? ($language == "und" ? "Piste ".($number + 1) : strtoupper($language));
    $title = trim($stream["tags"]["title"] ?? "");
    if ($title != "" && strcasecmp($title, $name) != 0)
        $name .= " — ".$title;
    if (!empty($stream["disposition"]["forced"]))
        $name .= " (forcés)";
    return ($name);
}

function support_video_hls_quote($text)
{
    // HLS quoted strings cannot contain a quote, CR or LF.
    return ('"'.str_replace(["\"", "\r", "\n", "\\"], ["'", " ", " ", "/"], $text).'"');
}

function support_video_hls_output($directory)
{
    return (["-c", "copy", "-sn", "-dn", "-f", "hls", "-hls_time", "2",
        "-hls_list_size", "0", "-hls_playlist_type", "vod", "-hls_segment_type", "fmp4",
        "-hls_flags", "independent_segments", "-hls_fmp4_init_filename", "init.mp4",
        "-hls_segment_filename", $directory."/segment_%05d.m4s", $directory."/index.m3u8"]);
}

function support_video_original_source($target)
{
    $directory = support_video_hls_directory($target);
    $metadata = json_decode((string)@file_get_contents($directory."/tracks.json"), true);
    $name = $metadata["original"] ?? "";
    if (preg_match('/^original\.(mp4|m4v|mov|webm|ogv|avi|mkv)$/', $name)
        && is_file($directory."/".$name))
        return ($directory."/".$name);
    return ($target);
}

function support_video_extract_subtitles($original, $probe, $directory, $log)
{
    $result = ["subtitles" => [], "warnings" => []];
    $text_codecs = ["subrip", "srt", "ass", "ssa", "webvtt", "mov_text", "text", "microdvd", "sami"];
    foreach (support_video_streams($probe, "subtitle") as $number => $stream)
    {
        $label = support_video_track_label($stream, $number);
        if (!in_array($stream["codec_name"] ?? "", $text_codecs))
        {
            $result["warnings"][] = "Sous-titres non affichables : ".$label." (".($stream["codec_name"] ?? "inconnu")."). L'original est conservé.";
            continue ;
        }
        $name = "subtitle_".$number.".vtt";
        $argv = [support_video_ffmpeg_binary(), "-hide_banner", "-nostdin", "-y", "-i", $original,
            "-map", "0:".$stream["index"], "-c:s", "webvtt", "-f", "webvtt", $directory."/".$name];
        $output = [];
        $status = 1;
        exec(support_video_shell_command($argv)." 2>> ".escapeshellarg($log), $output, $status);
        if ($status != 0 || !is_file($directory."/".$name))
        {
            @unlink($directory."/".$name);
            $result["warnings"][] = "Impossible de préparer les sous-titres : ".$label.". L'original est conservé.";
            continue ;
        }
        $result["subtitles"][] = ["id" => $number, "src" => $name, "label" => $label,
            "language" => support_video_track_language($stream), "default" => !empty($stream["disposition"]["forced"])];
    }
    return ($result);
}

function support_video_tracks_permissions($directory)
{
    foreach (scandir($directory) as $name)
    {
        if ($name == "." || $name == "..")
            continue ;
        $path = $directory."/".$name;
        if (is_dir($path))
            support_video_tracks_permissions($path);
        else
            @chmod($path, 0640);
    }
    @chmod($directory, 0750);
}

function support_video_package_tracks($source, $target, $prefix, $original)
{
    $directory = $prefix.".hls";
    $destination = support_video_hls_directory($target);
    $log = $prefix.".hls.log";
    $probe = support_video_probe($source);
    $original_probe = $original == $source ? $probe : support_video_probe($original);
    if ($probe === false || $original_probe === false || !count(support_video_streams($probe, "video")))
        return (new ValueResponse(false));
    support_video_remove_tree($directory);
    if (!@mkdir($directory."/video", 0750, true))
        return (new ErrorResponse("CannotWriteFile", $directory));
    $argv = [support_video_ffmpeg_binary(), "-hide_banner", "-nostdin", "-y", "-i", $source,
        "-map", "0:v:0", "-an"];
    $argv = array_merge($argv, support_video_hls_output($directory."/video"));
    $audio = support_video_streams($probe, "audio");
    $original_audio = support_video_streams($original_probe, "audio");
    $default_audio = 0;
    foreach ($original_audio as $number => $stream)
        if (!empty($stream["disposition"]["default"]))
        {
            $default_audio = $number;
            break ;
        }
    $metadata = ["version" => 2, "audio" => [], "subtitles" => [], "warnings" => []];
    $master = "#EXTM3U\n#EXT-X-VERSION:7\n#EXT-X-INDEPENDENT-SEGMENTS\n";
    foreach ($audio as $number => $stream)
    {
        $subdirectory = "audio_".$number;
        if (!@mkdir($directory."/".$subdirectory, 0750))
        {
            support_video_remove_tree($directory);
            return (new ErrorResponse("CannotWriteFile", $directory));
        }
        $argv = array_merge($argv, ["-map", "0:".$stream["index"], "-vn"],
            support_video_hls_output($directory."/".$subdirectory));
        $description = $original_audio[$number] ?? $stream;
        $label = support_video_track_label($description, $number);
        $language = support_video_track_language($description);
        $metadata["audio"][] = ["id" => $number, "label" => $label, "language" => $language,
            "default" => $number == $default_audio];
        $master .= '#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID="audio",NAME='.
            support_video_hls_quote(($number + 1).". ".$label).',LANGUAGE='.support_video_hls_quote($language).
            ',AUTOSELECT=YES,DEFAULT='.($number == $default_audio ? "YES" : "NO").
            ',URI="'.$subdirectory.'/index.m3u8"'."\n";
    }
    $bandwidth = max(256000, (int)($probe["format"]["bit_rate"] ?? 4000000));
    $master .= '#EXT-X-STREAM-INF:BANDWIDTH='.$bandwidth.(count($audio) ? ',AUDIO="audio"' : "")."\nvideo/index.m3u8\n";
    $output = [];
    $status = 1;
    exec(support_video_shell_command($argv)." 2> ".escapeshellarg($log), $output, $status);
    if ($status != 0 || !is_file($directory."/video/index.m3u8"))
    {
        error_log("Infosphere support video HLS packaging failed for ".$target.":\n".(string)@file_get_contents($log));
        support_video_remove_tree($directory);
        @unlink($log);
        return (new ValueResponse(false));
    }
    $subtitles = support_video_extract_subtitles($original, $original_probe, $directory, $log);
    $metadata["subtitles"] = $subtitles["subtitles"];
    $metadata["warnings"] = $subtitles["warnings"];
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $metadata["original"] = "original.".(support_video_upload_extension($extension) ? $extension : "mp4");
    $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!@copy($original, $directory."/".$metadata["original"])
        || @file_put_contents($directory."/tracks.json", $json."\n") === false
        || @file_put_contents($directory."/index.m3u8", $master) === false)
    {
        support_video_remove_tree($directory);
        @unlink($log);
        return (new ErrorResponse("CannotWriteFile", $directory));
    }
    support_video_tracks_permissions($directory);
    // Keep the previous rendition available if publishing the new one fails.
    $backup = $prefix.".old.hls";
    if (is_dir($destination) && !@rename($destination, $backup))
    {
        support_video_remove_tree($directory);
        @unlink($log);
        return (new ErrorResponse("CannotWriteFile", $destination));
    }
    if (!@rename($directory, $destination))
    {
        if (is_dir($backup))
            @rename($backup, $destination);
        support_video_remove_tree($directory);
        @unlink($log);
        return (new ErrorResponse("CannotWriteFile", $destination));
    }
    support_video_remove_tree($backup);
    foreach ($metadata["warnings"] as $warning)
        error_log("Infosphere support video: ".$warning);
    @unlink($log);
    return (new ValueResponse(true));
}
