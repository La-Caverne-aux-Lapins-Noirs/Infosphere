<?php

function school_logo_candidate_names($kind)
{
    if ($kind == "document")
        return ([
            "document_logo.png", "document_logo.jpg", "document_logo.jpeg", "document_logo.pdf",
            "logo_document.png", "logo_document.jpg", "logo_document.jpeg", "logo_document.pdf",
            "print_logo.png", "print_logo.jpg", "print_logo.jpeg", "print_logo.pdf",
            "logo.png", "logo.jpg", "logo.jpeg", "logo.pdf",
            "icon.png", "icon.jpg", "icon.jpeg",
        ]);
    return ([
        "icon.png", "icon.jpg", "icon.jpeg",
        "site_logo.png", "site_logo.jpg", "site_logo.jpeg",
        "logo.png", "logo.jpg", "logo.jpeg",
        "document_logo.png", "document_logo.jpg", "document_logo.jpeg",
    ]);
}

function school_logo_codename($school)
{
    if (is_array($school))
        return ($school["codename"] ?? "");
    return ((string)$school);
}

function school_logo_path($school, $kind = "site", $absolute = false, $with_fallback = true)
{
    global $Configuration;

    $root = dirname(__DIR__);
    $codename = school_logo_codename($school);
    $candidates = [];
    if ($codename != "")
    {
        foreach (school_logo_candidate_names($kind) as $name)
            $candidates[] = $Configuration->SchoolsDir($codename).$name;
    }
    if ($with_fallback)
    {
        foreach (["res/logo.png", "res/logo.jpg", "res/no_avatar_lab.png"] as $name)
            $candidates[] = $name;
    }

    foreach ($candidates as $candidate)
    {
        $candidate_absolute = $candidate;
        if ($candidate_absolute != "" && $candidate_absolute[0] != "/")
            $candidate_absolute = $root."/".$candidate_absolute;
        if (file_exists($candidate_absolute) && !is_dir($candidate_absolute))
            return ($absolute ? $candidate_absolute : $candidate);
    }
    return ("");
}

function school_site_logo_path($school, $absolute = false, $with_fallback = true)
{
    return (school_logo_path($school, "site", $absolute, $with_fallback));
}

function school_document_logo_path($school, $absolute = false, $with_fallback = true)
{
    return (school_logo_path($school, "document", $absolute, $with_fallback));
}

function refresh_school_logo_path($school)
{
    return (school_document_logo_path($school, true));
}

function school_logo_payload_empty($payload)
{
    if ($payload === NULL || $payload === "")
        return (true);
    if (is_array($payload))
    {
        if (!count($payload))
            return (true);
        if (isset($payload[0]["content"]) && $payload[0]["content"] != "")
            return (false);
        if (isset($payload["content"]) && $payload["content"] != "")
            return (false);
        return (true);
    }
    return (false);
}

function school_write_logo_binary($raw, $target)
{
    if ($raw === false || $raw === NULL || $raw === "")
        return (new ErrorResponse("BadFileFormat"));
    if (($img = @imagecreatefromstring($raw)) == false)
        return (new ErrorResponse("BadFileFormat"));
    $size = @getimagesizefromstring($raw);
    if (!is_array($size) || $size[0] < 100 || $size[1] < 100)
    {
        imagedestroy($img);
        return (new ErrorResponse("InvalidPictureSize"));
    }
    if (($ret = new_directory($target))->is_error())
    {
        imagedestroy($img);
        return ($ret);
    }
    imagesavealpha($img, true);
    if (imagepng($img, $target) == false)
    {
        imagedestroy($img);
        return (new ErrorResponse("CannotWritePngFile"));
    }
    imagedestroy($img);
    return (new Response);
}

function school_upload_logo_payload($payload, $target)
{
    if (school_logo_payload_empty($payload))
        return (new ValueResponse(false));

    if (is_string($payload) && @file_exists($payload))
    {
        if (($ret = new_directory($target))->is_error())
            return ($ret);
        if (($ret = upload_png($payload, $target, [100, 100], MINIMUM_PICTURE_SIZE))->is_error())
            return ($ret);
        return (new ValueResponse(true));
    }

    if (is_array($payload))
    {
        if (isset($payload[0]["content"]))
            $payload = $payload[0]["content"];
        else if (isset($payload["content"]))
            $payload = $payload["content"];
        else
            return (new ValueResponse(false));
    }

    $raw = base64_decode((string)$payload, true);
    if ($raw === false)
        return (new ErrorResponse("BadFileFormat"));
    if (($ret = school_write_logo_binary($raw, $target))->is_error())
        return ($ret);
    return (new ValueResponse(true));
}

function school_update_logos($codename, array $data)
{
    global $Configuration;

    $dir = $Configuration->SchoolsDir($codename);
    $changed = false;
    $logos = [
        "icon" => "icon.png",
        "site_logo" => "icon.png",
        "document_logo" => "document_logo.png",
        "document_icon" => "document_logo.png",
    ];
    foreach ($logos as $field => $filename)
    {
        if (!array_key_exists($field, $data) || school_logo_payload_empty($data[$field]))
            continue ;
        if (($ret = school_upload_logo_payload($data[$field], $dir.$filename))->is_error())
            return ($ret);
        $changed = $changed || ($ret instanceof ValueResponse && $ret->value);
    }
    return (new ValueResponse($changed));
}

function refresh_school($school)
{
    global $Configuration;

    if (!is_array($school))
    {
	$ret = fetch_school($school);
	if (is_object($ret) && $ret->is_error())
	    return ($ret);
	$school = $ret;
    }
    if (!isset($school["codename"]))
	return (new ErrorResponse("MissingCodeName"));

    $name = $school["fr_name"] ?? ($school["name"] ?? $school["codename"]);
    $legal_name = $school["legal_name"] ?? $name;

    $main_info = $school["main_info"] ?? "";
    $school_info = $school["school_info"] ?? "";
    $formation_info = $school["formation_info"] ?? "";
    $alternation_info = $school["alternation_info"] ?? "";

    $dabsic = [
	"company" => [
	    "name" => $name,
	    "legal_name" => $legal_name,
	    "address" => $school["address"] ?? "",
	    "phone" => $school["phone"] ?? "",
	    "mail" => $school["mail"] ?? "",

	    "main_info" => $main_info,
	    "school_info" => $school_info,
	    "formation_info" => $formation_info,
	    "alternation_info" => $alternation_info,

	    // Compatibilité avec les anciens noms courts.
	    "main" => $main_info,
	    "school" => $school_info,
	    "formation" => $formation_info,
	    "alternation" => $alternation_info,

	    "logo" => school_document_logo_path($school, true),
	    "document_logo" => school_document_logo_path($school, true),
	    "site_logo" => school_site_logo_path($school, true),
	    "logo_width" => "3cm",
	    "logo_height" => "2cm",
	    "document_logo_width" => "3cm",
	    "document_logo_height" => "2cm",
	    "site_logo_width" => "3cm",
	    "site_logo_height" => "2cm",
	]
    ];

    return (generate_dabsic(
	$dabsic,
	$Configuration->SchoolsDir($school["codename"])."identity.dab"
    ));
}

