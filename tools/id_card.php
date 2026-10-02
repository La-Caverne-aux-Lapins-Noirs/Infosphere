<?php

function id_card_project_root()
{
    $root = realpath(__DIR__."/..");
    return ($root === false ? dirname(__DIR__) : $root);
}

function id_card_absolute_path($path)
{
    $path = str_replace("\\", "/", (string)$path);
    if ($path !== "" && $path[0] === "/")
        return ($path);
    return (rtrim(id_card_project_root(), "/")."/".ltrim($path, "/"));
}

function id_card_school_resource_directory(array $school)
{
    global $Configuration;

    return ($Configuration->SchoolsDir($school["codename"]));
}

function id_card_school_configuration_path(array $school, $absolute = false)
{
    $path = id_card_school_resource_directory($school)."id_card.dab";
    return ($absolute ? id_card_absolute_path($path) : $path);
}

function id_card_school_background_path(array $school, $absolute = false)
{
    $path = id_card_school_resource_directory($school)."id_card_background.png";
    return ($absolute ? id_card_absolute_path($path) : $path);
}

function id_card_dabsic_string($value)
{
    $encoded = json_encode((string)$value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return ($encoded === false ? '""' : $encoded);
}

function id_card_school_default_configuration(array $school)
{
    $directory = str_replace("\\", "/", id_card_school_resource_directory($school));
    $logo = school_document_logo_path($school, false, false);
    if ($logo == "")
        $logo = school_site_logo_path($school, false, true);

    return (
        "[Card\n".
        "  Width = 990\n".
        "  Height = 600\n".
        "  Background = ".id_card_dabsic_string($directory."id_card_background.png")."\n".
        "  BackgroundColor = \"#101010\"\n".
        "  TextColor = \"#FFFFFF\"\n".
        "  AccentColor = \"#00FF60\"\n".
        "  FontFile = \"\"\n\n".
        "  [StudentPhoto\n".
        "    X = 58\n".
        "    Y = 174\n".
        "    Width = 182\n".
        "    Height = 222\n".
        "    Radius = 16\n".
        "  ]\n\n".
        "  [SchoolLogo\n".
        "    Path = ".id_card_dabsic_string($logo)."\n".
        "    X = 58\n".
        "    Y = 58\n".
        "    Width = 182\n".
        "    Height = 86\n".
        "  ]\n\n".
        "  [StudentCardTitle\n".
        "    Text = \"CARTE D'ÉTUDIANT\"\n".
        "    X = 275\n".
        "    Y = 92\n".
        "    Width = 680\n".
        "    Height = 46\n".
        "    FontSize = 30\n".
        "    LineHeight = 38\n".
        "  ]\n\n".
        "  [StudentName\n".
        "    X = 275\n".
        "    Y = 176\n".
        "    Width = 680\n".
        "    Height = 54\n".
        "    FontSize = 40\n".
        "    LineHeight = 48\n".
        "  ]\n\n".
        "  [SchoolYear\n".
        "    X = 275\n".
        "    Y = 246\n".
        "    Width = 680\n".
        "    Height = 36\n".
        "    FontSize = 26\n".
        "    LineHeight = 32\n".
        "  ]\n\n".
        "  [Course\n".
        "    X = 275\n".
        "    Y = 291\n".
        "    Width = 680\n".
        "    Height = 70\n".
        "    FontSize = 26\n".
        "    LineHeight = 32\n".
        "    Prefix = \"Année \"\n".
        "  ]\n\n".
        "  [SchoolName\n".
        "    X = 275\n".
        "    Y = 382\n".
        "    Width = 680\n".
        "    Height = 36\n".
        "    FontSize = 25\n".
        "    LineHeight = 31\n".
        "  ]\n\n".
        "  [SchoolAddress\n".
        "    X = 275\n".
        "    Y = 426\n".
        "    Width = 680\n".
        "    Height = 62\n".
        "    FontSize = 22\n".
        "    LineHeight = 28\n".
        "  ]\n\n".
        "  [SchoolCity\n".
        "    X = 275\n".
        "    Y = 497\n".
        "    Width = 680\n".
        "    Height = 34\n".
        "    FontSize = 22\n".
        "    LineHeight = 28\n".
        "  ]\n\n".
        "  [Sheet 'HERMA8840\n".
        "    Columns = 2\n".
        "    Rows = 5\n".
        "    MarginLeft = 220\n".
        "    MarginTop = 254\n".
        "    GapX = 60\n".
        "    GapY = 0\n".
        "    BackgroundColor = \"#FFFFFF\"\n".
        "  ]\n".
        "]\n"
    );
}

function id_card_write_atomic($target, $content, $mode = 0640)
{
    if (($ret = new_directory($target))->is_error())
        return ($ret);
    $temporary = tempnam(dirname($target), ".id-card-");
    if ($temporary === false)
        return (new ErrorResponse("IdCardCannotWrite"));
    $written = file_put_contents($temporary, $content, LOCK_EX);
    if ($written === false || $written !== strlen($content))
    {
        @unlink($temporary);
        return (new ErrorResponse("IdCardCannotWrite"));
    }
    @chmod($temporary, $mode);
    if (!@rename($temporary, $target))
    {
        @unlink($temporary);
        return (new ErrorResponse("IdCardCannotWrite"));
    }
    return (new Response);
}

function id_card_background_payload(array $data)
{
    if (!isset($data["id_card_background"]) || !is_array($data["id_card_background"]) || !count($data["id_card_background"]))
        return (NULL);
    $file = $data["id_card_background"][0];
    if (!is_array($file) || !isset($file["name"], $file["content"]))
        return (NULL);
    $raw = base64_decode((string)$file["content"], true);
    if ($raw === false)
        return (false);
    return (["name" => (string)$file["name"], "content" => $raw]);
}

function id_card_store_background(array $school, array $file)
{
    $extension = strtolower(pathinfo((string)$file["name"], PATHINFO_EXTENSION));
    if (!in_array($extension, ["png", "jpg", "jpeg", "webp"], true))
        return (new ErrorResponse("BadFileFormat"));
    return (school_write_logo_binary($file["content"], id_card_school_background_path($school, true), 300));
}

function id_card_school_save_configuration(array $school, array $data)
{
    $has_configuration = isset($data["id_card_configuration"]) && is_string($data["id_card_configuration"]);
    $background = id_card_background_payload($data);
    if (!$has_configuration && $background === NULL)
        return (new ErrorResponse("IdCardMissingConfiguration"));

    $content = NULL;
    if ($has_configuration)
    {
        $content = str_replace(["\r\n", "\r"], "\n", $data["id_card_configuration"]);
        if (trim($content) == "")
            return (new ErrorResponse("IdCardMissingConfiguration"));
        if (strlen($content) > 256 * 1024 || strpos($content, "\0") !== false)
            return (new ErrorResponse("IdCardInvalidConfiguration"));
        if (substr($content, -1) != "\n")
            $content .= "\n";
        $validation = dabsic_editor_validate_content($content);
        if (!$validation["ok"])
            return (new ErrorResponse($validation["error"], $validation["details"] ?? ""));
    }

    if ($background === false)
        return (new ErrorResponse("BadFileFormat"));
    if (is_array($background))
        if (($ret = id_card_store_background($school, $background))->is_error())
            return ($ret);
    if ($has_configuration)
        return (id_card_write_atomic(id_card_school_configuration_path($school, true), $content));
    return (new Response);
}

function id_card_school_status(array $school)
{
    $configuration = id_card_school_configuration_path($school, true);
    $background = id_card_school_background_path($school, true);
    $logo = school_document_logo_path($school, true, false);
    if ($logo == "")
        $logo = school_site_logo_path($school, true, false);
    $renderer = function_exists("imagecreatetruecolor") && function_exists("imagepng");
    return ([
        "configuration" => is_file($configuration) && is_readable($configuration),
        "background" => is_file($background) && is_readable($background),
        "logo" => $logo != "" && is_file($logo) && is_readable($logo),
        "generator" => $renderer,
        "ready" => (is_file($configuration) && is_readable($configuration)
            && is_file($background) && is_readable($background)
            && $logo != "" && is_file($logo) && is_readable($logo)
            && $renderer),
    ]);
}

function id_card_director_schools_for_user($id_user)
{
    global $User;

    if (!$User || !logged_in())
        return ([]);
    $out = [];
    foreach (user_school_ids((int)$id_user, "STUDENT") as $id_school)
    {
        if (!user_has_school_authority((int)$User["id"], "DIRECTOR", (int)$id_school))
            continue ;
        $school = fetch_school((int)$id_school);
        if (is_array($school) && isset($school["id"]))
            $out[(int)$school["id"]] = $school;
    }
    return ($out);
}

function can_generate_user_id_card($id_user)
{
    return (count(id_card_director_schools_for_user((int)$id_user)) > 0);
}

function id_card_safe_filename($name, $fallback = "carte_etudiante")
{
    $name = preg_replace('/[\\x00-\\x1F\\x7F\\\\\/:*?"<>|]+/u', "-", trim((string)$name));
    $name = preg_replace('/\s+/u', " ", $name);
    $name = trim((string)$name, " .-");
    if ($name == "")
        $name = trim((string)$fallback);
    if (function_exists("mb_substr"))
        $name = mb_substr($name, 0, 180, "UTF-8");
    else
        $name = substr($name, 0, 180);
    return ($name.".png");
}

function id_card_output_path(array $user, array $school)
{
    global $Configuration;

    $label = "carte_etudiante-".($school["codename"] ?? "school");
    return ($Configuration->UsersDir($user["codename"])."admin/".
        id_card_safe_filename($label, "carte_etudiante"));
}

function id_card_school_year_string($timestamp = NULL)
{
    if ($timestamp === NULL)
        $timestamp = now();
    $month = (int)date("n", (int)$timestamp);
    $year = (int)date("Y", (int)$timestamp);
    if ($month >= 9)
        return ($year."-".($year + 1));
    return (($year - 1)."-".$year);
}

function id_card_split_address($address)
{
    $parts = preg_split('/\R+/u', trim((string)$address));
    $parts = array_values(array_filter(array_map("trim", is_array($parts) ? $parts : []), function ($part) {
        return ($part !== "");
    }));
    if (!count($parts))
        return (["address" => "", "city" => ""]);
    if (count($parts) >= 2)
        return ([
            "address" => implode(", ", array_slice($parts, 0, -1)),
            "city" => $parts[count($parts) - 1],
        ]);
    if (strpos($parts[0], ",") !== false)
    {
        $split = array_values(array_filter(array_map("trim", explode(",", $parts[0])), function ($part) {
            return ($part !== "");
        }));
        if (count($split) >= 2)
            return ([
                "address" => implode(", ", array_slice($split, 0, -1)),
                "city" => $split[count($split) - 1],
            ]);
    }
    return (["address" => $parts[0], "city" => ""]);
}

function id_card_normalize_study_year($value)
{
    $value = trim((string)$value);
    if ($value == "")
        return ("");
    if (!preg_match('/^[0-9]{1,2}$/D', $value))
        return (NULL);
    $year = (int)$value;
    if ($year < 1 || $year > 20)
        return (NULL);
    return ((string)$year);
}

function id_card_user_current_course($id_user, $id_school)
{
    global $Language;

    $id_user = (int)$id_user;
    $id_school = (int)$id_school;
    $row = db_select_one("
        cycle.id as id_cycle,
        cycle.codename as codename,
        cycle.{$Language}_name as name,
        cycle.cycle as cycle_level,
        cycle.first_day as first_day,
        user_cycle.cursus as cursus
        FROM user_cycle
        LEFT JOIN cycle ON cycle.id = user_cycle.id_cycle
        LEFT JOIN school_cycle
          ON school_cycle.id_cycle = cycle.id
         AND school_cycle.id_school = $id_school
        WHERE user_cycle.id_user = $id_user
          AND cycle.id IS NOT NULL
          AND cycle.deleted IS NULL
          AND school_cycle.id IS NOT NULL
        ORDER BY
          cycle.cycle DESC,
          cycle.first_day DESC,
          cycle.id DESC
    ");
    if ($row == NULL)
    {
        $user = ["id" => $id_user];
        get_user_promotions($user);
        if (isset($user["greatest_cycle_data"]))
            $row = $user["greatest_cycle_data"];
    }
    if (!is_array($row))
        return ("");
    if (array_key_exists("cycle_level", $row))
        $cycle_level = (int)$row["cycle_level"];
    else if (array_key_exists("cycle", $row))
        $cycle_level = (int)$row["cycle"];
    else
        return ("");
    if ($cycle_level < 0)
        return ("");
    return ((string)(intdiv($cycle_level, 4) + 1));
}

function id_card_preferred_user_photo(array $user)
{
    global $Configuration;

    // Student cards are administrative documents: only the administrative
    // photo may be used. Never fall back to the public avatar or placeholder.
    $photo = id_card_absolute_path(
        $Configuration->UsersDir($user["codename"])."admin/photo.png"
    );
    return (is_file($photo) && is_readable($photo) ? $photo : "");
}

function id_card_payload_for_user($id_user, $id_school, $study_year = NULL)
{
    $id_user = (int)$id_user;
    $id_school = (int)$id_school;
    $user = db_select_one("id, codename, first_name, family_name, nickname, profile_status, deleted FROM user WHERE id = $id_user");
    if ($user == NULL || $user["deleted"] !== NULL || $user["profile_status"] !== "member")
        return (new ErrorResponse("UserNotFound"));
    $schools = id_card_director_schools_for_user($id_user);
    if (!isset($schools[$id_school]))
        return (new ErrorResponse("IdCardDirectorOnly"));
    $school = $schools[$id_school];

    $display_name = trim((string)$user["first_name"]." ".(string)$user["family_name"]);
    if ($display_name == "")
        $display_name = trim((string)($user["nickname"] ?? $user["codename"]));
    $address = id_card_split_address($school["address"] ?? "");
    $school_logo = school_document_logo_path($school, true, false);
    if ($school_logo == "")
        $school_logo = school_site_logo_path($school, true, true);

    $photo_path = id_card_preferred_user_photo($user);
    if ($photo_path == "")
        return (new ErrorResponse("IdCardGenerationFailed", "Photo administrative manquante."));

    $course = id_card_user_current_course($id_user, $id_school);
    if ($study_year !== NULL && trim((string)$study_year) != "")
    {
        $course = id_card_normalize_study_year($study_year);
        if ($course === NULL)
            return (new ErrorResponse("InvalidParameter", "study_year"));
    }

    return (new ValueResponse([
        "user" => $user,
        "school" => $school,
        "card" => [
            "photo_path" => $photo_path,
            "logo_path" => $school_logo,
            "full_name" => trim($display_name." #".(int)$user["id"]),
            "school_year" => id_card_school_year_string(),
            "course" => $course,
            "school_name" => trim((string)($school["name"] ?? $school["codename"])),
            "school_address" => $address["address"],
            "school_city" => $address["city"],
        ],
    ]));
}

function id_card_school_preview_data(array $school)
{
    $address = id_card_split_address($school["address"] ?? "");
    $school_name = trim((string)($school["name"] ?? $school["codename"] ?? ""));
    if ($school_name == "")
        $school_name = "Nom de l'école";
    if ($address["address"] == "")
        $address["address"] = "12 rue de l'Exemple";
    if ($address["city"] == "")
        $address["city"] = "75000 Ville";

    $school_logo = school_document_logo_path($school, true, false);
    if ($school_logo == "")
        $school_logo = school_site_logo_path($school, true, true);
    $avatar = id_card_project_root()."/res/no_avatar.png";
    if (!is_file($avatar))
        $avatar = id_card_project_root()."/res/no_avatar_lab.png";

    return ([
        "photo_path" => $avatar,
        // The default avatar is transparent, unlike a real identity photo.
        // Flatten it on white for the preview so the mock-up does not suggest
        // that the real student's photo will let the card background show through.
        "photo_background" => "#FFFFFF",
        "logo_path" => $school_logo,
        "full_name" => "Prénom Nom #1234",
        "school_year" => id_card_school_year_string(),
        "course" => "3",
        "school_name" => $school_name,
        "school_address" => $address["address"],
        "school_city" => $address["city"],
    ]);
}

function id_card_render_school_preview(array $school)
{
    if (!function_exists("imagecreatetruecolor") || !function_exists("imagepng"))
        return (new ErrorResponse("IdCardGenerationFailed", "L'extension GD de PHP est requise."));

    $configuration = id_card_school_configuration_path($school, true);
    if (!is_readable($configuration))
    {
        $ret = id_card_write_atomic($configuration, id_card_school_default_configuration($school));
        if ($ret->is_error())
            return ($ret);
    }
    $config = id_card_resolve_configuration_file($configuration);
    if ($config->is_error())
        return ($config);

    $image = id_card_render_card_image($config->value, id_card_school_preview_data($school));
    ob_start();
    $ok = @imagepng($image, NULL, 6);
    $content = ob_get_clean();
    imagedestroy($image);
    if (!$ok || !is_string($content) || $content === "")
        return (new ErrorResponse("IdCardGenerationFailed"));

    return (new ValueResponse([
        "filename" => "student-card-preview-".preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($school["codename"] ?? "school")).".png",
        "content_type" => "image/png",
        "disposition" => "inline",
        "content" => $content,
    ]));
}

function id_card_generate_payload_file(array $data, $prefix)
{
    $base = tempnam(sys_get_temp_dir(), $prefix);
    if ($base === false)
        return (new ErrorResponse("IdCardCannotWrite"));
    @unlink($base);
    $file = $base.".json";
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false)
        return (new ErrorResponse("CannotEdit"));
    if (($ret = id_card_write_atomic($file, $json, 0600))->is_error())
        return ($ret);
    return (new ValueResponse($file));
}

