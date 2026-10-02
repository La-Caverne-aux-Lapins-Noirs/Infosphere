<?php

function diploma_project_root()
{
    $root = realpath(__DIR__."/..");
    return ($root === false ? dirname(__DIR__) : $root);
}

function diploma_absolute_path($path)
{
    $path = str_replace("\\", "/", (string)$path);
    if ($path != "" && $path[0] == "/")
        return ($path);
    return (rtrim(diploma_project_root(), "/")."/".ltrim($path, "/"));
}

function diploma_school_resource_directory(array $school)
{
    global $Configuration;

    return ($Configuration->SchoolsDir($school["codename"]));
}

function diploma_school_configuration_path(array $school, $absolute = false)
{
    $path = diploma_school_resource_directory($school)."diploma.dab";
    return ($absolute ? diploma_absolute_path($path) : $path);
}

function diploma_dabsic_string($value)
{
    $encoded = json_encode((string)$value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return ($encoded === false ? '""' : $encoded);
}

function diploma_school_secret_generate()
{
    try
    {
        return (bin2hex(random_bytes(32)));
    }
    catch (Throwable $exception)
    {
        // A weak fallback would defeat the purpose of the diploma secret.
        return ("");
    }
}

function diploma_school_secret(array $school)
{
    return (strtolower(trim((string)($school["diploma_secret"] ?? ""))));
}

function diploma_school_secret_is_valid($secret)
{
    return (is_string($secret)
        && preg_match('/^[a-f0-9]{64}$/D', strtolower(trim($secret))) === 1);
}

/**
 * Existing schools predate diploma secrets. Generate one once, persist it in
 * the school row and never rotate it automatically afterwards.
 */
function diploma_school_ensure_secret(array &$school)
{
    $secret = diploma_school_secret($school);
    if (diploma_school_secret_is_valid($secret))
        return ($secret);
    // Do not silently replace a non-empty invalid value: the director must be
    // able to notice and deliberately fix it from the diploma configuration.
    if ($secret != "")
        return ("");

    $secret = diploma_school_secret_generate();
    if ($secret == "")
        return ("");
    $ret = update_table("school", (int)$school["id"], ["diploma_secret" => $secret]);
    if ($ret->is_error())
        return ("");
    $school["diploma_secret"] = $secret;
    return ($secret);
}

function diploma_school_save_secret(array &$school, $secret)
{
    $secret = strtolower(trim((string)$secret));
    if (!diploma_school_secret_is_valid($secret))
        return (new ErrorResponse("DiplomaInvalidSecret"));
    $ret = update_table("school", (int)$school["id"], ["diploma_secret" => $secret]);
    if ($ret->is_error())
        return ($ret);
    $school["diploma_secret"] = $secret;
    return (new Response);
}

function diploma_school_name(array $school)
{
    foreach (["name", "fr_name", "legal_name", "codename"] as $field)
        if (trim((string)($school[$field] ?? "")) != "")
            return (trim((string)$school[$field]));
    return ("Établissement");
}

function diploma_school_characterization(array $school)
{
    if (!empty($school["is_school"]))
        return ("École privée d'enseignement supérieur technique");
    if (!empty($school["is_cfa"]))
        return ("Centre de formation d'apprentis");
    if (!empty($school["is_of"]))
        return ("Organisme de formation");
    return (diploma_school_name($school));
}

function diploma_school_certification_text(array $school)
{
    return ("Diplôme délivré par ".diploma_school_name($school).
        ". Ce document atteste la validation d'un parcours de formation et des compétences associées conformément au règlement pédagogique de l'établissement.");
}

function diploma_school_font_reference(array $school, $filename)
{
    return (str_replace("\\", "/", diploma_school_resource_directory($school).$filename));
}

function diploma_school_font_library_directory(array $school, $absolute = false)
{
    $path = diploma_school_resource_directory($school)."diploma/fonts/";
    return ($absolute ? diploma_absolute_path($path) : str_replace("\\", "/", $path));
}

function diploma_font_storage_name($name)
{
    $extension = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
    $basename = pathinfo((string)$name, PATHINFO_FILENAME);
    if (function_exists("iconv"))
    {
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $basename);
        if ($ascii !== false)
            $basename = $ascii;
    }
    if (function_exists("mb_strtolower"))
        $basename = mb_strtolower($basename, "UTF-8");
    else
        $basename = strtolower($basename);
    $basename = preg_replace('/[^a-z0-9_-]+/', "_", (string)$basename);
    $basename = trim((string)$basename, "_-");
    if ($basename == "")
        $basename = "font";
    $basename = substr($basename, 0, 80);
    return ($basename.".".$extension);
}

function diploma_school_font_library(array $school)
{
    $fonts = [];
    $school_directory = str_replace("\\", "/", diploma_school_resource_directory($school));

    // Keep the former single-font installation visible and usable. New uploads
    // live in diploma/fonts/ and may be assigned independently to each role.
    foreach (["ttf", "otf"] as $extension)
    {
        $relative = $school_directory."diploma_font.".$extension;
        $absolute = diploma_absolute_path($relative);
        if (is_file($absolute) && is_readable($absolute))
            $fonts[$relative] = [
                "relative" => $relative,
                "absolute" => $absolute,
                "name" => basename($relative),
                "managed" => false,
                "legacy" => true,
                "mtime" => (int)@filemtime($absolute),
                "size" => (int)@filesize($absolute),
            ];
    }

    $relative_directory = diploma_school_font_library_directory($school, false);
    $absolute_directory = diploma_school_font_library_directory($school, true);
    if (is_dir($absolute_directory))
        foreach (scandir($absolute_directory) ?: [] as $name)
        {
            if ($name === "." || $name === "..")
                continue ;
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($extension, ["ttf", "otf"], true))
                continue ;
            $absolute = $absolute_directory.$name;
            if (!is_file($absolute) || !is_readable($absolute))
                continue ;
            $relative = $relative_directory.$name;
            $fonts[$relative] = [
                "relative" => $relative,
                "absolute" => $absolute,
                "name" => $name,
                "managed" => true,
                "legacy" => false,
                "mtime" => (int)@filemtime($absolute),
                "size" => (int)@filesize($absolute),
            ];
        }

    uasort($fonts, function ($a, $b) {
        if (!empty($a["legacy"]) != !empty($b["legacy"]))
            return (!empty($a["legacy"]) ? -1 : 1);
        return (strnatcasecmp((string)$a["name"], (string)$b["name"]));
    });
    return ($fonts);
}

function diploma_school_font_slots()
{
    return ([
        "title" => "diploma_title_font.dab",
        "promotion" => "diploma_promotion_font.dab",
        "school_name" => "diploma_school_name_font.dab",
        "recommendation" => "diploma_recommendation_font.dab",
        "recipient_name" => "diploma_recipient_name_font.dab",
        "recipient_alias" => "diploma_recipient_alias_font.dab",
        "recipient_birth" => "diploma_recipient_birth_font.dab",
        "attribution" => "diploma_attribution_font.dab",
        "certification" => "diploma_certification_font.dab",
        "signatory_label" => "diploma_signatory_label_font.dab",
        "signatory_name" => "diploma_signatory_name_font.dab",
        "signatory_title" => "diploma_signatory_title_font.dab",
        "diploma_number" => "diploma_number_font.dab",
        "school_phrase" => "diploma_school_font.dab",
    ]);
}

function diploma_font_definition_resource($path)
{
    if (!is_file($path) || !is_readable($path))
        return ("");
    $content = @file_get_contents($path);
    if (!is_string($content)
        || !preg_match('/^\\s*RessourceFile\\s*=\\s*("(?:\\\\.|[^"\\\\])*")\\s*$/m', $content, $match))
        return ("");
    $decoded = json_decode($match[1], true);
    return (is_string($decoded) ? str_replace("\\", "/", $decoded) : "");
}

function diploma_school_font_assignments(array $school)
{
    $directory = diploma_school_resource_directory($school);
    $assignments = [];
    foreach (diploma_school_font_slots() as $slot => $definition)
        $assignments[$slot] = diploma_font_definition_resource(
            diploma_absolute_path($directory.$definition)
        );
    return ($assignments);
}

function diploma_school_font_usage(array $school)
{
    $usage = [];
    foreach (diploma_school_font_assignments($school) as $slot => $resource)
        if ($resource != "")
        {
            if (!isset($usage[$resource]))
                $usage[$resource] = [];
            $usage[$resource][] = $slot;
        }
    return ($usage);
}

function diploma_school_default_font_path(array $school)
{
    $fonts = diploma_school_font_library($school);
    if (!count($fonts))
        return ("");
    $first = reset($fonts);
    return (is_array($first) ? (string)$first["absolute"] : "");
}

function diploma_school_font_revision(array $school)
{
    $revision = 0;
    foreach (diploma_school_font_library($school) as $font)
        $revision = max($revision, (int)($font["mtime"] ?? 0));
    $directory = diploma_school_resource_directory($school);
    foreach (diploma_school_font_slots() as $definition)
        $revision = max($revision, (int)@filemtime(diploma_absolute_path($directory.$definition)));
    return ($revision);
}

