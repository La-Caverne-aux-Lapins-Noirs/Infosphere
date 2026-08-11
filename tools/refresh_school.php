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

function school_favicon_path($school, $absolute = false)
{
    global $Configuration;

    $codename = school_logo_codename($school);
    if ($codename == "")
        return ("");
    $candidate = $Configuration->SchoolsDir($codename)."favicon.png";
    $candidate_absolute = $candidate;
    if ($candidate_absolute != "" && $candidate_absolute[0] != "/")
        $candidate_absolute = dirname(__DIR__)."/".$candidate_absolute;
    if (!file_exists($candidate_absolute) || is_dir($candidate_absolute))
        return ("");
    return ($absolute ? $candidate_absolute : $candidate);
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

function school_write_logo_binary($raw, $target, $minimum_size = 100)
{
    if ($raw === false || $raw === NULL || $raw === "")
        return (new ErrorResponse("BadFileFormat"));
    if (($img = @imagecreatefromstring($raw)) == false)
        return (new ErrorResponse("BadFileFormat"));
    $size = @getimagesizefromstring($raw);
    if (!is_array($size) || $size[0] < $minimum_size || $size[1] < $minimum_size)
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

function school_upload_logo_payload($payload, $target, $minimum_size = 100)
{
    if (school_logo_payload_empty($payload))
        return (new ValueResponse(false));

    if (is_string($payload) && @file_exists($payload))
    {
        if (($ret = new_directory($target))->is_error())
            return ($ret);
        if (($ret = upload_png($payload, $target, [$minimum_size, $minimum_size], MINIMUM_PICTURE_SIZE))->is_error())
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
    if (($ret = school_write_logo_binary($raw, $target, $minimum_size))->is_error())
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
        "favicon" => "favicon.png",
    ];
    foreach ($logos as $field => $filename)
    {
        if (!array_key_exists($field, $data) || school_logo_payload_empty($data[$field]))
            continue ;
        $minimum_size = $field == "favicon" ? 16 : 100;
        if (($ret = school_upload_logo_payload($data[$field], $dir.$filename, $minimum_size))->is_error())
            return ($ret);
        $changed = $changed || ($ret instanceof ValueResponse && $ret->value);
    }
    return (new ValueResponse($changed));
}

function school_document_address($address)
{
    return (trim(preg_replace('/\s*\R\s*/u', ', ', (string)$address)));
}

function school_main_info(array $school)
{
    $legal_name = trim((string)($school["legal_name"] ?? ($school["name"] ?? "")));
    $address = school_document_address($school["organization_address"] ?? "");
    $registry = trim((string)($school["registration_registry"] ?? ""));
    $registration_number = trim((string)($school["registration_number"] ?? ""));
    $parts = [];

    if ($legal_name != "")
        $parts[] = $legal_name.".";
    if ($address != "")
        $parts[] = "Siège social : ".$address.".";
    if ($registration_number != "")
    {
        if ($registry != "")
            $parts[] = "Immatriculée au ".$registry." sous le numéro ".$registration_number.".";
        else
            $parts[] = "Immatriculée sous le numéro ".$registration_number.".";
    }
    return (implode(" ", $parts));
}

function school_private_school_info(array $school)
{
    $number = trim((string)($school["school_registration_number"] ?? ""));
    $academy = trim((string)($school["school_registration_academy"] ?? ""));
    if ($number == "")
        return ("");
    $authority = $academy == "" ? "auprès du rectorat" : "auprès du rectorat de l’académie de ".$academy;
    return ("Établissement d’enseignement supérieur privé, immatriculé sous le numéro ".$number." ".$authority.".");
}

function school_formation_info(array $school)
{
    $number = trim((string)($school["formation_activity_number"] ?? ""));
    $region = trim((string)($school["formation_activity_region"] ?? ""));
    if ($number == "")
        return ("");
    if ($region == "")
        $authority = "auprès du préfet de région";
    else if (preg_match('/^(?:d[’\']|de |du |des )/ui', $region))
        $authority = "auprès du préfet de région ".$region;
    else
        $authority = "auprès du préfet de région d’".$region;
    return ("Organisme de formation, déclaration d’activité enregistrée sous le numéro ".$number." ".$authority.".");
}

function school_alternation_info(array $school)
{
    $number = trim((string)($school["alternation_registration_number"] ?? ""));
    $academy = trim((string)($school["alternation_registration_academy"] ?? ""));
    if ($number == "")
        return ("");
    $authority = $academy == "" ? "auprès du rectorat" : "auprès du rectorat de l’académie de ".$academy;
    return ("CFA déclaré sous le n° ".$number." ".$authority.".");
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

    if (($sync = sync_school_organization($school))->is_error())
	return ($sync);

    $school = fetch_school($school["id"]);
    if (is_object($school) && $school->is_error())
	return ($school);

    // L'organisation juridique liée possède son propre identity.dab.
    if (isset($school["id_organization"]) && (int)$school["id_organization"] > 0 &&
        function_exists("refresh_organization"))
    {
        $ret = refresh_organization((int)$school["id_organization"]);
        if (is_object($ret) && $ret->is_error())
            return ($ret);
    }

    $name = $school["name"] ?? ($school["fr_name"] ?? ($school["codename"] ?? ""));
    $legal_name = $school["legal_name"] ?? $name;
    $legal_address = $school["organization_address"] ?? "";
    $school_address = $school["address"] ?? $legal_address;
    $main_info = function_exists("enterprise_main_info") ? enterprise_main_info($school) : school_main_info($school);
    $school_info = school_private_school_info($school);
    $formation_info = school_formation_info($school);
    $alternation_info = school_alternation_info($school);

    $organization_dabsic = [
	"id" => $school["id_organization"] ?? -1,
	"codename" => $school["organization_codename"] ?? ($school["codename"] ?? ""),
	"name" => $school["organization_name"] ?? $name,
	"fr_name" => $school["fr_name"] ?? $name,
	"en_name" => $school["en_name"] ?? $name,
	"legal_name" => $legal_name,
	"address" => $legal_address,
	"legal_address" => $legal_address,
	"phone" => $school["organization_phone"] ?? "",
	"mail" => $school["organization_mail"] ?? "",
	"courriel" => $school["organization_mail"] ?? "",
	"website" => $school["website"] ?? "",
	"SIRET" => $school["siret"] ?? "",
	"registration_registry" => $school["registration_registry"] ?? "",
	"registration_number" => $school["registration_number"] ?? "",
	"main_info" => $main_info,
    ];

    // Dans un document, School représente l'établissement contractant dans son
    // ensemble. Les informations de sa personne morale sont donc disponibles
    // directement dans School, et non uniquement sous School.Organization.
    $school_dabsic = array_replace($organization_dabsic, [
	"id" => $school["id"] ?? -1,
	"codename" => $school["codename"] ?? "",
	"name" => $name,
	"fr_name" => $school["fr_name"] ?? $name,
	"en_name" => $school["en_name"] ?? $name,
	"legal_name" => $legal_name,
	"address" => $school_address,
	"training_address" => $school["school_address"] ?? $school_address,
	"legal_address" => $legal_address,
	"phone" => $school["phone"] ?? "",
	"mail" => $school["mail"] ?? "",
	"courriel" => $school["mail"] ?? "",
	"base_url" => $school["base_url"] ?? "",

	"SIRET" => $school["siret"] ?? "",
	"NDA" => $school["formation_activity_number"] ?? "",
	"UAI" => $school["uai"] ?? "",
	"cfa_name" => $school["cfa_name"] ?? "",
	"executing_establishment_name" => $school["executing_establishment_name"] ?? "",
	"representative" => "",
	"role" => "",
	"tutor" => [
	    "first_name" => "",
	    "family_name" => "",
	    "mail" => "",
	    "phone" => "",
	    "role" => "",
	],

	"organization" => $organization_dabsic,
	"main_info" => $main_info,
	"school_info" => $school_info,
	"formation_info" => $formation_info,
	"alternation_info" => $alternation_info,
	"school_registration_number" => $school["school_registration_number"] ?? "",
	"school_registration_academy" => $school["school_registration_academy"] ?? "",
	"formation_activity_number" => $school["formation_activity_number"] ?? "",
	"formation_activity_region" => $school["formation_activity_region"] ?? "",
	"alternation_registration_number" => $school["alternation_registration_number"] ?? "",
	"alternation_registration_academy" => $school["alternation_registration_academy"] ?? "",

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
    ]);

    // identity.dab décrit l'école elle-même. Il ne choisit pas le scope dans
    // lequel elle sera insérée par un document. La page Documents peut ainsi
    // l'insérer naturellement dans School, Company ou tout autre scope sans
    // dupliquer ici des wrappers historiques.
    return (generate_dabsic(
	$school_dabsic,
	$Configuration->SchoolsDir($school["codename"])."identity.dab"
    ));
}