function id_card_generate_for_user($id_user, $id_school, $study_year = NULL)
{
    $payload = id_card_payload_for_user((int)$id_user, (int)$id_school, $study_year);
    if ($payload->is_error())
        return ($payload);
    $school = $payload->value["school"];
    $user = $payload->value["user"];
    $status = id_card_school_status($school);
    if (!$status["ready"])
        return (new ErrorResponse("IdCardSchoolNotReady"));

    $data_file = id_card_generate_payload_file($payload->value["card"], "infosphere_id_card_");
    if ($data_file->is_error())
        return ($data_file);

    $target = id_card_absolute_path(id_card_output_path($user, $school));
    if (($directory = new_directory($target))->is_error())
    {
        @unlink($data_file->value);
        return ($directory);
    }
    $temporary = tempnam(dirname($target), ".id-card-render-");
    if ($temporary === false)
    {
        @unlink($data_file->value);
        return (new ErrorResponse("IdCardCannotWrite"));
    }
    @unlink($temporary);
    $temporary .= ".png";

    $render = id_card_render_single_file(
        id_card_school_configuration_path($school, true),
        $data_file->value,
        $temporary
    );
    @unlink($data_file->value);
    if ($render->is_error())
    {
        @unlink($temporary);
        return ($render);
    }
    $image = is_file($temporary) ? @getimagesize($temporary) : false;
    if (!is_array($image) || ($image["mime"] ?? "") !== "image/png")
    {
        @unlink($temporary);
        return (new ErrorResponse("IdCardGenerationFailed"));
    }
    @chmod($temporary, 0640);
    if (!@rename($temporary, $target))
    {
        @unlink($temporary);
        return (new ErrorResponse("IdCardCannotWrite"));
    }
    return (new ValueResponse([
        "filename" => basename($target),
        "path" => id_card_output_path($user, $school),
        "study_year" => (string)($payload->value["card"]["course"] ?? ""),
    ]));
}

