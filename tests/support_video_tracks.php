<?php
// Standalone integration test: generates tiny MKV/MP4 movies with real FFmpeg,
// then runs the same queue, conversion and HLS packaging as uploaded assets.
if (PHP_SAPI !== "cli")
    exit(1);
require_once (__DIR__."/../tools/response.php");
require_once (__DIR__."/../tools/support_video.php");
require_once (__DIR__."/../tools/support_video_jobs.php");
require_once (__DIR__."/../tools/install_uploads.php");
$Dictionnary = ["CannotWriteFile" => "Cannot write file"];

function video_check($condition, $message)
{
    if (!$condition)
        throw new RuntimeException($message);
}
function video_command($arguments)
{
    $output = [];
    $status = 1;
    exec(support_video_shell_command($arguments)." 2>&1", $output, $status);
    video_check($status == 0, implode("\n", $output));
}
function video_metadata($target)
{
    return (json_decode(file_get_contents(support_video_hls_directory($target)."/tracks.json"), true));
}
$directory = sys_get_temp_dir()."/infosphere_video_".bin2hex(random_bytes(6));
mkdir($directory, 0750, true);
chdir($directory);
try
{
    file_put_contents("fr.srt", "1\n00:00:00,200 --> 00:00:05,500\nBonjour capitaine\n\n");
    file_put_contents("en.srt", "1\n00:00:00,200 --> 00:00:05,500\nHello captain\n\n");
    // Spaces and shell metacharacters in filenames must remain literal.
    $mkv = $directory."/film ' \$ test.mkv";
    video_command(["ffmpeg", "-v", "error", "-nostdin", "-y", "-f", "lavfi", "-i",
        "testsrc2=size=160x90:rate=24:duration=6", "-f", "lavfi", "-i", "sine=frequency=440:duration=6",
        "-f", "lavfi", "-i", "sine=frequency=880:duration=6", "-i", "fr.srt", "-i", "en.srt",
        "-map", "0:v:0", "-map", "1:a:0", "-map", "2:a:0", "-map", "3:s:0", "-map", "4:s:0",
        "-c:v", "libx264", "-preset", "ultrafast", "-c:a", "aac", "-c:s", "srt",
        "-metadata:s:a:0", "language=fra", "-metadata:s:a:0", "title=VF \"originale\"\nseconde ligne",
        "-metadata:s:a:1", "language=eng", "-metadata:s:s:0", "language=fra", "-metadata:s:s:1", "language=eng",
        "-disposition:a:0", "default", "-disposition:a:1", "0", "-disposition:s:0", "forced", "-disposition:s:1", "0", $mkv]);
    $mp4 = $directory."/original.mp4";
    video_command(["ffmpeg", "-v", "error", "-nostdin", "-y", "-i", $mkv,
        "-map", "0", "-c", "copy", "-c:s", "mov_text", $mp4]);
    mkdir("assets");
    foreach ([$mkv, $mp4] as $number => $original)
    {
        $target = "assets/".($number == 0 ? "mkv" : "mp4").".mp4";
        $queued = support_video_enqueue($original, $target, basename($original), false);
        video_check(!$queued->is_error(), "Cannot enqueue fixture");
    }
    $processed = support_video_process_jobs(2, false);
    video_check($processed["processed"] == 2 && $processed["errors"] == 0, "Video queue failed");
    foreach (["assets/mkv.mp4", "assets/mp4.mp4"] as $target)
    {
        $metadata = video_metadata($target);
        video_check(count($metadata["audio"]) == 2, "Both audio tracks must survive");
        video_check(array_column($metadata["audio"], "language") == ["fr", "en"], "Audio language metadata lost");
        video_check(count($metadata["subtitles"]) == 2, "Both subtitle tracks must survive");
        video_check(count($metadata["warnings"]) == 0, "Unexpected subtitle warning");
        video_check(file_exists(support_video_original_source($target)), "Original is missing");
        $master = file_get_contents(support_video_hls_manifest($target));
        video_check(substr_count($master, "#EXT-X-MEDIA:TYPE=AUDIO") == 2, "HLS must advertise both audio renditions");
        video_check(substr_count($master, "DEFAULT=YES") == 1, "Exactly one default audio track required");
        video_check(strpos($master, 'VF "originale"') === false, "HLS labels must escape quotes");
        $video = support_video_probe(support_video_hls_directory($target)."/video/index.m3u8");
        video_check(count(support_video_streams($video, "video")) == 1 && count(support_video_streams($video, "audio")) == 0,
            "Video rendition must be independent from alternate audio");
        foreach ([0, 1] as $audio)
        {
            $probe = support_video_probe(support_video_hls_directory($target)."/audio_".$audio."/index.m3u8");
            video_check($probe !== false && count(support_video_streams($probe, "audio")) == 1, "Unreadable HLS audio rendition");
        }
        $french = file_get_contents(support_video_hls_directory($target)."/subtitle_0.vtt");
        video_check(strpos($french, "WEBVTT") === 0 && strpos($french, "Bonjour capitaine") !== false, "WebVTT extraction failed");
    }
    $target = "assets/mkv.mp4";
    $original_hash = hash_file("sha256", support_video_original_source($target));
    video_check($original_hash == hash_file("sha256", $mkv), "Preserved original differs from upload");
    $rebuilt = support_video_create_hls_from_existing($target, true);
    video_check(!$rebuilt->is_error() && $rebuilt->value["status"] == "converted", "HLS rebuild failed");
    video_check(count(video_metadata($target)["subtitles"]) == 2, "Rebuild must use original, preserving subtitles");
    video_check(hash_file("sha256", support_video_original_source($target)) == $original_hash, "Rebuild changed original");
    $old_manifest = file_get_contents(support_video_hls_manifest($target));
    $bad = support_video_create_hls("missing.mp4", $target, $directory."/bad");
    video_check(!$bad->is_error() && $bad->value === false, "Invalid source should fail");
    video_check(file_get_contents(support_video_hls_manifest($target)) === $old_manifest, "Failed packaging damaged existing HLS");
    file_put_contents("invalid.mkv", "Not a media file");
    support_video_enqueue("invalid.mkv", $target, "invalid.mkv", false);
    video_check(file_get_contents(support_video_hls_manifest($target)) === $old_manifest, "Enqueue removed published HLS too early");
    $failed_replacement = support_video_process_jobs(1, false);
    video_check($failed_replacement["errors"] == 1, "Invalid replacement should fail conversion");
    video_check(file_get_contents(support_video_hls_manifest($target)) === $old_manifest, "Failed queued replacement damaged old HLS");
    video_command(["ffmpeg", "-v", "error", "-nostdin", "-y", "-i", $mp4, "-map", "0:v:0", "-c", "copy", "assets/silent.mp4"]);
    $silent = support_video_create_hls("assets/silent.mp4", "assets/silent.mp4", $directory."/silent");
    video_check(!$silent->is_error() && $silent->value === true, "Video without audio failed");
    video_check(count(video_metadata("assets/silent.mp4")["audio"]) == 0, "Unexpected audio on silent video");
    $unsupported = support_video_extract_subtitles($mkv, ["streams" => [["codec_type" => "subtitle",
        "codec_name" => "hdmv_pgs_subtitle", "index" => 9]]], $directory, $directory."/unsupported.log");
    video_check(count($unsupported["subtitles"]) == 0 && count($unsupported["warnings"]) == 1,
        "Image subtitles must produce an explicit warning");
    $profile = install_upload_profile();
    $ini = parse_ini_string($profile["php"]);
    video_check($ini["upload_max_filesize"] == "4096M" && $ini["post_max_size"] == "4128M", "Upload profile lacks multipart margin");
    video_check(strpos($profile["apache"], "<Directory ") !== false && strpos($profile["apache"], "LimitRequestBody 0") !== false,
        "Apache upload override must be scoped to this application");
    file_put_contents("old.ini", "changed");
    file_put_contents("new.ini", "new");
    symlink("new.ini", "enabled.conf");
    install_upload_restore(["old.ini" => "previous", "new.ini" => NULL], "enabled.conf", false);
    video_check(file_get_contents("old.ini") === "previous" && !file_exists("new.ini") && !is_link("enabled.conf"),
        "Configuration rollback must restore files and remove a newly enabled configuration");
    echo "OK: MKV/MP4, alternate audio, WebVTT, original retention, queue, rebuild, failed packaging, silent video, upload profile.\n";
    echo "Fixtures: ".$directory."\n";
}
finally
{
    if (getenv("INFOSPHERE_KEEP_VIDEO_FIXTURES") !== "1")
        support_video_remove_tree($directory);
}
