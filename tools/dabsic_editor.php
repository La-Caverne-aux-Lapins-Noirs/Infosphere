<?php

function dabsic_editor_register_access_resolver($resolver)
{
    if (!is_string($resolver) || $resolver == "")
        return ;
    if (!isset($GLOBALS["DabsicEditorAccessResolvers"]) || !is_array($GLOBALS["DabsicEditorAccessResolvers"]))
        $GLOBALS["DabsicEditorAccessResolvers"] = [];
    if (!in_array($resolver, $GLOBALS["DabsicEditorAccessResolvers"], true))
        $GLOBALS["DabsicEditorAccessResolvers"][] = $resolver;
}

function dabsic_editor_user_can_access($requested, $for_write = false)
{
    if (is_admin())
        return (true);
    foreach ((array)($GLOBALS["DabsicEditorAccessResolvers"] ?? []) as $resolver)
        if (is_callable($resolver) && $resolver($requested, (bool)$for_write))
            return (true);
    return (false);
}

function dabsic_editor_project_root()
{
    static $root = NULL;

    if ($root === NULL)
        $root = realpath(__DIR__."/..");
    return ($root);
}

function dabsic_editor_add_allowed_root(&$roots, $path, $project_root)
{
    if (!is_string($path) || trim($path) === "")
        return ;

    $path = str_replace("\\", DIRECTORY_SEPARATOR, trim($path));
    if ($path[0] !== DIRECTORY_SEPARATOR)
        $path = rtrim($project_root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$path;
    $resolved = realpath($path);
    if ($resolved !== false && is_dir($resolved) && !in_array($resolved, $roots, true))
        $roots[] = $resolved;
}

function dabsic_editor_allowed_roots()
{
    static $roots = NULL;

    if ($roots !== NULL)
        return ($roots);
    $roots = [];
    $project_root = dabsic_editor_project_root();
    if ($project_root === false)
        return ($roots);

    $roots[] = $project_root;

    // dres may itself be a symlink/mount, and some deployments also mount
    // individual managed resource directories (medals, documents, users...)
    // outside dres.  Those locations are legitimate editor roots because
    // Infosphere explicitly exposes them through its Configuration object.
    dabsic_editor_add_allowed_root($roots, "dres", $project_root);

    $configuration = $GLOBALS["Configuration"] ?? NULL;
    if (is_object($configuration))
    {
        foreach ([
            "_MedalsDir",
            "_DocDir",
            "_ActivitiesDir",
            "_SchoolsDir",
            "_OrganizationsDir",
            "_RoomsDir",
            "_GroupsDir",
            "_SupportDir",
            "_UsersDir",
            "_ConfigurationDir",
            "_RobotDir",
            "_QuizDir"
        ] as $property)
            if (isset($configuration->$property))
                dabsic_editor_add_allowed_root($roots, $configuration->$property, $project_root);
    }
    return ($roots);
}

function dabsic_editor_path_is_inside($path, $root)
{
    $root = rtrim($root, DIRECTORY_SEPARATOR);
    return ($path === $root || strncmp($path, $root.DIRECTORY_SEPARATOR, strlen($root) + 1) === 0);
}

function dabsic_editor_normalize_requested_path($requested)
{
    if (!is_string($requested) && !is_numeric($requested))
        return (NULL);
    $requested = trim(str_replace("\\", "/", (string)$requested));
    if ($requested === "" || strpos($requested, "\0") !== false || substr($requested, 0, 1) === "/")
        return (NULL);
    while (substr($requested, 0, 2) === "./")
        $requested = substr($requested, 2);
    if ($requested === "")
        return (NULL);
    foreach (explode("/", $requested) as $part)
        if ($part === "" || $part === "." || $part === "..")
            return (NULL);
    return ($requested);
}

function dabsic_editor_editable_extensions()
{
    return (["dab", "json", "xml", "txt"]);
}

function dabsic_editor_reference_from_path($path)
{
    if (!is_string($path) && !is_numeric($path))
        return (NULL);

    $path = str_replace("\\", "/", trim((string)$path));
    $project_root = dabsic_editor_project_root();
    if ($project_root === false)
        return (NULL);

    // Prefer the path as it is exposed by Infosphere. This is important when
    // dres (or one of its children) is a symlink/mount outside the project:
    // realpath() would otherwise lose the project-visible alias.
    if ($path !== "" && substr($path, 0, 1) !== "/")
    {
        $candidate = dabsic_editor_normalize_requested_path($path);
        if ($candidate !== NULL)
        {
            $absolute = realpath($project_root.DIRECTORY_SEPARATOR.$candidate);
            if ($absolute !== false && is_file($absolute))
            {
                foreach (dabsic_editor_allowed_roots() as $root)
                    if (dabsic_editor_path_is_inside($absolute, $root))
                        return ($candidate);
            }
        }
    }

    // Also accept an absolute filesystem path when it really points inside
    // Infosphere itself. Never expose or accept an arbitrary absolute path.
    $absolute = realpath($path);
    if ($absolute === false || !is_file($absolute) ||
        !dabsic_editor_path_is_inside($absolute, $project_root))
        return (NULL);

    $relative = substr($absolute, strlen(rtrim($project_root, DIRECTORY_SEPARATOR)) + 1);
    return (dabsic_editor_normalize_requested_path($relative));
}

function dabsic_editor_resolve_file($requested, $for_write = false, $extensions = ["dab"])
{
    $relative = dabsic_editor_normalize_requested_path($requested);
    if ($relative === NULL)
        return (["ok" => false, "error" => "DabsicEditorInvalidPath"]);

    $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    if (!in_array($extension, $extensions, true))
        return (["ok" => false, "error" => "DabsicEditorInvalidPath"]);

    $project_root = dabsic_editor_project_root();
    $absolute = realpath($project_root.DIRECTORY_SEPARATOR.$relative);
    if ($absolute === false || !is_file($absolute))
        return (["ok" => false, "error" => "DabsicEditorMissingFile"]);

    $authorized = false;
    foreach (dabsic_editor_allowed_roots() as $root)
        if (dabsic_editor_path_is_inside($absolute, $root))
        {
            $authorized = true;
            break ;
        }
    if (!$authorized)
        return (["ok" => false, "error" => "DabsicEditorInvalidPath"]);
    if (function_exists("user_storage_can_access_absolute")
        && !user_storage_can_access_absolute($absolute, $for_write))
        return (["ok" => false, "error" => "DabsicEditorInvalidPath"]);
    if (!is_readable($absolute))
        return (["ok" => false, "error" => "DabsicEditorCannotRead"]);
    if ($for_write && (!is_writable($absolute) || !is_writable(dirname($absolute))))
        return (["ok" => false, "error" => "DabsicEditorReadOnly"]);

    return ([
        "ok" => true,
        "relative" => $relative,
        "absolute" => $absolute,
        "extension" => $extension
    ]);
}

function dabsic_editor_url($file)
{
    $reference = dabsic_editor_reference_from_path($file);
    if ($reference === NULL)
        return ("");
    return ("index.php?p=DabsicEditorMenu&file=".rawurlencode($reference));
}

function dabsic_editor_clean_validation_error($stderr, $status)
{
    $details = trim((string)$stderr);
    if ($details === "")
        $details = "mergeconf a terminé avec le code ".(int)$status." sans fournir de diagnostic sur stderr.";
    $details = str_replace(["\r\n", "\r"], "\n", $details);
    if (strlen($details) > 20000)
        $details = substr($details, 0, 20000)."\n…";
    return ($details);
}

function dabsic_editor_write_stream($stream, $content)
{
    $offset = 0;
    $length = strlen($content);

    while ($offset < $length)
    {
        $written = fwrite($stream, substr($content, $offset));
        if ($written === false || $written === 0)
            return (false);
        $offset += $written;
    }
    return (true);
}

function dabsic_editor_validate_content($content)
{
    $stdout_file = tempnam(sys_get_temp_dir(), "infosphere_dabsic_stdout_");
    $stderr_file = tempnam(sys_get_temp_dir(), "infosphere_dabsic_stderr_");
    if ($stdout_file === false || $stderr_file === false)
    {
        if ($stdout_file !== false)
            @unlink($stdout_file);
        if ($stderr_file !== false)
            @unlink($stderr_file);
        return ([
            "ok" => false,
            "error" => "DabsicEditorMergeconfUnavailable",
            "details" => "Impossible de créer les fichiers temporaires nécessaires à mergeconf."
        ]);
    }

    $process = proc_open(
        "mergeconf -if .dabsic -of .dabsic",
        [
            0 => ["pipe", "r"],
            1 => ["file", $stdout_file, "w"],
            2 => ["file", $stderr_file, "w"]
        ],
        $pipes
    );
    if (!is_resource($process))
    {
        @unlink($stdout_file);
        @unlink($stderr_file);
        return ([
            "ok" => false,
            "error" => "DabsicEditorMergeconfUnavailable",
            "details" => "Impossible de lancer mergeconf."
        ]);
    }

    $written = dabsic_editor_write_stream($pipes[0], $content);
    fclose($pipes[0]);
    $status = proc_close($process);
    $stdout = file_get_contents($stdout_file);
    $stderr = file_get_contents($stderr_file);
    @unlink($stdout_file);
    @unlink($stderr_file);
    if ($stdout === false)
        $stdout = "";
    if ($stderr === false)
        $stderr = "";

    if (!$written || $status === 126 || $status === 127)
        return ([
            "ok" => false,
            "error" => "DabsicEditorMergeconfUnavailable",
            "details" => dabsic_editor_clean_validation_error($stderr, $status)
        ]);

    // Ask mergeconf to parse Dabsic and emit Dabsic again. This validates the
    // source grammar without resolving variables and without introducing JSON
    // constraints that do not apply to unresolved Dabsic references.
    if ($status !== 0)
        return ([
            "ok" => false,
            "error" => "DabsicEditorSyntaxError",
            "details" => dabsic_editor_clean_validation_error($stderr, $status)
        ]);
    return (["ok" => true]);
}

function dabsic_editor_validate_json_content($content)
{
    json_decode((string)$content, true);
    if (json_last_error() === JSON_ERROR_NONE)
        return (["ok" => true]);
    return ([
        "ok" => false,
        "error" => "DabsicEditorJsonSyntaxError",
        "details" => function_exists("json_last_error_msg")
            ? json_last_error_msg()
            : "Erreur JSON ".json_last_error()
    ]);
}

function dabsic_editor_validate_xml_content($content)
{
    // DOM fait partie de php-xml. Si le module n'est pas installé, l'éditeur
    // reste utilisable : on ne transforme pas une capacité d'édition texte
    // en dépendance système supplémentaire.
    if (!class_exists("DOMDocument"))
        return (["ok" => true]);

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $document = new DOMDocument();
    $flags = defined("LIBXML_NONET") ? LIBXML_NONET : 0;
    $valid = $document->loadXML((string)$content, $flags);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($valid)
        return (["ok" => true]);

    $details = [];
    foreach ($errors as $error)
    {
        $message = trim((string)$error->message);
        if ($message === "")
            continue ;
        $details[] = "Ligne ".(int)$error->line.", colonne ".(int)$error->column." : ".$message;
        if (count($details) >= 20)
            break ;
    }
    return ([
        "ok" => false,
        "error" => "DabsicEditorXmlSyntaxError",
        "details" => implode("\n", $details)
    ]);
}

function dabsic_editor_validate_editable_content($content, $extension)
{
    switch (strtolower((string)$extension))
    {
    case "dab":
        return (dabsic_editor_validate_content($content));
    case "json":
        return (dabsic_editor_validate_json_content($content));
    case "xml":
        return (dabsic_editor_validate_xml_content($content));
    case "txt":
        return (["ok" => true]);
    }
    return (["ok" => false, "error" => "DabsicEditorInvalidPath"]);
}

function dabsic_editor_save_file($requested, $content, $expected_hash)
{
    $resolved = dabsic_editor_resolve_file(
        $requested,
        true,
        dabsic_editor_editable_extensions()
    );
    if (!$resolved["ok"])
        return ($resolved);
    if (!is_string($content))
        return (["ok" => false, "error" => "DabsicEditorCannotSave"]);
    if (!is_string($expected_hash) || !preg_match('/^[a-f0-9]{64}$/i', $expected_hash))
        return (["ok" => false, "error" => "DabsicEditorConflict"]);

    $path = $resolved["absolute"];
    $lock_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.
        "infosphere_dabsic_".hash("sha256", $path).".lock";
    $lock = @fopen($lock_path, "c");
    if ($lock === false || !flock($lock, LOCK_EX))
    {
        if (is_resource($lock))
            fclose($lock);
        return (["ok" => false, "error" => "DabsicEditorCannotSave"]);
    }

    $current = file_get_contents($path);
    if ($current === false)
    {
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicEditorCannotRead"]);
    }
    if (!hash_equals(strtolower($expected_hash), hash("sha256", $current)))
    {
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicEditorConflict"]);
    }

    $validation = dabsic_editor_validate_editable_content(
        $content,
        $resolved["extension"]
    );
    if (!$validation["ok"])
    {
        flock($lock, LOCK_UN);
        fclose($lock);
        return ($validation);
    }

    $directory = dirname($path);
    $temporary = tempnam($directory, ".dabsic-edit-");
    if ($temporary === false)
    {
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicEditorCannotSave"]);
    }

    $stat = @stat($path);
    $saved = file_put_contents($temporary, $content, LOCK_EX);
    if ($saved === false || $saved !== strlen($content))
    {
        @unlink($temporary);
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicEditorCannotSave"]);
    }
    if (is_array($stat))
    {
        @chmod($temporary, $stat["mode"] & 0777);
        @chown($temporary, $stat["uid"]);
        @chgrp($temporary, $stat["gid"]);
    }

    if (!@rename($temporary, $path))
    {
        @unlink($temporary);
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicEditorCannotSave"]);
    }

    clearstatcache(true, $path);
    $hash = hash("sha256", $content);
    flock($lock, LOCK_UN);
    fclose($lock);

    return ([
        "ok" => true,
        "relative" => $resolved["relative"],
        "extension" => $resolved["extension"],
        "hash" => $hash,
        "size" => strlen($content),
        "mtime" => @filemtime($path)
    ]);
}