function id_card_load_json_file($file)
{
    $content = @file_get_contents((string)$file);
    if (!is_string($content))
        return (NULL);
    $json = json_decode($content, true);
    return (is_array($json) ? $json : NULL);
}

function id_card_parse_scalar($value)
{
    $value = trim((string)$value);
    if ($value === "")
        return ("");
    if ($value[0] === '"' && substr($value, -1) === '"')
    {
        // A Dabsic double-quoted string is JSON-compatible. Decode it as-is:
        // replacing apostrophes by double quotes corrupts perfectly valid text
        // such as "CARTE D'ÉTUDIANT" and used to turn it into an empty string.
        $json = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_string($json))
            return ($json);
        return (substr($value, 1, -1));
    }
    if ($value[0] === "'" && substr($value, -1) === "'")
        return (str_replace(["\\'", "\\\\"], ["'", "\\"], substr($value, 1, -1)));
    if (strcasecmp($value, "true") === 0)
        return (true);
    if (strcasecmp($value, "false") === 0)
        return (false);
    if (preg_match('/^-?[0-9]+$/', $value))
        return ((int)$value);
    if (preg_match('/^-?[0-9]*\.[0-9]+$/', $value))
        return ((float)$value);
    if (strpos($value, ",") !== false)
        return (array_map("id_card_parse_scalar", explode(",", $value)));
    return ($value);
}

