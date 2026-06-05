<?php

function support_video_job_root()
{
    return ("dres/support/.video_jobs");
}

function support_video_job_directory($kind = NULL)
{
    $root = support_video_job_root();
    if (!is_dir($root))
        @mkdir($root, 0750, true);
    foreach (["pending", "running", "done", "failed", "sources", "logs"] as $dir)
        if (!is_dir($root."/".$dir))
            @mkdir($root."/".$dir, 0750, true);
    @chmod($root, 0750);
    if ($kind === NULL)
        return ($root);
    return ($root."/".$kind);
}

function support_video_job_random_id()
{
    if (function_exists("random_bytes"))
        return (date("YmdHis")."_".bin2hex(random_bytes(8)));
    return (date("YmdHis")."_".str_replace(".", "", uniqid("", true)));
}

function support_video_encoding_marker($target)
{
    return ($target.".encoding.json");
}

function support_video_encoding_status($target)
{
    $marker = support_video_encoding_marker($target);
    if (!file_exists($marker))
        return (NULL);
    $data = json_decode(@file_get_contents($marker), true);
    if (!is_array($data))
        return (["status" => "pending", "target" => $target]);
    return ($data);
}

function support_video_write_json($path, $data)
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false)
        return (false);
    if (@file_put_contents($path, $json."\n", LOCK_EX) === false)
        return (false);
    @chmod($path, 0640);
    return (true);
}

function support_video_update_marker($target, $status, $extra = [])
{
    $marker = support_video_encoding_marker($target);
    $data = array_merge([
        "status" => $status,
        "target" => $target,
        "updated_at" => date("c")
    ], $extra);
    return (support_video_write_json($marker, $data));
}

function support_video_enqueue($source, $target, $original_name, $consume_source = false)
{
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if ($extension == "")
        $extension = "video";
    $job_id = support_video_job_random_id();
    $source_dir = support_video_job_directory("sources");
    $pending_dir = support_video_job_directory("pending");
    $queue_source = $source_dir."/".$job_id.".".$extension;

    if (!support_video_move_or_copy($source, $queue_source, $consume_source))
        return (new ErrorResponse("CannotWriteFile", $queue_source));
    @chmod($queue_source, 0640);

    support_video_remove_tree(support_video_hls_directory($target));

    if ($extension == "mp4" || $extension == "m4v")
    {
        if (!support_video_move_or_copy($queue_source, $target, false))
            return (new ErrorResponse("CannotWriteFile", $target));
        @chmod($target, 0640);
    }

    $job = [
        "id" => $job_id,
        "status" => "pending",
        "source" => $queue_source,
        "target" => $target,
        "original_name" => $original_name,
        "created_at" => date("c"),
        "pid" => getmypid()
    ];
    if (!support_video_write_json($pending_dir."/".$job_id.".json", $job))
        return (new ErrorResponse("CannotWriteFile", $pending_dir."/".$job_id.".json"));
    if (!support_video_update_marker($target, "pending", [
        "id" => $job_id,
        "source_name" => $original_name,
        "created_at" => $job["created_at"]
    ]))
        return (new ErrorResponse("CannotWriteFile", support_video_encoding_marker($target)));
    return (new ValueResponse($target));
}

function support_video_job_read($path)
{
    $data = json_decode(@file_get_contents($path), true);
    if (!is_array($data))
        return (NULL);
    return ($data);
}

function support_video_job_finish($running_file, $job, $status, $message = "")
{
    $job["status"] = $status;
    $job["message"] = $message;
    $job["finished_at"] = date("c");
    $dir = ($status == "done") ? "done" : "failed";
    $target_file = support_video_job_directory($dir)."/".$job["id"].".json";
    support_video_write_json($target_file, $job);
    @unlink($running_file);
}

function support_video_job_fail($running_file, $job, $message)
{
    support_video_update_marker($job["target"], "error", [
        "id" => $job["id"],
        "message" => $message,
        "source_name" => $job["original_name"] ?? ""
    ]);
    support_video_job_finish($running_file, $job, "failed", $message);
}

