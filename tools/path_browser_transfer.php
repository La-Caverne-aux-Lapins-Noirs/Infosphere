<?php

function path_browser_transfer_normalize_relative($path)
{
    if (!is_string($path) && !is_numeric($path))
        return (NULL);
    $path = trim(str_replace("\\", "/", (string)$path));
    if (strpos($path, "\0") !== false)
        return (NULL);
    $path = ltrim($path, "/");
    $parts = [];
    foreach (explode("/", $path) as $part)
    {
        if ($part === "" || $part === ".")
            continue ;
        if ($part === "..")
            return (NULL);
        $parts[] = $part;
    }
    return (implode("/", $parts));
}

function path_browser_transfer_root_from_target($target, $path)
{
    $target = rtrim(str_replace("\\", "/", (string)$target), "/");
    $path = trim((string)$path);
    $path = trim(str_replace("\\", "/", $path), "/");
    if ($path === "")
        return ($target);

    $suffix = "/".$path;
    if (strlen($target) >= strlen($suffix) && substr($target, -strlen($suffix)) === $suffix)
        return (substr($target, 0, -strlen($suffix)));
    return ($target);
}

function path_browser_transfer_relative_entry($root, $entry)
{
    $root = rtrim(str_replace("\\", "/", (string)$root), "/");
    $entry = str_replace("\\", "/", (string)$entry);
    if ($root !== "" && strncmp($entry, $root."/", strlen($root) + 1) === 0)
        $entry = substr($entry, strlen($root) + 1);
    else if ($entry === $root)
        $entry = "";
    return (path_browser_transfer_normalize_relative($entry));
}

function path_browser_transfer_activity_root($id, $type, $language)
{
    global $Configuration;

    if (!is_assistant_for_activity($id))
        forbidden();
    ($activity = new FullActivity)->build($id);
    $language_dir = $language == "NA" ? "" : $language;
    if ($type === "ressource")
        return ($Configuration->ActivitiesDir($activity->codename, $language_dir)."ressource/");
    if ($type === "mood")
        return ($Configuration->ActivitiesDir($activity->codename, "")."mood/");
    if ($type === "subject")
        return ($Configuration->ActivitiesDir($activity->codename, $language_dir));
    bad_request();
}

function path_browser_transfer_user_context($id, $type)
{
    global $Configuration;

    $id = (int)$id;
    if (($user = db_select_one("codename FROM user WHERE id = $id")) == NULL)
        not_found();
    if ($type === "subscription_file")
    {
        if (!is_director_for_student($id))
            forbidden();
        return ([
            "root" => $Configuration->UsersDir($user["codename"]),
            "prefix" => "admin/subscription",
            "kind" => "subscription"
        ]);
    }
    if ($type === "documentation_file")
    {
        if (!is_director_for_student($id))
            forbidden();
        return ([
            "root" => $Configuration->UsersDir($user["codename"]),
            "prefix" => document_builder_documentation_file_root(),
            "kind" => "documentation"
        ]);
    }
    if ($type === "letter_file")
    {
        if (!is_director_for_student($id))
            forbidden();
        return ([
            "root" => $Configuration->UsersDir($user["codename"]),
            "prefix" => document_builder_letter_file_root(),
            "kind" => "letter"
        ]);
    }
    if ($type !== "file")
        bad_request();
    return ([
        "root" => $Configuration->UsersDir($user["codename"]),
        "prefix" => "",
        "kind" => "user"
    ]);
}

function path_browser_transfer_context($page, $id, $type, $language)
{
    global $Configuration;

    $page = (string)$page;
    $type = (string)$type;
    if ($page === "activity")
        return (["root" => path_browser_transfer_activity_root((int)$id, $type, $language), "prefix" => "", "kind" => "activity"]);
    if ($page === "medal" && $type === "ressource")
    {
        if (!is_teacher())
            forbidden();
        return (["root" => $Configuration->MedalsDir(".ressources"), "prefix" => "", "kind" => "medal"]);
    }
    if ($page === "doc" && $type === "file")
    {
        if (!is_teacher())
            forbidden();
        return (["root" => $Configuration->DocDir(), "prefix" => "", "kind" => "doc"]);
    }
    if ($page === "user")
        return (path_browser_transfer_user_context((int)$id, $type));
    if ($page === "cycle" && $type === "file")
    {
        $id = (int)$id;
        if (!is_director_for_cycle($id))
            forbidden();
        $cycle = db_select_one("codename FROM cycle WHERE id = $id AND deleted IS NULL");
        if ($cycle == NULL || trim((string)($cycle["codename"] ?? "")) == "")
            not_found();
        return ([
            "root" => $Configuration->CyclesDir($cycle["codename"]),
            "prefix" => "",
            "kind" => "cycle"
        ]);
    }
    bad_request();
}