function id_card_parse_configuration_text($content)
{
    $root = [];
    $stack = [&$root];
    foreach (preg_split('/\R/u', str_replace(["\r\n", "\r"], "\n", (string)$content)) as $line)
    {
        $trimmed = trim((string)$line);
        if ($trimmed === "" || $trimmed[0] === ';')
            continue ;
        if ($trimmed === "]")
        {
            if (count($stack) > 1)
                array_pop($stack);
            continue ;
        }
        if ($trimmed[0] === '[')
        {
            $name = trim(substr($trimmed, 1));
            if ($name === "")
                continue ;
            $parent_index = count($stack) - 1;
            if (!isset($stack[$parent_index][$name]) || !is_array($stack[$parent_index][$name]))
                $stack[$parent_index][$name] = [];
            $stack[] = &$stack[$parent_index][$name];
            continue ;
        }
        $parts = explode("=", $trimmed, 2);
        if (count($parts) !== 2)
            continue ;
        $key = trim($parts[0]);
        if ($key === "")
            continue ;
        $stack[count($stack) - 1][$key] = id_card_parse_scalar($parts[1]);
    }
    return ($root);
}

function id_card_parse_configuration_file($file)
{
    $content = @file_get_contents((string)$file);
    if (!is_string($content))
        return (NULL);
    return (id_card_parse_configuration_text($content));
}

/*
 * Resolve the Dabsic with the same mergeconf path already used by Infosphere,
 * then let the small card parser consume the resolved Dabsic.  Keeping the
 * parser is useful for the renderer, but expressions, references and includes
 * must be evaluated by mergeconf first instead of being interpreted locally.
 */
function id_card_resolve_configuration_file($file)
{
    $file = id_card_absolute_path($file);
    if (!is_file($file) || !is_readable($file))
        return (new ErrorResponse("IdCardInvalidConfiguration", "Fichier de configuration introuvable ou illisible."));

    $process = dabsic_form_process(
        dabsic_form_mergeconf_command($file, [], true)
    );
    if (in_array((int)($process["status"] ?? 127), [126, 127], true))
        return (new ErrorResponse(
            "IdCardInvalidConfiguration",
            dabsic_form_clean_diagnostic($process["stderr"] ?? "Impossible de lancer mergeconf.")
        ));
    if (($process["status"] ?? 127) !== 0)
    {
        $details = dabsic_form_clean_diagnostic(
            ($process["stderr"] ?? "") !== ""
                ? ($process["stderr"] ?? "")
                : ($process["stdout"] ?? "")
        );
        if ($details == "")
            $details = "mergeconf a terminé avec le code ".(int)($process["status"] ?? 127).".";
        return (new ErrorResponse("IdCardInvalidConfiguration", $details));
    }

    $resolved = trim((string)($process["stdout"] ?? ""));
    if ($resolved == "")
        return (new ErrorResponse("IdCardInvalidConfiguration", "mergeconf n'a produit aucune configuration résolue."));

    $config = id_card_parse_configuration_text($resolved);
    if (!is_array($config) || !isset($config["Card"]) || !is_array($config["Card"]))
        return (new ErrorResponse("IdCardInvalidConfiguration", "La configuration résolue ne contient pas de scope Card exploitable."));
    return (new ValueResponse($config));
}

function id_card_array_get($array, array $path, $default = NULL)
{
    $current = $array;
    foreach ($path as $segment)
    {
        if (!is_array($current) || !array_key_exists($segment, $current))
            return ($default);
        $current = $current[$segment];
    }
    return ($current);
}

function id_card_int($value, $default)
{
    if (is_int($value))
        return ($value);
    if (is_numeric($value))
        return ((int)round((float)$value));
    return ((int)$default);
}

function id_card_bool($value, $default = false)
{
    if (is_bool($value))
        return ($value);
    if (is_numeric($value))
        return ((bool)$value);
    $value = strtolower(trim((string)$value));
    if (in_array($value, ["1", "true", "yes", "oui"], true))
        return (true);
    if (in_array($value, ["0", "false", "no", "non"], true))
        return (false);
    return ((bool)$default);
}