function diploma_school_font_definitions()
{
    // Geometry follows the complete reference configuration shipped by the
    // current GenerateDiploma engine. Colours deliberately keep Infosphere's
    // black/green visual identity instead of copying the neutral demo theme.
    return ([
        "diploma_title_font.dab" => [5612, 992, 210, "Middle", "Middle", 6, 4],
        "diploma_promotion_font.dab" => [1400, 270, 98, "Left", "Middle", 2, 2],
        "diploma_school_name_font.dab" => [4490, 270, 120, "Middle", "Middle", 3, 4],
        "diploma_recommendation_font.dab" => [4630, 220, 76, "Middle", "Middle", 2, 1],
        "diploma_recipient_name_font.dab" => [3508, 340, 130, "Middle", "Middle", 2, 2],
        "diploma_recipient_alias_font.dab" => [2400, 150, 76, "Middle", "Middle", 2, 2],
        "diploma_recipient_birth_font.dab" => [3508, 220, 72, "Middle", "Middle", 2, 1],
        "diploma_attribution_font.dab" => [3508, 220, 68, "Middle", "Middle", 2, 1],
        "diploma_certification_font.dab" => [5470, 445, 54, "Middle", "Middle", 2, 1],
        "diploma_signatory_label_font.dab" => [1180, 95, 54, "Middle", "Middle", 0, 1],
        "diploma_signatory_name_font.dab" => [1180, 85, 56, "Middle", "Middle", 0, 1],
        "diploma_signatory_title_font.dab" => [1180, 120, 50, "Middle", "Top", 0, 1],
        "diploma_number_font.dab" => [6416, 270, 150, "Left", "Top", 3, 2],
        "diploma_school_font.dab" => [6416, 270, 110, "Right", "Top", 3, 2],
    ]);
}

/**
 * Generated institutional defaults for the current GenerateDiploma format.
 * They are passed BEFORE the school's editable diploma.dab: a school may thus
 * override any wording/style here without Infosphere overwriting its choices.
 */
function diploma_school_runtime_defaults(array $school)
{
    $logo = school_document_logo_path($school, false, false);
    if ($logo == "")
        $logo = diploma_school_resource_directory($school)."document_logo.png";
    $seal = school_stamp_path($school, false);

    return ([
        "Diploma" => [
            "Logo" => ["Path" => $logo],
            "Font" => ["Path" => diploma_school_font_reference($school, "diploma_title_font.dab")],
            "Promotion" => [
                "Font" => diploma_school_font_reference($school, "diploma_promotion_font.dab"),
            ],
            "School" => [
                "Name" => diploma_school_name($school),
                "NameFont" => diploma_school_font_reference($school, "diploma_school_name_font.dab"),
                "Characterization" => diploma_school_characterization($school),
            ],
            "Recommendation" => [
                "Font" => diploma_school_font_reference($school, "diploma_recommendation_font.dab"),
                "Text" => "Sur proposition de l'équipe pédagogique et après validation des instances de l'établissement",
            ],
            "Recipient" => [
                "NameFont" => diploma_school_font_reference($school, "diploma_recipient_name_font.dab"),
                "AliasFont" => diploma_school_font_reference($school, "diploma_recipient_alias_font.dab"),
                "BirthFont" => diploma_school_font_reference($school, "diploma_recipient_birth_font.dab"),
            ],
            "Attribution" => [
                "Font" => diploma_school_font_reference($school, "diploma_attribution_font.dab"),
                "Text" => "se voit attribuer le",
            ],
            "Certification" => [
                "Font" => diploma_school_font_reference($school, "diploma_certification_font.dab"),
                "Text" => diploma_school_certification_text($school),
            ],
            "Seal" => ["Path" => $seal],
            "SignatoryFonts" => [
                "LabelFont" => diploma_school_font_reference($school, "diploma_signatory_label_font.dab"),
                "NameFont" => diploma_school_font_reference($school, "diploma_signatory_name_font.dab"),
                "TitleFont" => diploma_school_font_reference($school, "diploma_signatory_title_font.dab"),
            ],
            "Footer" => [
                "DiplomaNumber" => [
                    "Font" => diploma_school_font_reference($school, "diploma_number_font.dab"),
                ],
                "SchoolPhrase" => [
                    "Font" => diploma_school_font_reference($school, "diploma_school_font.dab"),
                ],
            ],
        ],
    ]);
}

function diploma_school_runtime_configuration_file(array $school, $prefix = "infosphere_diploma_school_")
{
    return (diploma_generate_configuration_file(diploma_school_runtime_defaults($school), $prefix));
}

function diploma_school_default_configuration(array $school)
{
    $directory = str_replace("\\", "/", diploma_school_resource_directory($school));
    $logo = school_document_logo_path($school, false, false);
    if ($logo == "")
        $logo = $directory."document_logo.png";
    $seal = school_stamp_path($school, false);
    $seal_configuration = $seal == "" ? "" :
        "  [Seal\n".
        "    Path = ".diploma_dabsic_string($seal)."\n".
        "  ]\n";

    return ("[Diploma\n".
        "  Width = 3508\n".
        "  Height = 2480\n".
        "  SuperSampling = 2\n".
        "  Background = \"#000000\"\n\n".
        "  [Botanical\n".
        "    Enabled = true\n".
        "    Color = \"#00FF78B4\"\n".
        "    Margin = 170\n".
        "    MinScale = 280\n".
        "    MaxScale = 520\n".
        "    MinDepth = 3\n".
        "    MaxDepth = 5\n".
        "    MinThickness = 1\n".
        "    MaxThickness = 3\n".
        "  ]\n\n".
        "  [Border\n".
        "    Color = \"#00FF60\"\n".
        "    Margin = 150\n".
        "    MinHorizontalLength = 420\n".
        "    MaxHorizontalLength = [].Diploma.Width / 3\n".
        "    MinVerticalLength = 220\n".
        "    MaxVerticalLength = [].Diploma.Height / 3\n".
        "    MinThickness = 2\n".
        "    MaxThickness = 5\n".
        "    MinLines = 2\n".
        "    MaxLines = 5\n".
        "    LineSpacing = 16\n".
        "    GlowMinStyle = 0\n".
        "    GlowMaxStyle = 8\n".
        "  ]\n\n".
        "  [Logo\n".
        "    Path = ".diploma_dabsic_string($logo)."\n".
        "  ]\n".
        "  [Font\n".
        "    Path = ".diploma_dabsic_string($directory."diploma_title_font.dab")."\n".
        "  ]\n".
        "  [Promotion\n".
        "    Font = ".diploma_dabsic_string($directory."diploma_promotion_font.dab")."\n".
        "  ]\n".
        "  [School\n".
        "    Name = ".diploma_dabsic_string(diploma_school_name($school))."\n".
        "    NameFont = ".diploma_dabsic_string($directory."diploma_school_name_font.dab")."\n".
        "    Characterization = ".diploma_dabsic_string(diploma_school_characterization($school))."\n".
        "  ]\n".
        "  [Recommendation\n".
        "    Font = ".diploma_dabsic_string($directory."diploma_recommendation_font.dab")."\n".
        "    Text = ".diploma_dabsic_string("Sur proposition de l'équipe pédagogique et après validation des instances de l'établissement")."\n".
        "  ]\n".
        "  [Recipient\n".
        "    NameFont = ".diploma_dabsic_string($directory."diploma_recipient_name_font.dab")."\n".
        "    AliasFont = ".diploma_dabsic_string($directory."diploma_recipient_alias_font.dab")."\n".
        "    BirthFont = ".diploma_dabsic_string($directory."diploma_recipient_birth_font.dab")."\n".
        "  ]\n".
        "  [Attribution\n".
        "    Font = ".diploma_dabsic_string($directory."diploma_attribution_font.dab")."\n".
        "    Text = \"se voit attribuer le\"\n".
        "  ]\n".
        "  [Text\n".
        "    Color = \"#00FF78E6\"\n".
        "  ]\n".
        "  [Certification\n".
        "    Font = ".diploma_dabsic_string($directory."diploma_certification_font.dab")."\n".
        "    Text = ".diploma_dabsic_string(diploma_school_certification_text($school))."\n".
        "  ]\n".
        $seal_configuration.
        "  [SignatoryFonts\n".
        "    LabelFont = ".diploma_dabsic_string($directory."diploma_signatory_label_font.dab")."\n".
        "    NameFont = ".diploma_dabsic_string($directory."diploma_signatory_name_font.dab")."\n".
        "    TitleFont = ".diploma_dabsic_string($directory."diploma_signatory_title_font.dab")."\n".
        "  ]\n".
        "  [Footer\n".
        "    [DiplomaNumber\n".
        "      Font = ".diploma_dabsic_string($directory."diploma_number_font.dab")."\n".
        "    ]\n".
        "    [SchoolPhrase\n".
        "      Font = ".diploma_dabsic_string($directory."diploma_school_font.dab")."\n".
        "    ]\n".
        "  ]\n".
        "]\n");
}

