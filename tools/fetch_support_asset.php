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
	$ext = pathinfo($asset["content"], PATHINFO_EXTENSION);
	$asset["selected"] = true;
	if (in_array($ext, ["png", "jpg", "gif", "webp"]))
	    $asset["type"] = "img";
	else if (in_array($ext, ["htm", "pdf"]))
	    $asset["type"] = "frame";
	else if (in_array($ext, ["mp4", "webm"]))
	    $asset["type"] = "video";
	else if (in_array($ext, ["mp3"]))
	    $asset["type"] = "audio";
	else if (file_exists($asset["content"]))
	    $asset["type"] = "file";
	else
	    $asset["type"] = "data";
    }
    if ($id != -1)
	return (new ValueResponse($assets[array_key_first($assets)]));
    return (new ValueResponse($assets));
}