function id_card_color_rgba($value, array $default)
{
    if (is_string($value))
    {
        $value = trim($value);
        if (preg_match('/^#([0-9a-f]{6}|[0-9a-f]{8})$/i', $value, $m))
        {
            $hex = strtolower($m[1]);
            if (strlen($hex) === 6)
                return ([hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), 255]);
            return ([hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), hexdec(substr($hex, 6, 2))]);
        }
    }
    if (is_array($value))
    {
        $flat = array_values($value);
        if (count($flat) >= 3)
            return ([
                max(0, min(255, (int)$flat[0])),
                max(0, min(255, (int)$flat[1])),
                max(0, min(255, (int)$flat[2])),
                max(0, min(255, (int)($flat[3] ?? 255))),
            ]);
    }
    return ($default);
}

function id_card_gd_color($image, array $rgba)
{
    $alpha = 127 - (int)round(($rgba[3] / 255) * 127);
    return (imagecolorallocatealpha($image, $rgba[0], $rgba[1], $rgba[2], max(0, min(127, $alpha))));
}

function id_card_image_from_file($path)
{
    if (!is_file((string)$path) || !is_readable((string)$path))
        return (NULL);
    $type = @exif_imagetype((string)$path);
    switch ($type)
    {
    case IMAGETYPE_PNG:
        return (@imagecreatefrompng((string)$path) ?: NULL);
    case IMAGETYPE_JPEG:
        return (@imagecreatefromjpeg((string)$path) ?: NULL);
    case IMAGETYPE_WEBP:
        return (function_exists("imagecreatefromwebp") ? (@imagecreatefromwebp((string)$path) ?: NULL) : NULL);
    default:
        $raw = @file_get_contents((string)$path);
        return (is_string($raw) ? (@imagecreatefromstring($raw) ?: NULL) : NULL);
    }
}

function id_card_create_canvas($width, $height, array $background, $transparent = false)
{
    $image = imagecreatetruecolor((int)$width, (int)$height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    if ($transparent)
        $fill = imagecolorallocatealpha($image, 0, 0, 0, 127);
    else
        $fill = id_card_gd_color($image, $background);
    imagefilledrectangle($image, 0, 0, (int)$width, (int)$height, $fill);
    imagealphablending($image, true);
    return ($image);
}

function id_card_render_cover($dest, $src, $dx, $dy, $dw, $dh)
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw <= 0 || $sh <= 0 || $dw <= 0 || $dh <= 0)
        return (false);
    $src_ratio = $sw / $sh;
    $dst_ratio = $dw / $dh;
    if ($src_ratio > $dst_ratio)
    {
        $crop_h = $sh;
        $crop_w = (int)round($sh * $dst_ratio);
    }
    else
    {
        $crop_w = $sw;
        $crop_h = (int)round($sw / $dst_ratio);
    }
    $sx = (int)max(0, floor(($sw - $crop_w) / 2));
    $sy = (int)max(0, floor(($sh - $crop_h) / 2));
    return (imagecopyresampled($dest, $src, (int)$dx, (int)$dy, $sx, $sy, (int)$dw, (int)$dh, $crop_w, $crop_h));
}

function id_card_apply_rounded_mask($image, $radius)
{
    $radius = (int)$radius;
    if ($radius <= 0)
        return ;
    $w = imagesx($image);
    $h = imagesy($image);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagealphablending($image, false);
    for ($y = 0; $y < $h; ++$y)
    {
        for ($x = 0; $x < $w; ++$x)
        {
            $outside = false;
            if ($x < $radius && $y < $radius)
                $outside = (($x - $radius) * ($x - $radius) + ($y - $radius) * ($y - $radius) > $radius * $radius);
            else if ($x >= $w - $radius && $y < $radius)
                $outside = (($x - ($w - $radius - 1)) * ($x - ($w - $radius - 1)) + ($y - $radius) * ($y - $radius) > $radius * $radius);
            else if ($x < $radius && $y >= $h - $radius)
                $outside = (($x - $radius) * ($x - $radius) + ($y - ($h - $radius - 1)) * ($y - ($h - $radius - 1)) > $radius * $radius);
            else if ($x >= $w - $radius && $y >= $h - $radius)
                $outside = (($x - ($w - $radius - 1)) * ($x - ($w - $radius - 1)) + ($y - ($h - $radius - 1)) * ($y - ($h - $radius - 1)) > $radius * $radius);
            if ($outside)
                imagesetpixel($image, $x, $y, $transparent);
        }
    }
    imagealphablending($image, true);
}

function id_card_place_rounded_image($dest, $source_path, $x, $y, $width, $height, $radius = 0, $background = NULL)
{
    $src = id_card_image_from_file($source_path);
    if (!$src)
        return (false);
    if ($background === NULL)
        $tmp = id_card_create_canvas($width, $height, [0, 0, 0, 0], true);
    else
        $tmp = id_card_create_canvas(
            $width,
            $height,
            id_card_color_rgba($background, [255, 255, 255, 255]),
            false
        );
    id_card_render_cover($tmp, $src, 0, 0, $width, $height);
    if ((int)$radius > 0)
        id_card_apply_rounded_mask($tmp, $radius);
    imagecopy($dest, $tmp, (int)$x, (int)$y, 0, 0, (int)$width, (int)$height);
    imagedestroy($tmp);
    imagedestroy($src);
    return (true);
}

function id_card_place_image_fit($dest, $source_path, $x, $y, $width, $height)
{
    $src = id_card_image_from_file($source_path);
    if (!$src)
        return (false);
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw <= 0 || $sh <= 0)
    {
        imagedestroy($src);
        return (false);
    }
    $ratio = min($width / $sw, $height / $sh);
    $dw = (int)max(1, round($sw * $ratio));
    $dh = (int)max(1, round($sh * $ratio));
    $dx = (int)round($x + ($width - $dw) / 2);
    $dy = (int)round($y + ($height - $dh) / 2);
    imagecopyresampled($dest, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);
    return (true);
}

function id_card_default_font_path($configured = "")
{
    $candidates = [];
    if (trim((string)$configured) != "")
        $candidates[] = id_card_absolute_path($configured);
    $candidates = array_merge($candidates, [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed.ttf",
        "/usr/share/fonts/opentype/dejavu/DejaVuSans.ttf",
    ]);
    foreach ($candidates as $candidate)
        if (is_file($candidate) && is_readable($candidate))
            return ($candidate);
    return ("");
}