function diploma_write_atomic($target, $content, $mode = 0640)
{
    if (($ret = new_directory($target))->is_error())
        return ($ret);
    $temporary = tempnam(dirname($target), ".diploma-");
    if ($temporary === false)
        return (new ErrorResponse("DiplomaCannotWrite"));
    $written = file_put_contents($temporary, $content, LOCK_EX);
    if ($written === false || $written !== strlen($content))
    {
        @unlink($temporary);
        return (new ErrorResponse("DiplomaCannotWrite"));
    }
    @chmod($temporary, $mode);
    if (!@rename($temporary, $target))
    {
        @unlink($temporary);
        return (new ErrorResponse("DiplomaCannotWrite"));
    }
    return (new Response);
}

function diploma_font_payloads(array $data, $field = "diploma_fonts")
{
    if (!isset($data[$field]) || !is_array($data[$field]) || !count($data[$field]))
        return (NULL);
    $out = [];
    foreach ($data[$field] as $file)
    {
        if (!is_array($file) || !isset($file["name"], $file["content"]))
            return (false);
        $raw = base64_decode((string)$file["content"], true);
        if ($raw === false)
            return (false);
        $out[] = ["name" => (string)$file["name"], "content" => $raw];
    }
    return ($out);
}

function diploma_font_payload(array $data)
{
    $fonts = diploma_font_payloads($data, "diploma_font");
    if ($fonts === NULL || $fonts === false)
        return ($fonts);
    return (count($fonts) ? $fonts[0] : NULL);
}

function diploma_font_is_valid($name, $content)
{
    $extension = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
    if (!in_array($extension, ["ttf", "otf"], true) || !is_string($content)
        || strlen($content) < 12 || strlen($content) > 20 * 1024 * 1024)
        return (false);
    $signature = substr($content, 0, 4);
    return ($signature === "\x00\x01\x00\x00"
        || in_array($signature, ["OTTO", "true", "typ1", "ttcf"], true));
}

function diploma_font_configuration($font, $width, $height, $glyph, $horizontal,
    $vertical = "Middle", $outline = 3, $interglyph = 2)
{
    return ("RessourceFile = ".diploma_dabsic_string($font)."\n".
        "BoxSize = ".(int)$width.", ".(int)$height."\n".
        "GlyphSize = ".(int)$glyph."\n".
        "HorizontalAlign = ".diploma_dabsic_string($horizontal)."\n".
        "VerticalAlign = ".diploma_dabsic_string($vertical)."\n".
        "Color = 0, 255, 120, 230\n".
        "OutlineColor = 0, 28, 10, 190\n".
        "OutlineSize = ".(int)$outline."\n".
        "Interglyph = ".(int)$interglyph.", 0\n".
        "String = \"\"\n");
}

function diploma_school_write_font_definitions(array $school, $relative_font)
{
    $directory = diploma_school_resource_directory($school);
    foreach (diploma_school_font_definitions() as $name => $definition)
    {
        $content = diploma_font_configuration(
            $relative_font,
            $definition[0],
            $definition[1],
            $definition[2],
            $definition[3],
            $definition[4],
            $definition[5],
            $definition[6]
        );
        if (($ret = diploma_write_atomic(diploma_absolute_path($directory.$name), $content))->is_error())
            return ($ret);
    }
    return (new Response);
}

function diploma_school_write_font_files(array $school, array $font)
{
    $directory = diploma_school_resource_directory($school);
    $extension = strtolower(pathinfo($font["name"], PATHINFO_EXTENSION));
    $relative_font = str_replace("\\", "/", $directory."diploma_font.".$extension);
    $absolute_font = diploma_absolute_path($relative_font);

    if (($ret = diploma_write_atomic($absolute_font, $font["content"]))->is_error())
        return ($ret);
    return (diploma_school_write_font_definitions($school, $relative_font));
}

function diploma_school_store_font_library(array $school, array $fonts)
{
    if (count($fonts) > 12)
        return (new ErrorResponse("DiplomaTooManyFonts"));
    $directory = diploma_school_font_library_directory($school, false);
    foreach ($fonts as $font)
    {
        if (!is_array($font) || !isset($font["name"], $font["content"])
            || !diploma_font_is_valid($font["name"], $font["content"]))
            return (new ErrorResponse("DiplomaInvalidFont"));
        $name = diploma_font_storage_name($font["name"]);
        $target = diploma_absolute_path($directory.$name);
        if (($ret = diploma_write_atomic($target, $font["content"]))->is_error())
            return ($ret);
    }

    // A fresh school gets sensible definitions immediately. Subsequent uploads
    // merely enrich the library and never silently replace existing choices.
    if (!diploma_school_ensure_font_definitions($school))
    {
        $library = diploma_school_font_library($school);
        if (!count($library))
            return (new ErrorResponse("DiplomaInvalidFont"));
        $first = reset($library);
        if (!is_array($first)
            || diploma_school_write_font_definitions($school, $first["relative"])->is_error())
            return (new ErrorResponse("DiplomaCannotWrite"));
    }
    return (new Response);
}

function diploma_school_update_font_definition_resource(array $school, $definition, $relative_font)
{
    $definitions = diploma_school_font_definitions();
    if (!isset($definitions[$definition]))
        return (new ErrorResponse("DiplomaInvalidFontAssignment"));

    $target = diploma_absolute_path(diploma_school_resource_directory($school).$definition);
    $content = is_file($target) ? @file_get_contents($target) : false;
    if (!is_string($content) || trim($content) == "")
    {
        $d = $definitions[$definition];
        $content = diploma_font_configuration(
            $relative_font, $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6]
        );
    }
    else
    {
        $replacement = "RessourceFile = ".diploma_dabsic_string($relative_font);
        $count = 0;
        $content = preg_replace('/^\\s*RessourceFile\\s*=.*$/m', $replacement, $content, 1, $count);
        if (!is_string($content))
            return (new ErrorResponse("DiplomaCannotWrite"));
        if ($count == 0)
            $content = $replacement."\n".$content;
        if (substr($content, -1) != "\n")
            $content .= "\n";
    }
    return (diploma_write_atomic($target, $content));
}

function diploma_school_save_font_assignments(array $school, array $data)
{
    $slots = diploma_school_font_slots();
    $library = diploma_school_font_library($school);
    $current = diploma_school_font_assignments($school);
    $changed = false;

    foreach ($slots as $slot => $definition)
    {
        $field = "diploma_font_slot_".$slot;
        if (!array_key_exists($field, $data))
            continue ;
        $resource = str_replace("\\", "/", trim((string)$data[$field]));
        if ($resource == "")
            return (new ErrorResponse("DiplomaInvalidFontAssignment"));
        // The GUI may faithfully carry a manually-authored external resource
        // while another slot is changed. Only a new choice must come from the
        // school's managed font library.
        if (!isset($library[$resource]) && ($current[$slot] ?? "") !== $resource)
            return (new ErrorResponse("DiplomaInvalidFontAssignment"));
        if (($current[$slot] ?? "") === $resource)
            continue ;
        if (($ret = diploma_school_update_font_definition_resource($school, $definition, $resource))->is_error())
            return ($ret);
        $changed = true;
    }
    return (new ValueResponse(["changed" => $changed]));
}

function diploma_school_delete_font(array $school, $resource)
{
    $resource = str_replace("\\", "/", trim((string)$resource));
    $library = diploma_school_font_library($school);
    if (!isset($library[$resource]) || empty($library[$resource]["managed"]))
        return (new ErrorResponse("DiplomaInvalidFont"));
    $usage = diploma_school_font_usage($school);
    if (!empty($usage[$resource]))
        return (new ErrorResponse("DiplomaFontInUse"));
    if (!@unlink($library[$resource]["absolute"]))
        return (new ErrorResponse("DiplomaCannotWrite"));
    return (new Response);
}

/**
 * Upgrade schools configured with the former three-font GenDiplome adapter.
 * Rebuild the complete set once when at least one definition is missing. The
 * uploaded TTF/OTF remains untouched.
 */
function diploma_school_ensure_font_definitions(array $school)
{
    $directory = diploma_school_resource_directory($school);
    $missing = [];
    foreach (diploma_school_font_slots() as $slot => $name)
        if (!is_readable(diploma_absolute_path($directory.$name)))
            $missing[$name] = true;
    if (!count($missing))
        return (true);

    $font = diploma_school_default_font_path($school);
    if ($font == "" || !is_readable($font))
        return (false);

    $library = diploma_school_font_library($school);
    $relative_font = "";
    foreach ($library as $candidate)
        if ($candidate["absolute"] === $font)
        {
            $relative_font = $candidate["relative"];
            break ;
        }
    if ($relative_font == "")
        return (false);

    $definitions = diploma_school_font_definitions();
    foreach (array_keys($missing) as $name)
    {
        $d = $definitions[$name];
        if (diploma_write_atomic(
            diploma_absolute_path($directory.$name),
            diploma_font_configuration($relative_font, $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6])
        )->is_error())
            return (false);
    }
    return (true);
}