function support_video_process_job($pending_file, $verbose = true)
{
    $job = support_video_job_read($pending_file);
    if ($job === NULL || !isset($job["id"]) || !isset($job["source"]) || !isset($job["target"]))
    {
        @unlink($pending_file);
        return (["status" => "invalid", "path" => $pending_file]);
    }

    $running_file = support_video_job_directory("running")."/".$job["id"].".json";
    if (!@rename($pending_file, $running_file))
        return (["status" => "locked", "path" => $pending_file]);

    $job["status"] = "running";
    $job["started_at"] = date("c");
    $job["worker_pid"] = getmypid();
    support_video_write_json($running_file, $job);
    support_video_update_marker($job["target"], "running", [
        "id" => $job["id"],
        "source_name" => $job["original_name"] ?? ""
    ]);

    if (!file_exists($job["source"]))
    {
        support_video_job_fail($running_file, $job, "Source file is missing");
        return (["status" => "failed", "path" => $job["target"], "message" => "Source file is missing"]);
    }
    if (!function_exists("exec"))
    {
        support_video_job_fail($running_file, $job, "PHP exec is disabled");
        return (["status" => "failed", "path" => $job["target"], "message" => "PHP exec is disabled"]);
    }

    $token = function_exists("random_bytes") ? bin2hex(random_bytes(6)) : uniqid("", true);
    $prefix = $job["target"].".encoding.".getmypid().".".$token;
    $temporary = $prefix.".encoded.mp4";
    $log = support_video_job_directory("logs")."/".$job["id"].".log";

    @unlink($temporary);
    @unlink($log);
    if (!support_video_encode_mp4($job["source"], $temporary, $log))
    {
        $details = @file_get_contents($log);
        if ($details !== false && $details != "")
            error_log("Infosphere support video queued transcode failed for ".$job["target"].":\n".$details);
        @unlink($temporary);
        support_video_job_fail($running_file, $job, "ffmpeg transcode failed");
        return (["status" => "failed", "path" => $job["target"], "message" => "ffmpeg transcode failed"]);
    }

    $hls = support_video_create_hls($temporary, $job["target"], $prefix);
    if ($hls->is_error() || $hls->value !== true)
    {
        @unlink($temporary);
        support_video_job_fail($running_file, $job, "ffmpeg HLS packaging failed");
        return (["status" => "failed", "path" => $job["target"], "message" => "ffmpeg HLS packaging failed"]);
    }

    @unlink($job["target"]);
    if (@rename($temporary, $job["target"]) == false)
    {
        @unlink($temporary);
        support_video_job_fail($running_file, $job, "Cannot move encoded MP4 to target");
        return (["status" => "failed", "path" => $job["target"], "message" => "Cannot move encoded MP4 to target"]);
    }

    @chmod($job["target"], 0640);
    @unlink($job["source"]);
    @unlink(support_video_encoding_marker($job["target"]));
    support_video_job_finish($running_file, $job, "done");
    return (["status" => "done", "path" => $job["target"]]);
}

function support_video_pending_jobs()
{
    $pending_dir = support_video_job_directory("pending");
    $jobs = glob($pending_dir."/*.json");
    if ($jobs === false)
        return ([]);
    usort($jobs, function ($a, $b) {
        return (filemtime($a) <=> filemtime($b));
    });
    return ($jobs);
}

function support_video_process_jobs($limit = 1, $verbose = true)
{
    $root = support_video_job_directory();
    $lock = fopen($root."/worker.lock", "c");
    if ($lock === false)
        return (["processed" => 0, "errors" => 1, "locked" => false]);
    if (!flock($lock, LOCK_EX | LOCK_NB))
        return (["processed" => 0, "errors" => 0, "locked" => true]);

    $processed = 0;
    $errors = 0;
    foreach (support_video_pending_jobs() as $job_file)
    {
        if ($limit > 0 && $processed >= $limit)
            break ;
        $result = support_video_process_job($job_file, $verbose);
        if ($verbose)
        {
            echo str_pad($result["status"], 10).($result["path"] ?? $job_file);
            if (isset($result["message"]))
                echo " — ".$result["message"];
            echo "\n";
        }
        if ($result["status"] == "failed")
            $errors += 1;
        if ($result["status"] != "locked" && $result["status"] != "invalid")
            $processed += 1;
    }
    flock($lock, LOCK_UN);
    fclose($lock);
    return (["processed" => $processed, "errors" => $errors, "locked" => false]);
}