function id_card_text_width($font, $size, $text)
{
    if ($font == "" || !function_exists("imagettfbbox"))
        return (strlen((string)$text) * max(8, (int)$size));
    $box = imagettfbbox((float)$size, 0, $font, (string)$text);
    if (!is_array($box))
        return (0);
    return ((int)abs($box[2] - $box[0]));
}

function id_card_wrap_text($font, $size, $text, $max_width)
{
    $paragraphs = preg_split('/\R/u', (string)$text);
    $out = [];
    foreach ($paragraphs as $paragraph)
    {
        $paragraph = trim((string)$paragraph);
        if ($paragraph === "")
        {
            $out[] = "";
            continue ;
        }
        $words = preg_split('/\s+/u', $paragraph);
        $line = "";
        foreach ($words as $word)
        {
            $candidate = $line === "" ? $word : ($line." ".$word);
            if ($line !== "" && id_card_text_width($font, $size, $candidate) > $max_width)
            {
                $out[] = $line;
                $line = $word;
            }
            else
                $line = $candidate;
        }
        if ($line !== "")
            $out[] = $line;
    }
    return ($out);
}

function id_card_draw_text_lines($image, array $lines, $font, $size, array $rgba, $x, $y, $line_height, $max_width, $max_lines = 0)
{
    $color = id_card_gd_color($image, $rgba);
    $drawn = 0;
    foreach ($lines as $line)
    {
        $wrapped = id_card_wrap_text($font, $size, (string)$line, $max_width);
        foreach ($wrapped as $subline)
        {
            if ($max_lines > 0 && $drawn >= $max_lines)
                return ($y + $drawn * $line_height);
            if ($font != "" && function_exists("imagettftext"))
            {
                $baseline = (int)round($y + ($drawn + 1) * $line_height - max(4, $size * 0.18));
                imagettftext($image, (float)$size, 0, (int)$x, $baseline, $color, $font, (string)$subline);
            }
            else
                imagestring($image, 5, (int)$x, (int)($y + $drawn * $line_height), (string)$subline, $color);
            ++$drawn;
        }
    }
    return ($y + $drawn * $line_height);
}

function id_card_draw_configured_text($image, array $card, $section, $value, $default_font, array $default_color, array $defaults = [], $render_if_missing = false)
{
    $definition = id_card_array_get($card, [$section], []);
    if (!is_array($definition) || !count($definition))
    {
        if (!$render_if_missing)
            return (false);
        $definition = [];
    }
    if (!id_card_bool($definition["Enabled"] ?? true, true))
        return (true);

    $value = trim((string)$value);
    if ($value == "")
        return (true);
    $prefix = (string)($definition["Prefix"] ?? ($defaults["Prefix"] ?? ""));
    $suffix = (string)($definition["Suffix"] ?? ($defaults["Suffix"] ?? ""));

    // Compatibility with the first separate-field examples: those strings were
    // presentation labels, not student/school data. Keep custom prefixes intact,
    // but transparently migrate these two legacy values to the new wording.
    if ($section === "SchoolYear" && in_array(trim($prefix), ["Année scolaire :", "Année scolaire:"], true))
        $prefix = "";

    $value = $prefix.$value.$suffix;

    $font_size = max(8, id_card_int($definition["FontSize"] ?? ($defaults["FontSize"] ?? 24), 24));
    $line_height = max($font_size, id_card_int($definition["LineHeight"] ?? ($defaults["LineHeight"] ?? ($font_size + 6)), $font_size + 6));
    $width = max(20, id_card_int($definition["Width"] ?? ($defaults["Width"] ?? 300), 300));
    $height = max($line_height, id_card_int($definition["Height"] ?? ($defaults["Height"] ?? $line_height), $line_height));
    $max_lines = id_card_int($definition["MaxLines"] ?? 0, 0);
    if ($max_lines <= 0)
        $max_lines = max(1, (int)floor($height / $line_height));

    $font_file = trim((string)($definition["FontFile"] ?? ""));
    $font = $font_file == "" ? $default_font : id_card_default_font_path($font_file);
    $color = array_key_exists("Color", $definition)
        ? id_card_color_rgba($definition["Color"], $default_color)
        : $default_color;

    id_card_draw_text_lines(
        $image,
        [$value],
        $font,
        $font_size,
        $color,
        id_card_int($definition["X"] ?? ($defaults["X"] ?? 0), 0),
        id_card_int($definition["Y"] ?? ($defaults["Y"] ?? 0), 0),
        $line_height,
        $width,
        $max_lines
    );
    return (true);
}