function diploma_school_save_configuration(array $school, array $data)
{
    $has_configuration = isset($data["diploma_configuration"])
        && is_string($data["diploma_configuration"]);
    $has_secret = array_key_exists("diploma_secret", $data);
    $font = diploma_font_payload($data); // Legacy single-font form support.
    $fonts = diploma_font_payloads($data, "diploma_fonts");
    $has_assignments = false;
    foreach (array_keys(diploma_school_font_slots()) as $slot)
        $has_assignments = $has_assignments || array_key_exists("diploma_font_slot_".$slot, $data);
    $delete_font = trim((string)($data["diploma_font_delete"] ?? ""));

    if (!$has_configuration && $font === NULL && $fonts === NULL && !$has_secret
        && !$has_assignments && $delete_font == "")
        return (new ErrorResponse("DiplomaMissingConfiguration"));

    $secret = NULL;
    if ($has_secret)
    {
        $secret = strtolower(trim((string)$data["diploma_secret"]));
        if (!diploma_school_secret_is_valid($secret))
            return (new ErrorResponse("DiplomaInvalidSecret"));
    }

    $content = NULL;
    if ($has_configuration)
    {
        $content = str_replace(["\r\n", "\r"], "\n", $data["diploma_configuration"]);
        if (trim($content) == "")
            return (new ErrorResponse("DiplomaMissingConfiguration"));
        if (strlen($content) > 256 * 1024 || strpos($content, "\0") !== false)
            return (new ErrorResponse("DiplomaInvalidConfiguration"));
        if (substr($content, -1) != "\n")
            $content .= "\n";
        $validation = dabsic_editor_validate_content($content);
        if (!$validation["ok"])
            return (new ErrorResponse($validation["error"], $validation["details"] ?? ""));
    }

    if ($font === false || (is_array($font) && !diploma_font_is_valid($font["name"], $font["content"])))
        return (new ErrorResponse("DiplomaInvalidFont"));
    if ($fonts === false)
        return (new ErrorResponse("DiplomaInvalidFont"));
    if (is_array($fonts))
        foreach ($fonts as $candidate)
            if (!diploma_font_is_valid($candidate["name"] ?? "", $candidate["content"] ?? NULL))
                return (new ErrorResponse("DiplomaInvalidFont"));

    if (is_array($font))
        if (($ret = diploma_school_write_font_files($school, $font))->is_error())
            return ($ret);
    if (is_array($fonts))
        if (($ret = diploma_school_store_font_library($school, $fonts))->is_error())
            return ($ret);
    if ($has_assignments)
        if (($ret = diploma_school_save_font_assignments($school, $data))->is_error())
            return ($ret);
    if ($delete_font != "")
        if (($ret = diploma_school_delete_font($school, $delete_font))->is_error())
            return ($ret);
    if ($has_configuration)
        if (($ret = diploma_write_atomic(diploma_school_configuration_path($school, true), $content))->is_error())
            return ($ret);
    if ($has_secret)
        if (($ret = diploma_school_save_secret($school, $secret))->is_error())
            return ($ret);
    return (new Response);
}

function diploma_school_font_path(array $school)
{
    $assignments = diploma_school_font_assignments($school);
    $title = (string)($assignments["title"] ?? "");
    if ($title != "")
    {
        $absolute = diploma_absolute_path($title);
        if (is_file($absolute) && is_readable($absolute))
            return ($absolute);
    }
    return (diploma_school_default_font_path($school));
}

function diploma_school_fonts_ready(array $school)
{
    if (!diploma_school_ensure_font_definitions($school))
        return (false);
    foreach (diploma_school_font_assignments($school) as $resource)
    {
        if ($resource == "")
            return (false);
        $absolute = diploma_absolute_path($resource);
        if (!is_file($absolute) || !is_readable($absolute))
            return (false);
    }
    return (true);
}

function diploma_generator_path()
{
    global $Configuration;

    $configured = trim((string)($Configuration->Properties["diploma_generator"] ?? ""));
    $candidates = $configured == "" ? [] : [$configured];
    // Prefer the Debian package location, then a traditional local install.
    // diploma_run_generator() provides Xvfb itself when the selected binary
    // has no DISPLAY, so neither candidate needs to be a wrapper.
    $candidates = array_merge($candidates, ["/usr/bin/gendiploma", "/usr/local/bin/gendiploma"]);
    foreach ($candidates as $candidate)
    {
        $absolute = $candidate != "" && $candidate[0] == "/"
            ? $candidate : diploma_absolute_path($candidate);
        if (is_file($absolute) && is_executable($absolute))
            return ($absolute);
    }
    foreach (explode(PATH_SEPARATOR, (string)getenv("PATH")) as $directory)
    {
        $candidate = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."gendiploma";
        if (is_file($candidate) && is_executable($candidate))
            return ($candidate);
    }
    return (NULL);
}

function diploma_school_status(array $school)
{
    $configuration = diploma_school_configuration_path($school, true);
    $logo = school_document_logo_path($school, true, false);
    $fonts_ready = diploma_school_fonts_ready($school);
    $generator = diploma_generator_path();
    $secret = diploma_school_secret($school);
    return ([
        "configuration" => is_file($configuration) && is_readable($configuration),
        "logo" => $logo != "" && is_file($logo) && is_readable($logo),
        "font" => $fonts_ready,
        "generator" => $generator !== NULL,
        "secret" => diploma_school_secret_is_valid($secret),
        "ready" => is_file($configuration) && is_readable($configuration)
            && $logo != "" && is_file($logo) && is_readable($logo)
            && $fonts_ready && $generator !== NULL
            && diploma_school_secret_is_valid($secret),
    ]);
}

function diploma_director_schools_for_user($id_user)
{
    global $User;

    if (!$User || !logged_in())
        return ([]);
    $out = [];
    foreach (user_school_ids((int)$id_user, "STUDENT") as $id_school)
    {
        // Do not use is_director_for_school(): it deliberately grants global
        // administrators access, while diploma issuance is director-only.
        if (!user_has_school_authority((int)$User["id"], "DIRECTOR", (int)$id_school))
            continue ;
        $school = fetch_school((int)$id_school);
        if (is_array($school) && isset($school["id"]))
            $out[(int)$school["id"]] = $school;
    }
    return ($out);
}

function can_generate_user_diploma($id_user)
{
    return (count(diploma_director_schools_for_user((int)$id_user)) > 0);
}

function diploma_certification_name(array $title)
{
    foreach (["fr_name", "en_name", "codename"] as $field)
        if (trim((string)($title[$field] ?? "")) != "")
            return (trim((string)$title[$field]));
    return ("diplome");
}

function diploma_person_is_female(array $person)
{
    $gender = strtolower(trim((string)($person["gender"] ?? "")));
    return (in_array($gender, ["f", "female", "femme", "madame", "mme"], true));
}

function diploma_person_is_male(array $person)
{
    $gender = strtolower(trim((string)($person["gender"] ?? "")));
    return (in_array($gender, ["m", "male", "homme", "monsieur", "mr", "m."], true));
}

function diploma_person_name(array $person)
{
    $first = trim((string)($person["first_name"] ?? ""));
    $family = trim((string)($person["use_name"] ?? ""));
    if ($family == "")
        $family = trim((string)($person["family_name"] ?? ""));
    if (function_exists("mb_strtoupper"))
        $family = mb_strtoupper($family, "UTF-8");
    else
        $family = strtoupper($family);
    $name = trim($first." ".$family);
    return ($name != "" ? $name : (string)($person["codename"] ?? ""));
}

function diploma_recipient_birth_information(array $user)
{
    $administrative = function_exists("user_identity_student_administrative_fields")
        ? user_identity_student_administrative_fields($user) : [];
    $birth_date = trim((string)($user["birth_date"] ?? ""));

    // Contracts and the profile use BirthCity, while some admission forms
    // historically used BirthPlace. Treat both as the same diploma datum; the
    // editable BirthCity value wins when both exist.
    $birth_place = trim((string)($administrative["BirthCity"]
        ?? ($administrative["birth_city"]
        ?? ($administrative["BirthPlace"]
        ?? ($administrative["birth_place"] ?? "")))));

    // BirthCountry is intentionally distinct from Nationality. Never infer the
    // country of birth from nationality: foreign students may have a different
    // nationality, place of birth and country of birth. Accept the historical
    // CountryOfBirth spelling as a compatibility fallback.
    $birth_country = trim((string)($administrative["BirthCountry"]
        ?? ($administrative["birth_country"]
        ?? ($administrative["CountryOfBirth"]
        ?? ($administrative["country_of_birth"] ?? "")))));

    return ([
        "Date" => $birth_date,
        "Place" => $birth_place,
        "Country" => $birth_country,
    ]);
}