function path_browser_transfer_authorize_user_path($id, $relative, $kind, $prefix, $write = false)
{
    $relative = path_browser_transfer_normalize_relative($relative);
    if ($relative === NULL)
        forbidden();
    if ($kind === "subscription" || $kind === "documentation" || $kind === "letter")
    {
        $prefix = trim((string)$prefix, "/");
        if ($relative !== $prefix && strncmp($relative, $prefix."/", strlen($prefix) + 1) !== 0)
            forbidden();
        return ;
    }

    if ($write)
    {
        if (!user_storage_can_write_path((int)$id, $relative))
            forbidden();
    }
    else if (!user_storage_can_read_path((int)$id, $relative))
        forbidden();
}

function path_browser_transfer_real_root($root)
{
    $root = rtrim((string)$root, "/");
    $real = realpath($root);
    if ($real === false || !is_dir($real) || !is_readable($real))
        not_found();
    return ($real);
}

function path_browser_transfer_resolve_entry($context, $page, $id, $relative)
{
    $relative = path_browser_transfer_normalize_relative($relative);
    if ($relative === NULL || $relative === "")
        bad_request();
    if ($page === "user")
        path_browser_transfer_authorize_user_path($id, $relative, $context["kind"], $context["prefix"]);

    $root = path_browser_transfer_real_root($context["root"]);
    $target = realpath($root.DIRECTORY_SEPARATOR.str_replace("/", DIRECTORY_SEPARATOR, $relative));
    if ($target === false)
        not_found();
    if ($target !== $root && strncmp($target, $root.DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0)
        forbidden();
    if (!is_file($target) && !is_dir($target))
        not_found();
    if (!is_readable($target))
        forbidden();
    return (["absolute" => $target, "relative" => $relative]);
}

function path_browser_transfer_validate_new_name($name)
{
    if (!is_string($name) && !is_numeric($name))
        return (NULL);
    $name = trim((string)$name);
    if ($name === "" || $name === "." || $name === ".." || $name === "index.php")
        return (NULL);
    if (strpos($name, "\0") !== false || strpos($name, "/") !== false || strpos($name, "\\") !== false)
        return (NULL);
    return ($name);
}

function path_browser_transfer_rename($page, $id, $type, $language, $relative, $new_name)
{
    $context = path_browser_transfer_context($page, $id, $type, $language);
    $relative = path_browser_transfer_normalize_relative($relative);
    $new_name = path_browser_transfer_validate_new_name($new_name);
    if ($relative === NULL || $relative === "" || $new_name === NULL)
        return (new ErrorResponse("InvalidParameter", "name"));
    if (basename($relative) === "index.php")
        forbidden();
    if ($page === "user")
    {
        $prefix = trim((string)($context["prefix"] ?? ""), "/");
        if ($prefix !== "" && $relative === $prefix)
            forbidden();
        path_browser_transfer_authorize_user_path(
            $id,
            $relative,
            $context["kind"],
            $context["prefix"],
            true
        );
        if ($context["kind"] === "user" && user_storage_is_root_space($relative))
            forbidden();
    }

    $resolved = path_browser_transfer_resolve_entry($context, $page, $id, $relative);
    $root = path_browser_transfer_real_root($context["root"]);
    $source = $root.DIRECTORY_SEPARATOR.str_replace("/", DIRECTORY_SEPARATOR, $relative);
    if (is_link($source) || realpath($source) !== $resolved["absolute"])
        forbidden();

    $parent = dirname($source);
    $real_parent = realpath($parent);
    if ($real_parent === false || ($real_parent !== $root && strncmp($real_parent, $root.DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0))
        forbidden();
    if (!is_writable($real_parent))
        forbidden();

    $destination = $real_parent.DIRECTORY_SEPARATOR.$new_name;
    if (file_exists($destination) || is_link($destination))
        return (new ErrorResponse("PathBrowserNameAlreadyExists"));
    if (!@rename($source, $destination))
        return (new ErrorResponse("PathBrowserRenameFailed"));

    $parent_relative = dirname($relative);
    $new_relative = ($parent_relative === "." ? "" : $parent_relative."/").$new_name;
    return (new ValueResponse([
        "name" => $new_name,
        "relative" => $new_relative
    ]));
}

function path_browser_transfer_add_directory_to_zip($zip, $absolute, $archive)
{
    $archive = trim(str_replace("\\", "/", $archive), "/");
    if ($archive !== "")
        $zip->addEmptyDir($archive);
    $entries = @scandir($absolute);
    if ($entries === false)
        return (false);
    foreach ($entries as $name)
    {
        if ($name === "." || $name === ".." || $name === "index.php")
            continue ;
        $source = $absolute.DIRECTORY_SEPARATOR.$name;
        if (is_link($source))
            continue ;
        $destination = ($archive === "" ? "" : $archive."/").$name;
        if (is_dir($source))
        {
            if (!path_browser_transfer_add_directory_to_zip($zip, $source, $destination))
                return (false);
        }
        else if (is_file($source) && is_readable($source))
        {
            if (!$zip->addFile($source, $destination))
                return (false);
        }
    }
    return (true);
}

function path_browser_transfer_mime_type($file)
{
    if (function_exists("mime_content_type"))
    {
        $mime = @mime_content_type($file);
        if (is_string($mime) && $mime !== "")
            return ($mime);
    }
    return ("application/octet-stream");
}

function path_browser_transfer_export($page, $id, $type, $language, array $selection)
{
    $context = path_browser_transfer_context($page, $id, $type, $language);
    $selection = array_values(array_unique(array_filter(array_map(function ($entry) {
        return (path_browser_transfer_normalize_relative($entry));
    }, $selection), function ($entry) {
        return ($entry !== NULL && $entry !== "");
    })));
    if (!count($selection) || count($selection) > 256)
        bad_request();

    $resolved = [];
    foreach ($selection as $entry)
        $resolved[] = path_browser_transfer_resolve_entry($context, $page, $id, $entry);

    if (count($resolved) === 1 && is_file($resolved[0]["absolute"]))
    {
        $content = @file_get_contents($resolved[0]["absolute"]);
        if ($content === false)
            return (new ErrorResponse("InvalidFile"));
        return (new ValueResponse([
            "filename" => basename($resolved[0]["absolute"]),
            "content_type" => path_browser_transfer_mime_type($resolved[0]["absolute"]),
            "content" => $content
        ]));
    }

    if (!class_exists("ZipArchive"))
        return (new ErrorResponse("InternalError", "ZipArchive is unavailable."));
    $temporary = tempnam(sys_get_temp_dir(), "infosphere_file_browser_");
    if ($temporary === false)
        return (new ErrorResponse("InternalError"));
    $zip = new ZipArchive;
    if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true)
    {
        @unlink($temporary);
        return (new ErrorResponse("InternalError"));
    }

    $ok = true;
    foreach ($resolved as $entry)
    {
        $base = basename($entry["absolute"]);
        if (is_dir($entry["absolute"]))
            $ok = $ok && path_browser_transfer_add_directory_to_zip($zip, $entry["absolute"], $base);
        else
            $ok = $ok && $zip->addFile($entry["absolute"], $base);
    }
    $zip->close();
    if (!$ok)
    {
        @unlink($temporary);
        return (new ErrorResponse("InternalError"));
    }
    $content = @file_get_contents($temporary);
    @unlink($temporary);
    if ($content === false)
        return (new ErrorResponse("InternalError"));

    $filename = count($resolved) === 1
        ? basename($resolved[0]["absolute"]).".zip"
        : "infosphere-selection.zip";
    return (new ValueResponse([
        "filename" => $filename,
        "content_type" => "application/zip",
        "content" => $content
    ]));
}