function id_card_render_card_image(array $config, array $data)
{
    $card = id_card_array_get($config, ["Card"], []);
    $width = max(200, id_card_int($card["Width"] ?? 1014, 1014));
    $height = max(120, id_card_int($card["Height"] ?? 638, 638));
    $background_rgba = id_card_color_rgba($card["BackgroundColor"] ?? "#101010", [16, 16, 16, 255]);
    $image = id_card_create_canvas($width, $height, $background_rgba, false);

    $background = trim((string)($card["Background"] ?? ""));
    if ($background != "")
    {
        $background_image = id_card_image_from_file(id_card_absolute_path($background));
        if ($background_image)
        {
            id_card_render_cover($image, $background_image, 0, 0, $width, $height);
            imagedestroy($background_image);
        }
    }

    $student_photo = id_card_array_get($card, ["StudentPhoto"], []);
    id_card_place_rounded_image(
        $image,
        $data["photo_path"] ?? "",
        id_card_int($student_photo["X"] ?? 58, 58),
        id_card_int($student_photo["Y"] ?? 174, 174),
        max(20, id_card_int($student_photo["Width"] ?? 182, 182)),
        max(20, id_card_int($student_photo["Height"] ?? 222, 222)),
        max(0, id_card_int($student_photo["Radius"] ?? 16, 16)),
        $data["photo_background"] ?? NULL
    );

    $logo = id_card_array_get($card, ["SchoolLogo"], []);
    // A Path explicitly configured in the Dabsic must win over the automatic
    // school logo supplied by Infosphere. The latter is only a fallback.
    $logo_path = trim((string)($logo["Path"] ?? ""));
    if ($logo_path == "")
        $logo_path = trim((string)($data["logo_path"] ?? ""));
    if ($logo_path != "")
        id_card_place_image_fit(
            $image,
            id_card_absolute_path($logo_path),
            id_card_int($logo["X"] ?? 58, 58),
            id_card_int($logo["Y"] ?? 58, 58),
            max(20, id_card_int($logo["Width"] ?? 182, 182)),
            max(20, id_card_int($logo["Height"] ?? 86, 86))
        );

    $font = id_card_default_font_path($card["FontFile"] ?? "");
    $text_rgba = id_card_color_rgba($card["TextColor"] ?? "#FFFFFF", [255, 255, 255, 255]);

    // Text zones are deliberately independent. This avoids coupling school-year
    // or course information to the school's postal address and also makes every
    // zone movable on its own in the Dabsic configuration.
    $title_definition = id_card_array_get($card, ["StudentCardTitle"], []);
    $title_text = trim((string)($title_definition["Text"] ?? "CARTE D'ÉTUDIANT"));
    id_card_draw_configured_text($image, $card, "StudentCardTitle", $title_text, $font, $text_rgba, [
        "X" => 275, "Y" => 92, "Width" => 680, "Height" => 46, "FontSize" => 30, "LineHeight" => 38,
    ], true);

    // Name used to be called [Name] in the first format. Preserve its geometry
    // while moving all other legacy [Details] content to separate default zones.
    if (!isset($card["StudentName"]) && isset($card["Name"]) && is_array($card["Name"]))
        $card["StudentName"] = $card["Name"];

    id_card_draw_configured_text($image, $card, "StudentName", $data["full_name"] ?? "", $font, $text_rgba, [
        "X" => 275, "Y" => 176, "Width" => 680, "Height" => 54, "FontSize" => 40, "LineHeight" => 48,
    ], true);
    id_card_draw_configured_text($image, $card, "SchoolYear", $data["school_year"] ?? "", $font, $text_rgba, [
        "X" => 275, "Y" => 246, "Width" => 680, "Height" => 36, "FontSize" => 26, "LineHeight" => 32,
    ], true);
    id_card_draw_configured_text($image, $card, "Course", $data["course"] ?? "", $font, $text_rgba, [
        "X" => 275, "Y" => 291, "Width" => 680, "Height" => 70, "FontSize" => 26, "LineHeight" => 32,
    ], true);
    id_card_draw_configured_text($image, $card, "SchoolName", $data["school_name"] ?? "", $font, $text_rgba, [
        "X" => 275, "Y" => 382, "Width" => 680, "Height" => 36, "FontSize" => 25, "LineHeight" => 31,
    ], true);
    id_card_draw_configured_text($image, $card, "SchoolAddress", $data["school_address"] ?? "", $font, $text_rgba, [
        "X" => 275, "Y" => 426, "Width" => 680, "Height" => 62, "FontSize" => 22, "LineHeight" => 28,
    ], true);
    id_card_draw_configured_text($image, $card, "SchoolCity", $data["school_city"] ?? "", $font, $text_rgba, [
        "X" => 275, "Y" => 497, "Width" => 680, "Height" => 34, "FontSize" => 22, "LineHeight" => 28,
    ], true);

    return ($image);
}

function id_card_save_png_resource($image, $output)
{
    if (($ret = new_directory($output))->is_error())
        return ($ret);
    imagesavealpha($image, true);
    if (!@imagepng($image, $output, 6))
        return (new ErrorResponse("IdCardCannotWrite"));
    @chmod($output, 0640);
    return (new Response);
}

function id_card_render_single_file($config_file, $data_file, $output)
{
    if (!function_exists("imagecreatetruecolor"))
        return (new ErrorResponse("IdCardGenerationFailed", "L'extension GD de PHP est requise."));
    $config = id_card_resolve_configuration_file($config_file);
    if ($config->is_error())
        return ($config);
    $data = id_card_load_json_file($data_file);
    if (!is_array($data))
        return (new ErrorResponse("InvalidParameter", "data"));
    $image = id_card_render_card_image($config->value, $data);
    $ret = id_card_save_png_resource($image, $output);
    imagedestroy($image);
    return ($ret);
}

function id_card_parse_skip_slots($raw)
{
    $out = [];
    foreach (preg_split('/\s*,\s*/', trim((string)$raw)) as $part)
    {
        if ($part === "")
            continue ;
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m))
        {
            $start = (int)$m[1];
            $end = (int)$m[2];
            if ($end < $start)
            {
                $tmp = $start;
                $start = $end;
                $end = $tmp;
            }
            for ($i = $start; $i <= $end; ++$i)
                $out[$i] = true;
        }
        else if (preg_match('/^\d+$/', $part))
            $out[(int)$part] = true;
    }
    ksort($out);
    return (array_keys($out));
}

function id_card_sheet_layout(array $config)
{
    $card = id_card_array_get($config, ["Card"], []);
    $sheet = id_card_array_get($card, ["Sheet"], []);
    $card_width = max(200, id_card_int($card["Width"] ?? 990, 990));
    $card_height = max(120, id_card_int($card["Height"] ?? 600, 600));
    $columns = max(1, id_card_int($sheet["Columns"] ?? 2, 2));
    $rows = max(1, id_card_int($sheet["Rows"] ?? 5, 5));
    $margin_left = max(0, id_card_int($sheet["MarginLeft"] ?? 220, 220));
    $margin_top = max(0, id_card_int($sheet["MarginTop"] ?? 254, 254));
    $gap_x = max(0, id_card_int($sheet["GapX"] ?? 60, 60));
    $gap_y = max(0, id_card_int($sheet["GapY"] ?? 0, 0));
    return ([
        "card_width" => $card_width,
        "card_height" => $card_height,
        "columns" => $columns,
        "rows" => $rows,
        "capacity" => $columns * $rows,
        "margin_left" => $margin_left,
        "margin_top" => $margin_top,
        "gap_x" => $gap_x,
        "gap_y" => $gap_y,
        "width" => $margin_left * 2 + $columns * $card_width + ($columns - 1) * $gap_x,
        "height" => $margin_top * 2 + $rows * $card_height + ($rows - 1) * $gap_y,
        "background" => id_card_color_rgba($sheet["BackgroundColor"] ?? "#FFFFFF", [255, 255, 255, 255]),
    ]);
}

function id_card_school_sheet_layout(array $school)
{
    $configuration = id_card_school_configuration_path($school, true);
    if (!is_readable($configuration))
        return (NULL);
    $resolved = id_card_resolve_configuration_file($configuration);
    if ($resolved->is_error() || !is_array($resolved->value))
        return (NULL);
    return (id_card_sheet_layout($resolved->value));
}