function diploma_recipient_birth_text(array $user)
{
    $birth = diploma_recipient_birth_information($user);
    $birth_date = $birth["Date"];
    $birth_place = $birth["Place"];
    $birth_country = $birth["Country"];
    if ($birth_date == "" && $birth_place == "" && $birth_country == "")
        return ("");

    if (diploma_person_is_female($user))
        $prefix = "Née";
    else if (diploma_person_is_male($user))
        $prefix = "Né";
    else
        $prefix = "Né(e)";

    $text = $prefix;
    if ($birth_date != "")
        $text .= " le ".human_date($birth_date, true);
    if ($birth_place != "")
        $text .= " à ".$birth_place;

    // Keep the country explicit on the diploma, including France, but avoid
    // duplicating it when an imported place already contains the country.
    $place_contains_country = false;
    if ($birth_place != "" && $birth_country != "")
    {
        if (function_exists("mb_stripos"))
            $place_contains_country = mb_stripos($birth_place, $birth_country, 0, "UTF-8") !== false;
        else
            $place_contains_country = stripos($birth_place, $birth_country) !== false;
    }
    if ($birth_country != "" && !$place_contains_country)
        $text .= ($birth_place != "" ? " (".$birth_country.")" : " en ".$birth_country);
    return ($text);
}

function diploma_holder_label(array $user)
{
    // This label describes the expected handwritten act, not the holder's
    // grammatical gender.  Keep it explicit and identical for every diploma.
    return ("Signature du titulaire");
}

function diploma_director_title(array $director)
{
    if (diploma_person_is_female($director))
        return ("Directrice de l'établissement");
    if (diploma_person_is_male($director))
        return ("Directeur de l'établissement");
    return ("Direction de l'établissement");
}

function diploma_promotion_year()
{
    return ((int)datex("Y"));
}

