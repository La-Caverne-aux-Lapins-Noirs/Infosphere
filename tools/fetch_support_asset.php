<?php

function fetch_support_asset($id = -1, $id_support = -1, $by_name = false)
{
    $filter = [];
    if ($id_support != -1)
	$filter["id_support"] = $id_support;
    
    $forge = forge_language_fields(["name", "content"], true, true);
    if (($assets = fetch_data(
	"support_asset", $id,
	["name", "content"],
	"codename", $by_name, true, false,
	$filter,
	["chapter ASC"]
    ))->is_error())
        return ($assets);
    $assets = $assets->value;
    foreach ($assets as &$asset)
    {
	$ext = strtolower(pathinfo($asset["content"], PATHINFO_EXTENSION));
	$asset["selected"] = true;
	$asset["content_exists"] = trim((string)$asset["content"]) != "";
	if ($asset["content_exists"] && $ext != "" && !file_exists($asset["content"]))
	    $asset["content_exists"] = false;
	if (in_array($ext, ["png", "jpg", "gif", "webp"]))
	    $asset["type"] = "img";
	else if (in_array($ext, ["htm", "pdf"]))
	    $asset["type"] = "frame";
	else if (in_array($ext, ["mp4", "m4v", "mov", "webm", "ogv"]))
	{
	    $asset["type"] = "video";
	    if (function_exists("support_video_encoding_status") &&
		($status = support_video_encoding_status($asset["content"])) !== NULL)
	    {
		$asset["video_status"] = $status["status"];
		$asset["video_status_message"] = $status["message"] ?? "";
		if (!file_exists($asset["content"]))
		{
		    $message = "<p>Encodage vidéo en cours.</p>";
		    if ($asset["video_status"] == "error")
			$message = "<p>Erreur pendant l'encodage vidéo.</p>";
		    if ($asset["video_status_message"] != "")
			$message .= "<p>".htmlentities($asset["video_status_message"])."</p>";
		    $asset["type"] = "data";
		    $asset["content"] = base64_encode($message);
		}
	    }
	    $manifest = support_video_hls_manifest($asset["content"]);
	    if ($asset["type"] == "video" && file_exists($manifest))
		$asset["hls_content"] = $manifest;
	}
	else if (in_array($ext, ["mp3"]))
	    $asset["type"] = "audio";
	else if (file_exists($asset["content"]))
	    $asset["type"] = "file";
	else
	    $asset["type"] = "data";
    }
    if (function_exists("support_progress_apply_to_assets"))
	support_progress_apply_to_assets($assets);
    if ($id != -1)
	return (new ValueResponse($assets[array_key_first($assets)]));
    return (new ValueResponse($assets));
}