function id_card_generate_school_sheet(array $school, array $student_ids, $skip_raw = "", $output_mode = "print", array $study_years = [])
{
    if (!function_exists("imagecreatetruecolor") || !function_exists("imagepng"))
        return (new ErrorResponse("IdCardGenerationFailed", "L'extension GD de PHP est requise."));
    $status = id_card_school_status($school);
    if (!$status["ready"])
        return (new ErrorResponse("IdCardSchoolNotReady"));

    $layout = id_card_school_sheet_layout($school);
    if (!is_array($layout))
        return (new ErrorResponse("IdCardInvalidConfiguration"));

    $skip = array_values(array_filter(id_card_parse_skip_slots($skip_raw), function ($slot) use ($layout) {
        return ($slot >= 1 && $slot <= $layout["capacity"]);
    }));
    $free_slots = $layout["capacity"] - count($skip);

    $unique = [];
    foreach ($student_ids as $student_id)
    {
        $student_id = (int)$student_id;
        if ($student_id > 0)
            $unique[$student_id] = true;
    }
    $student_ids = array_keys($unique);
    if (!count($student_ids))
        return (new ErrorResponse("IdCardSheetNoStudents"));
    if (count($student_ids) > $free_slots)
        return (new ErrorResponse("IdCardSheetTooManyStudents"));

    $cards = [];
    foreach ($student_ids as $student_id)
    {
        $study_year = array_key_exists($student_id, $study_years)
            ? $study_years[$student_id]
            : (array_key_exists((string)$student_id, $study_years) ? $study_years[(string)$student_id] : NULL);
        $payload = id_card_payload_for_user($student_id, (int)$school["id"], $study_year);
        if ($payload->is_error())
            return ($payload);
        $cards[] = $payload->value["card"];
    }

    $data_file = id_card_generate_payload_file($cards, "infosphere_id_card_sheet_data_");
    if ($data_file->is_error())
        return ($data_file);
    $temporary = tempnam(sys_get_temp_dir(), "infosphere_id_card_sheet_");
    if ($temporary === false)
    {
        @unlink($data_file->value);
        return (new ErrorResponse("IdCardCannotWrite"));
    }
    @unlink($temporary);
    $temporary .= ".png";

    $render = id_card_render_sheet_file(
        id_card_school_configuration_path($school, true),
        $data_file->value,
        $temporary,
        implode(",", $skip)
    );
    @unlink($data_file->value);
    if ($render->is_error())
    {
        @unlink($temporary);
        return ($render);
    }
    $content = @file_get_contents($temporary);
    @unlink($temporary);
    if (!is_string($content) || $content === "")
        return (new ErrorResponse("IdCardGenerationFailed"));

    $codename = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($school["codename"] ?? "school"));
    $filename = "planche-cartes-".$codename."-".id_card_school_year_string().".png";
    if ((string)$output_mode === "download")
    {
        return (new ValueResponse([
            "filename" => $filename,
            "content_type" => "image/png",
            "disposition" => "attachment",
            "content" => $content,
        ]));
    }

    $encoded = base64_encode($content);
    $title = htmlspecialchars(pathinfo($filename, PATHINFO_FILENAME), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $html = '<!doctype html><html><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>'.$title.'</title><style>'
        .'@page{size:A4 portrait;margin:0;}'
        .'html,body{margin:0;padding:0;background:#222;}'
        .'body{min-height:100vh;display:flex;justify-content:center;align-items:flex-start;}'
        .'.id-card-sheet{display:block;width:210mm;height:297mm;margin:12px auto;box-shadow:0 0 18px rgba(0,0,0,.55);}'
        .'@media print{html,body{width:210mm;height:297mm;background:#fff;min-height:0;display:block;}'
        .'.id-card-sheet{width:210mm;height:297mm;margin:0;box-shadow:none;page-break-after:avoid;}}'
        .'</style></head><body>'
        .'<img class="id-card-sheet" alt="Student card sheet" src="data:image/png;base64,'.$encoded.'">'
        .'</body></html>';
    return (new ValueResponse([
        "filename" => pathinfo($filename, PATHINFO_FILENAME).".html",
        "content_type" => "text/html; charset=UTF-8",
        "disposition" => "inline",
        "content" => $html,
    ]));
}

function id_card_render_sheet_file($config_file, $data_file, $output, $skip_raw = "")
{
    if (!function_exists("imagecreatetruecolor"))
        return (new ErrorResponse("IdCardGenerationFailed", "L'extension GD de PHP est requise."));
    $config = id_card_resolve_configuration_file($config_file);
    if ($config->is_error())
        return ($config);
    $list = id_card_load_json_file($data_file);
    if (!is_array($list))
        return (new ErrorResponse("InvalidParameter", "data"));
    $layout = id_card_sheet_layout($config->value);
    $card_width = $layout["card_width"];
    $card_height = $layout["card_height"];
    $columns = $layout["columns"];
    $rows = $layout["rows"];
    $margin_left = $layout["margin_left"];
    $margin_top = $layout["margin_top"];
    $gap_x = $layout["gap_x"];
    $gap_y = $layout["gap_y"];
    $image = id_card_create_canvas($layout["width"], $layout["height"], $layout["background"], false);

    $skip = array_flip(id_card_parse_skip_slots($skip_raw));
    $capacity = $layout["capacity"];
    $slot = 1;
    $index = 0;
    while ($slot <= $capacity && $index < count($list))
    {
        if (isset($skip[$slot]))
        {
            ++$slot;
            continue ;
        }
        $item = $list[$index];
        if (!is_array($item))
        {
            ++$index;
            continue ;
        }
        $card_image = id_card_render_card_image($config->value, $item);
        $col = ($slot - 1) % $columns;
        $row = (int)floor(($slot - 1) / $columns);
        $x = $margin_left + $col * ($card_width + $gap_x);
        $y = $margin_top + $row * ($card_height + $gap_y);
        imagecopy($image, $card_image, $x, $y, 0, 0, $card_width, $card_height);
        imagedestroy($card_image);
        ++$slot;
        ++$index;
    }
    if ($index < count($list))
    {
        imagedestroy($image);
        return (new ErrorResponse("IdCardGenerationFailed", "La planche ne contient pas assez d'emplacements libres pour toutes les cartes demandées."));
    }
    $ret = id_card_save_png_resource($image, $output);
    imagedestroy($image);
    return ($ret);
}