function diploma_default_promotion_year($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (diploma_promotion_year());

    // Cycle 20 is the explicit "hors année" cycle in Infosphere.  It must not
    // move a student's promotion year after the end of their normal cursus.
    $cycle = db_select_one("
        YEAR(DATE_ADD(cycle.first_day, INTERVAL 15 WEEK)) as promotion_year
        FROM user_cycle
        LEFT JOIN cycle ON cycle.id = user_cycle.id_cycle
        WHERE user_cycle.id_user = $id_user
          AND cycle.deleted IS NULL
          AND cycle.done = 1
          AND cycle.cycle != 20
          AND cycle.first_day IS NOT NULL
        ORDER BY DATE_ADD(cycle.first_day, INTERVAL 15 WEEK) DESC, cycle.id DESC
    ");
    $year = (int)($cycle["promotion_year"] ?? 0);
    return ($year >= 1900 && $year <= 2200 ? $year : diploma_promotion_year());
}

function diploma_signatories(array $user, array $director)
{
    $signatories = [[
        "Label" => diploma_holder_label($user),
        "Name" => diploma_person_name($user),
        "Title" => "",
        "Signature" => "",
    ]];

    $director_name = diploma_person_name($director);
    if ($director_name != "")
    {
        // A diploma is intended to be signed on paper.  Do not reuse the
        // intranet signature image here: it is suitable for ordinary
        // administrative documents, not for the original diploma.
        $signatories[] = [
            "Label" => "",
            "Name" => $director_name,
            "Title" => diploma_director_title($director),
            "Signature" => "",
        ];
    }
    return ($signatories);
}

function diploma_safe_filename($name, $fallback = "diplome")
{
    $name = preg_replace('/[\\x00-\\x1F\\x7F\\\\\/:*?"<>|]+/u', "-", trim((string)$name));
    $name = preg_replace('/\s+/u', "_", $name);
    $name = trim((string)$name, " .-_");
    if ($name == "")
    {
        $name = preg_replace('/[\\x00-\\x1F\\x7F\\\\\/:*?"<>|]+/u', "-", trim((string)$fallback));
        $name = preg_replace('/\s+/u', "_", $name);
        $name = trim((string)$name, " .-_");
    }
    if (function_exists("mb_strtolower"))
        $name = mb_strtolower($name, "UTF-8");
    else
        $name = strtolower($name);
    if (function_exists("mb_substr"))
        $name = mb_substr($name, 0, 180, "UTF-8");
    else
        $name = substr($name, 0, 180);
    return ($name.".png");
}

function diploma_output_path(array $user, array $title)
{
    global $Configuration;

    return ($Configuration->UsersDir($user["codename"])."admin/".
        diploma_safe_filename(diploma_certification_name($title), $title["codename"] ?? "diplome"));
}

function diploma_generate_configuration_file(array $data, $prefix)
{
    $base = tempnam(sys_get_temp_dir(), $prefix);
    if ($base === false)
        return (new ErrorResponse("DiplomaCannotWrite"));
    @unlink($base);
    $file = $base.".dab";
    $ret = generate_dabsic($data, $file);
    if ($ret->is_error())
    {
        @unlink($file);
        return ($ret);
    }
    return (new ValueResponse($file));
}

function diploma_generator_environment()
{
    $environment = getenv();
    if (!is_array($environment))
        $environment = [];

    // Apache/PHP-FPM commonly runs with HOME=/var/www, which is deliberately
    // not writable. Mesa/SFML nevertheless tries to create GL caches below
    // $HOME. Give the renderer a private writable runtime area instead of
    // changing permissions on /var/www.
    $runtime = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        .DIRECTORY_SEPARATOR."infosphere-gendiploma-".getmypid();
    $directories = [
        $runtime,
        $runtime.DIRECTORY_SEPARATOR."cache",
        $runtime.DIRECTORY_SEPARATOR."runtime",
        $runtime.DIRECTORY_SEPARATOR."mesa-cache",
    ];
    foreach ($directories as $directory)
    {
        if (!is_dir($directory))
            @mkdir($directory, 0700, true);
        @chmod($directory, 0700);
    }

    $environment["HOME"] = $runtime;
    $environment["XDG_CACHE_HOME"] = $runtime.DIRECTORY_SEPARATOR."cache";
    $environment["XDG_RUNTIME_DIR"] = $runtime.DIRECTORY_SEPARATOR."runtime";
    $environment["MESA_SHADER_CACHE_DIR"] = $runtime.DIRECTORY_SEPARATOR."mesa-cache";
    // There is no physical GPU/display attached to the web process. Force
    // Mesa down its software-rendering path instead of probing a hardware DRI
    // driver through the virtual X server.
    $environment["LIBGL_ALWAYS_SOFTWARE"] = "1";

    return ($environment);
}

function diploma_run_generator(array $arguments)
{
    // GenDiplome uses SFML and therefore needs an X display even when it only
    // renders a PNG.  The packaged /usr/bin/gendiploma wrapper already takes
    // care of this, but keep Infosphere robust when a raw binary is selected.
    $display = trim((string)getenv("DISPLAY"));
    if ($display == "")
    {
        $xvfb = NULL;
        foreach (["/usr/bin/xvfb-run", "/usr/local/bin/xvfb-run"] as $candidate)
            if (is_file($candidate) && is_executable($candidate))
            {
                $xvfb = $candidate;
                break ;
            }
        if ($xvfb !== NULL)
            $arguments = array_merge([$xvfb, "-a"], $arguments);
    }

    $process = proc_open($arguments, [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ], $pipes, diploma_project_root(), diploma_generator_environment(), ["bypass_shell" => true]);
    if (!is_resource($process))
        return (["status" => 127, "stdout" => "", "stderr" => "Cannot start GenDiplome"]);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return ([
        "status" => proc_close($process),
        "stdout" => $stdout === false ? "" : $stdout,
        "stderr" => $stderr === false ? "" : $stderr,
    ]);
}


function diploma_preview_director(array $school)
{
    global $Database;
    global $User;

    if (isset($User["id"]) && user_has_school_authority((int)$User["id"], "DIRECTOR", (int)$school["id"]))
        return ([
            "id" => (int)$User["id"],
            "codename" => (string)($User["codename"] ?? "director"),
            "first_name" => (string)($User["first_name"] ?? "Prénom"),
            "family_name" => (string)($User["family_name"] ?? "Nom"),
        ]);

    $director_authority = user_school_authority_sql("DIRECTOR");
    $director = db_select_one("
        user.id as id, user.codename as codename, user.first_name as first_name,
        user.family_name as family_name
        FROM user_school
        LEFT JOIN user ON user.id = user_school.id_user
        WHERE user_school.id_school = ".((int)$school["id"])."
          AND user_school.authority = ".$director_authority."
          AND user.deleted IS NULL
        ORDER BY user_school.id ASC
    ");
    if (is_array($director) && isset($director["id"]))
        return ($director);

    return ([
        "id" => 0,
        "codename" => "director",
        "first_name" => "Prénom",
        "family_name" => "Nom",
    ]);
}

function diploma_png_is_complete($path)
{
    if (!is_file($path) || !is_readable($path) || @filesize($path) <= 0)
        return (false);
    $image = @getimagesize($path);
    if (!is_array($image) || ($image["mime"] ?? "") !== "image/png"
        || (int)($image[0] ?? 0) <= 0 || (int)($image[1] ?? 0) <= 0)
        return (false);

    // getimagesize() only needs the PNG header. Decode the complete file when
    // GD is available so a truncated image written just before a crash is not
    // accepted as a successful diploma.
    if (function_exists("imagecreatefrompng"))
    {
        $decoded = @imagecreatefrompng($path);
        if ($decoded === false)
            return (false);
        imagedestroy($decoded);
    }
    return (true);
}

function diploma_school_preview_cache_path(array $school, $absolute = true)
{
    $path = diploma_school_resource_directory($school)."diploma_preview.png";
    return ($absolute ? diploma_absolute_path($path) : str_replace("\\", "/", $path));
}

function diploma_school_preview_signature_path(array $school, $absolute = true)
{
    $path = diploma_school_resource_directory($school)."diploma_preview.signature";
    return ($absolute ? diploma_absolute_path($path) : str_replace("\\", "/", $path));
}

function diploma_school_preview_lock_path(array $school)
{
    return (diploma_absolute_path(diploma_school_resource_directory($school)."diploma_preview.lock"));
}

function diploma_school_preview_source_signature(array $school)
{
    $parts = [
        "school-name=".diploma_school_name($school),
        "school-kind=".diploma_school_characterization($school),
        // The secret influences procedural generation. Store only its digest.
        "secret=".hash("sha256", diploma_school_secret($school)),
    ];

    $configuration = diploma_school_configuration_path($school, true);
    if (is_file($configuration) && is_readable($configuration))
    {
        $digest = @hash_file("sha256", $configuration);
        $parts[] = "configuration=".($digest === false ? "" : $digest);
    }
    else
        $parts[] = "configuration=missing";

    $files = [];
    foreach ([
        school_document_logo_path($school, true, false),
        school_stamp_path($school, true),
    ] as $path)
        if (is_string($path) && $path != "")
            $files[$path] = true;

    $directory = diploma_school_resource_directory($school);
    foreach (diploma_school_font_slots() as $definition)
    {
        $path = diploma_absolute_path($directory.$definition);
        if (is_file($path))
            $files[$path] = true;
    }
    foreach (diploma_school_font_library($school) as $font)
        if (!empty($font["absolute"]))
            $files[(string)$font["absolute"]] = true;

    $generator = diploma_generator_path();
    if ($generator !== NULL)
        $files[$generator] = true;
    $files[__FILE__] = true;

    ksort($files, SORT_STRING);
    foreach (array_keys($files) as $path)
    {
        if (!is_file($path) || !is_readable($path))
        {
            $parts[] = "file=".$path."|missing";
            continue ;
        }
        // Font binaries can be large. mtime + size is sufficient for them;
        // Dabsic itself is hashed above so same-second text edits are detected.
        $parts[] = "file=".$path."|".(int)@filesize($path)."|".(int)@filemtime($path);
    }
    return (hash("sha256", implode("\n", $parts)));
}

function diploma_school_preview_state(array $school)
{
    $cache = diploma_school_preview_cache_path($school, true);
    $exists = is_file($cache) && is_readable($cache) && (int)@filesize($cache) > 0;
    $stored_signature = "";
    $signature_file = diploma_school_preview_signature_path($school, true);
    if (is_readable($signature_file))
        $stored_signature = trim((string)@file_get_contents($signature_file));
    $current_signature = diploma_school_preview_source_signature($school);

    return ([
        "exists" => $exists,
        "path" => $cache,
        "mtime" => $exists ? (int)@filemtime($cache) : 0,
        "stale" => $exists && ($stored_signature == "" || $stored_signature !== $current_signature),
        "source_signature" => $current_signature,
    ]);
}

function diploma_read_school_preview(array $school)
{
    $state = diploma_school_preview_state($school);
    if (!$state["exists"])
        return (new ErrorResponse("DiplomaPreviewMissing"));
    $content = @file_get_contents($state["path"]);
    if (!is_string($content) || $content === "")
        return (new ErrorResponse("DiplomaPreviewMissing"));
    return (new ValueResponse([
        "filename" => "diploma-preview-".
            preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($school["codename"] ?? "school")).".png",
        "content_type" => "image/png",
        "disposition" => "inline",
        "content" => $content,
    ]));
}

function diploma_generator_failure_details(array $process, $temporary)
{
    $details = trim((string)($process["stderr"] ?? "")."\n".(string)($process["stdout"] ?? ""));
    $status = (int)($process["status"] ?? -1);
    $size = is_file($temporary) ? (int)@filesize($temporary) : 0;
    $prefix = "GenDiplome exit status: ".$status."; output PNG: ".
        ($size > 0 ? $size." bytes" : "absent");
    return (trim($prefix.($details != "" ? "\n".$details : "")));
}

function diploma_preview_debug_bundle(array $school, $defaults_file, $title_file, $recipient_file, $temporary, array $process)
{
    // Preview data is synthetic. Keep a self-contained copy of the exact
    // inputs only when preview generation crashes so the renderer can be
    // reproduced outside Apache without touching a real student's files.
    $suffix = "";
    try
    {
        $suffix = bin2hex(random_bytes(3));
    }
    catch (Throwable $exception)
    {
        $suffix = dechex(mt_rand(0, 0xFFFFFF));
    }
    // Keep debug bundles in Infosphere's persistent resource tree rather
    // than sys_get_temp_dir(). Apache commonly runs with systemd PrivateTmp,
    // which makes a /tmp path printed by PHP invisible from the administrator's
    // normal shell. dres/debug is already used for document-generation debug
    // artifacts and remains easy to inspect on the host.
    $debug_root = dirname(__DIR__).DIRECTORY_SEPARATOR."dres".
        DIRECTORY_SEPARATOR."debug".DIRECTORY_SEPARATOR."gendiploma";
    if (!is_dir($debug_root) && !@mkdir($debug_root, 0770, true) && !is_dir($debug_root))
        return ("");
    @chmod($debug_root, 0770);

    $directory = $debug_root.DIRECTORY_SEPARATOR."preview-".
        date("Ymd-His")."-".getmypid()."-".$suffix;
    if (!@mkdir($directory, 0770, true) && !is_dir($directory))
        return ("");
    @chmod($directory, 0770);

    $inputs = [
        "defaults.dab" => $defaults_file,
        "school.dab" => diploma_school_configuration_path($school, true),
        "title.dab" => $title_file,
        "recipient.dab" => $recipient_file,
    ];
    foreach ($inputs as $name => $source)
    {
        $target = $directory.DIRECTORY_SEPARATOR.$name;
        if (!is_readable($source) || !@copy($source, $target))
            return ("");
        @chmod($target, 0660);
    }

    $generator = diploma_generator_path();
    if ($generator === NULL)
        return ($directory);
    $xvfb = NULL;
    foreach (["/usr/bin/xvfb-run", "/usr/local/bin/xvfb-run"] as $candidate)
        if (is_file($candidate) && is_executable($candidate))
        {
            $xvfb = $candidate;
            break ;
        }

    $q = function ($value) { return (escapeshellarg((string)$value)); };
    $prefix = "#!/bin/sh\nset -eu\n".
        // Resolve the bundle from the script itself. This keeps the scripts
        // usable even when the deployment directory is moved or copied.
        "HERE=\$(CDPATH= cd -- \"\$(dirname -- \"\$0\")\" && pwd)\n".
        "PROJECT_ROOT=\$(CDPATH= cd -- \"\$HERE/../../../..\" && pwd)\n".
        "cd \"\$PROJECT_ROOT\"\n".
        "RUNTIME=\"\$HERE/runtime-env\"\n".
        "mkdir -p \"\$RUNTIME/cache\" \"\$RUNTIME/runtime\" \"\$RUNTIME/mesa-cache\"\n".
        "chmod 700 \"\$RUNTIME\" \"\$RUNTIME/cache\" \"\$RUNTIME/runtime\" \"\$RUNTIME/mesa-cache\"\n".
        "export HOME=\"\$RUNTIME\"\n".
        "export XDG_CACHE_HOME=\"\$RUNTIME/cache\"\n".
        "export XDG_RUNTIME_DIR=\"\$RUNTIME/runtime\"\n".
        "export MESA_SHADER_CACHE_DIR=\"\$RUNTIME/mesa-cache\"\n".
        "export LIBGL_ALWAYS_SOFTWARE=1\n".
        // Never persist the school secret in dres/debug. A manual replay must
        // provide it explicitly in the environment.
        ": \"\${GENDIPLOMA_SECRET:?Set GENDIPLOMA_SECRET to the school diploma secret}\"\n";
    $runner = ($xvfb !== NULL ? $q($xvfb)." -a " : "").$q($generator);
    $arguments = " \"\$HERE/defaults.dab\" \"\$HERE/school.dab\" \"\$HERE/title.dab\" \"\$HERE/recipient.dab\"".
        " --secret \"\$GENDIPLOMA_SECRET\" --output \"\$HERE/output.png\"";
    @file_put_contents($directory.DIRECTORY_SEPARATOR."run.sh", $prefix."exec ".$runner.$arguments."\n");
    @chmod($directory.DIRECTORY_SEPARATOR."run.sh", 0770);

    if ($xvfb !== NULL)
    {
        $gdb = $prefix.
            "if ! command -v gdb >/dev/null 2>&1; then\n".
            "  echo 'gdb is not installed.' >&2\n".
            "  exit 127\n".
            "fi\n".
            "exec ".$q($xvfb)." -a gdb --batch -ex run -ex 'thread apply all bt full' --args ".
            $q($generator).$arguments."\n";
        @file_put_contents($directory.DIRECTORY_SEPARATOR."gdb.sh", $gdb);
        @chmod($directory.DIRECTORY_SEPARATOR."gdb.sh", 0770);
    }

    $report = "GenDiplome exit status: ".(int)($process["status"] ?? -1)."\n".
        "stdout:\n".(string)($process["stdout"] ?? "")."\n\n".
        "stderr:\n".(string)($process["stderr"] ?? "")."\n";
    @file_put_contents($directory.DIRECTORY_SEPARATOR."failure.txt", $report);
    @chmod($directory.DIRECTORY_SEPARATOR."failure.txt", 0660);

    $project_root = dirname(__DIR__).DIRECTORY_SEPARATOR;
    if (strncmp($directory, $project_root, strlen($project_root)) === 0)
        return (str_replace(DIRECTORY_SEPARATOR, "/", substr($directory, strlen($project_root))));
    return ($directory);
}

function diploma_render_school_preview(array $school)
{
    $status = diploma_school_status($school);
    if (!$status["ready"])
        return (new ErrorResponse("DiplomaSchoolNotReady"));

    $cache = diploma_school_preview_cache_path($school, true);
    if (($ret = new_directory($cache))->is_error())
        return ($ret);
    $lock = @fopen(diploma_school_preview_lock_path($school), "c");
    if ($lock === false)
        return (new ErrorResponse("DiplomaCannotWrite"));
    @chmod(diploma_school_preview_lock_path($school), 0660);
    if (!@flock($lock, LOCK_EX | LOCK_NB))
    {
        @fclose($lock);
        return (new ErrorResponse("DiplomaPreviewBusy"));
    }

    try
    {
        $source_signature = diploma_school_preview_source_signature($school);
        $school_name = diploma_school_name($school);
    $promotion_year = diploma_promotion_year();
    $preview_title = [
        "id" => 0,
        "codename" => "preview",
        "code" => "RNCP-00000",
        "fr_name" => "Diplôme d'exemple",
    ];
    $preview_student = [
        "id" => 1234,
        "codename" => "student-preview",
        "nickname" => "pseudo",
        "first_name" => "Prénom",
        "use_name" => "",
        "family_name" => "Nom",
        "gender" => "",
        "birth_date" => "2000-01-01",
        "administrative_data" => json_encode([
            "BirthPlace" => "Ville",
            "BirthCountry" => "France",
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
    $preview_director = [
        "id" => 0,
        "codename" => "preview-director",
        "first_name" => "Prénom",
        "use_name" => "",
        "family_name" => "Directeur",
        "gender" => "m",
    ];

    // The generated defaults complete old school diploma.dab files without
    // taking control away from the editable school configuration: merge order
    // is defaults -> school -> certification -> recipient.
    $defaults_file = diploma_school_runtime_configuration_file(
        $school, "infosphere_diploma_preview_defaults_"
    );
    if ($defaults_file->is_error())
        return ($defaults_file);

    $title_file = diploma_generate_configuration_file([
        "Diploma" => [
            "Promotion" => ["Year" => $promotion_year],
            "Text" => ["Main" => "DIPLÔME D'EXEMPLE"],
        ],
        "Certification" => [
            "Id" => 0,
            "Codename" => "preview",
            "Code" => "RNCP-00000",
            "Name" => "Certification d'exemple",
        ],
    ], "infosphere_diploma_preview_title_");
    if ($title_file->is_error())
    {
        @unlink($defaults_file->value);
        return ($title_file);
    }

    $recipient_name = diploma_person_name($preview_student);
    $recipient_file = diploma_generate_configuration_file([
        "Diploma" => [
            "Recipient" => [
                "Promo" => $promotion_year,
                "Number" => 1234,
                "Codename" => (string)($school["codename"] ?? "school").":preview:student-preview",
                "Name" => $recipient_name,
                "Alias" => "pseudo",
                "BirthText" => diploma_recipient_birth_text($preview_student),
                "BirthPlace" => diploma_recipient_birth_information($preview_student)["Place"],
                "BirthCountry" => diploma_recipient_birth_information($preview_student)["Country"],
            ],
            "Signatories" => diploma_signatories($preview_student, $preview_director),
        ],
        "Student" => [
            "Id" => 1234,
            "Codename" => "student-preview",
            "FirstName" => "Prénom",
            "FamilyName" => "Nom",
            "Name" => $recipient_name,
        ],
        "School" => [
            "Id" => (int)$school["id"],
            "Codename" => (string)($school["codename"] ?? "school"),
            "Name" => $school_name,
            "Director" => [
                "Id" => 0,
                "Codename" => "preview-director",
                "FirstName" => "Prénom",
                "FamilyName" => "Directeur",
            ],
        ],
    ], "infosphere_diploma_preview_recipient_");
    if ($recipient_file->is_error())
    {
        @unlink($defaults_file->value);
        @unlink($title_file->value);
        return ($recipient_file);
    }

        $temporary = tempnam(dirname($cache), ".diploma-preview-");
        if ($temporary === false)
        {
            @unlink($defaults_file->value);
            @unlink($title_file->value);
            @unlink($recipient_file->value);
            return (new ErrorResponse("DiplomaCannotWrite"));
        }
        @unlink($temporary);
        $temporary .= ".png";
    $process = diploma_run_generator([
        diploma_generator_path(),
        $defaults_file->value,
        diploma_school_configuration_path($school, true),
        $title_file->value,
        $recipient_file->value,
        "--secret", diploma_school_secret($school),
        "--output", $temporary,
    ]);

    if (!diploma_png_is_complete($temporary))
    {
        $details = diploma_generator_failure_details($process, $temporary);
        $debug = diploma_preview_debug_bundle(
            $school, $defaults_file->value, $title_file->value,
            $recipient_file->value, $temporary, $process
        );
        @unlink($defaults_file->value);
        @unlink($title_file->value);
        @unlink($recipient_file->value);
        @unlink($temporary);
        if ($debug != "")
            $details .= "\nDebug bundle: ".$debug;
        return (new ErrorResponse("DiplomaGenerationFailed", $details));
    }

        @unlink($defaults_file->value);
        @unlink($title_file->value);
        @unlink($recipient_file->value);

        // A Dabsic/font/logo may have been saved while GenDiplome was running.
        // Never publish a preview produced from inputs that are already stale.
        if ($source_signature !== diploma_school_preview_source_signature($school))
        {
            @unlink($temporary);
            return (new ErrorResponse("DiplomaPreviewChangedDuringGeneration"));
        }

        // $temporary lives beside the cache: rename() is an atomic replacement
        // on the deployment filesystem, so readers either get the previous
        // complete preview or the new complete preview, never a partial PNG.
        @chmod($temporary, 0660);
        if (!@rename($temporary, $cache))
        {
            @unlink($temporary);
            return (new ErrorResponse("DiplomaCannotWrite"));
        }
        $signature_ret = diploma_write_atomic(
            diploma_school_preview_signature_path($school, true),
            $source_signature."\n",
            0660
        );
        if ($signature_ret->is_error())
            return ($signature_ret);

        return (new ValueResponse([
            "preview_revision" => (int)@filemtime($cache),
            "stale" => false,
        ]));
    }
    finally
    {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function diploma_default_issue_date($id_user, $id_school = 0)
{
    $id_user = (int)$id_user;
    $id_school = (int)$id_school;
    if ($id_user <= 0)
        return ("");

    // "Dernier cycle" = dernière liaison user_cycle de cet élève.  La date de
    // fin est la dernière session planifiée dans les activités de ce cycle.
    $school_join = $id_school > 0
        ? "LEFT JOIN school_cycle ON school_cycle.id_cycle = cycle.id AND school_cycle.id_school = $id_school"
        : "";
    $school_where = $id_school > 0 ? "AND school_cycle.id IS NOT NULL" : "";
    $cycle = db_select_one("
        user_cycle.id_cycle
        FROM user_cycle
        LEFT JOIN cycle ON cycle.id = user_cycle.id_cycle
        $school_join
        WHERE user_cycle.id_user = $id_user
          AND cycle.id IS NOT NULL
          AND cycle.deleted IS NULL
          $school_where
        ORDER BY user_cycle.id DESC
    ");
    if (!is_array($cycle) || (int)($cycle["id_cycle"] ?? 0) <= 0)
        return ("");

    $id_cycle = (int)$cycle["id_cycle"];
    $end = db_select_one("
        DATE(MAX(COALESCE(session.end_date, session.begin_date))) as issue_date
        FROM activity_cycle
        LEFT JOIN session ON session.id_activity = activity_cycle.id_activity
        WHERE activity_cycle.id_cycle = $id_cycle
          AND session.id IS NOT NULL
          AND session.deleted IS NULL
    ");
    return (is_array($end) ? trim((string)($end["issue_date"] ?? "")) : "");
}

function diploma_issue_date_validate($date)
{
    $date = trim((string)$date);
    if ($date == "")
        return ("");
    if (!preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/', $date, $m))
        return (NULL);
    if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1]))
        return (NULL);
    return ($date);
}

function diploma_issue_date_display($date)
{
    $date = diploma_issue_date_validate($date);
    if ($date === NULL || $date === "")
        return ("");
    [$year, $month, $day] = array_map("intval", explode("-", $date));
    return (sprintf("%02d/%02d/%04d", $day, $month, $year));
}

function diploma_issue_place(array $school)
{
    // Le lieu porté sur le diplôme doit être le lieu de formation, pas le
    // siège social de l'organisation. Dans Infosphere, school.address est
    // l'adresse de l'établissement / lieu de formation.
    $address = trim((string)($school["address"] ?? ""));
    if ($address != "")
    {
        $one_line = trim((string)preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $address)));
        // Cas français usuel : "32a Avenue Pierre Sémard 94200 Ivry-sur-Seine"
        // ou la même adresse sur plusieurs lignes.
        if (preg_match('/(?:^|[ ,;])\d{5}\s+(.+?)(?:\s*,?\s*France)?$/ui', $one_line, $match))
        {
            $city = trim((string)$match[1], " \t\n\r\0\x0B,;");
            if ($city != "")
                return ($city);
        }
    }

    // Compatibilité avec d'anciennes installations où la ville était un
    // champ séparé de l'école.
    foreach (["formation_city", "training_city", "city"] as $field)
    {
        $city = trim((string)($school[$field] ?? ""));
        if ($city != "")
            return ($city);
    }
    return ("");
}

function diploma_generate_for_user($id_user, $id_title, $id_school, array $options = [], $issue_date = NULL)
{
    global $Database;
    global $User;

    $id_user = (int)$id_user;
    $id_title = (int)$id_title;
    $id_school = (int)$id_school;
    $issue_date = diploma_issue_date_validate($issue_date);
    if ($issue_date === NULL)
        return (new ErrorResponse("InvalidParameter"));
    if ($issue_date === "")
        $issue_date = diploma_default_issue_date($id_user, $id_school);
    $issue_date_display = diploma_issue_date_display($issue_date);
    $user = db_select_one("
        id, codename, nickname, first_name, use_name, family_name, gender,
        birth_date, administrative_data, profile_status, deleted
        FROM user WHERE id = $id_user
    ");
    if ($user == NULL || $user["deleted"] !== NULL || $user["profile_status"] !== "member")
        return (new ErrorResponse("UserNotFound"));
    $schools = diploma_director_schools_for_user($id_user);
    if (!isset($schools[$id_school]))
        return (new ErrorResponse("DiplomaDirectorOnly"));
    $school = $schools[$id_school];
    if (($title_response = fetch_certification_title($id_title))->is_error())
        return ($title_response);
    $title = $title_response->value;
    if ($title["deleted"] !== NULL)
        return (new ErrorResponse("DiplomaUnknownCertification"));
    $status = diploma_school_status($school);
    if (!$status["ready"])
        return (new ErrorResponse("DiplomaSchoolNotReady"));

    $main_text = trim((string)($title["diploma_text"] ?? ""));
    if ($main_text == "")
        $main_text = diploma_certification_name($title);
    $promotion_year = (int)($options["promotion_year"] ?? 0);
    if ($promotion_year < 1900 || $promotion_year > 2200)
        $promotion_year = diploma_default_promotion_year($id_user);
    $include_nickname = array_key_exists("include_nickname", $options)
        ? !empty($options["include_nickname"]) : true;
    $recipient_name = diploma_person_name($user);
    $recipient_alias = $include_nickname
        ? trim((string)($user["nickname"] ?? "")) : "";
    $director = db_select_one("
        id, codename, nickname, first_name, use_name, family_name, gender
        FROM user WHERE id = ".(int)$User["id"]." AND deleted IS NULL
    ");
    if (!is_array($director))
        $director = is_array($User) ? $User : [];

    $defaults_file = diploma_school_runtime_configuration_file(
        $school, "infosphere_diploma_defaults_"
    );
    if ($defaults_file->is_error())
        return ($defaults_file);

    $diploma_issue = ["Date" => $issue_date_display];
    $issue_place = diploma_issue_place($school);
    if ($issue_place != "")
        $diploma_issue["Place"] = $issue_place;

    $title_file = diploma_generate_configuration_file([
        "Diploma" => [
            "Promotion" => ["Year" => $promotion_year],
            "Text" => ["Main" => $main_text],
        ],
        "Certification" => [
            "Id" => (int)$title["id"],
            "Codename" => (string)$title["codename"],
            "Code" => (string)($title["code"] ?? ""),
            "Name" => diploma_certification_name($title),
        ],
    ], "infosphere_diploma_title_");
    if ($title_file->is_error())
    {
        @unlink($defaults_file->value);
        return ($title_file);
    }

    $recipient_data = [
        "Promo" => $promotion_year,
        "Number" => $id_user,
        // GenDiplome uses Recipient.Codename as the procedural seed.  Keep
        // the immutable certification codename in it so the same student
        // receives a different procedural diploma for each title.
        "Codename" => $school["codename"].":".$title["codename"].":".$user["codename"],
        "Name" => $recipient_name,
        "BirthText" => diploma_recipient_birth_text($user),
    ];
    $recipient_birth = diploma_recipient_birth_information($user);
    if ($recipient_birth["Place"] != "")
        $recipient_data["BirthPlace"] = $recipient_birth["Place"];
    if ($recipient_birth["Country"] != "")
        $recipient_data["BirthCountry"] = $recipient_birth["Country"];
    if ($recipient_alias != "")
        $recipient_data["Alias"] = $recipient_alias;

    $recipient_file = diploma_generate_configuration_file([
        "Diploma" => [
            "Issue" => $diploma_issue,
            "Recipient" => $recipient_data,
            "Signatories" => diploma_signatories($user, $director),
        ],
        "Student" => array_merge([
            "Id" => $id_user,
            "Codename" => (string)$user["codename"],
            "FirstName" => (string)$user["first_name"],
            "FamilyName" => (string)$user["family_name"],
            "Name" => $recipient_name,
        ], $recipient_alias != "" ? ["Nickname" => $recipient_alias] : []),
        "School" => [
            "Id" => (int)$school["id"],
            "Codename" => (string)$school["codename"],
            "Name" => diploma_school_name($school),
            "Director" => [
                "Id" => (int)($director["id"] ?? 0),
                "Codename" => (string)($director["codename"] ?? ""),
                "FirstName" => (string)($director["first_name"] ?? ""),
                "FamilyName" => (string)($director["family_name"] ?? ""),
            ],
        ],
    ], "infosphere_diploma_recipient_");
    if ($recipient_file->is_error())
    {
        @unlink($defaults_file->value);
        @unlink($title_file->value);
        return ($recipient_file);
    }

    $target = diploma_absolute_path(diploma_output_path($user, $title));
    if (($directory = new_directory($target))->is_error())
    {
        @unlink($defaults_file->value);
        @unlink($title_file->value);
        @unlink($recipient_file->value);
        return ($directory);
    }
    $temporary = tempnam(dirname($target), ".diploma-render-");
    if ($temporary === false)
    {
        @unlink($defaults_file->value);
        @unlink($title_file->value);
        @unlink($recipient_file->value);
        return (new ErrorResponse("DiplomaCannotWrite"));
    }
    @unlink($temporary);
    $temporary .= ".png";
    $process = diploma_run_generator([
        diploma_generator_path(),
        $defaults_file->value,
        diploma_school_configuration_path($school, true),
        $title_file->value,
        $recipient_file->value,
        "--secret", diploma_school_secret($school),
        "--output", $temporary,
    ]);
    @unlink($defaults_file->value);
    @unlink($title_file->value);
    @unlink($recipient_file->value);
    if (!diploma_png_is_complete($temporary))
    {
        $details = diploma_generator_failure_details($process, $temporary);
        @unlink($temporary);
        return (new ErrorResponse("DiplomaGenerationFailed", $details));
    }
    if ($Database->query("INSERT IGNORE INTO user_title (id_user, id_title, type) VALUES ($id_user, $id_title, 'certified')") == false)
    {
        @unlink($temporary);
        return (new ErrorResponse("CannotEdit"));
    }
    @chmod($temporary, 0640);
    if (!@rename($temporary, $target))
    {
        @unlink($temporary);
        return (new ErrorResponse("DiplomaCannotWrite"));
    }
    return (new ValueResponse([
        "filename" => basename($target),
        "path" => diploma_output_path($user, $title),
    ]));
}
