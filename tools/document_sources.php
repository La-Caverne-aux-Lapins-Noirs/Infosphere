<?php

function document_source_roots()
{
    global $Configuration;

    $roots = [
        "dres" => [
            "label" => "Documents personnalisables",
            "root" => $Configuration->DocDir(),
            "writable" => true,
        ],
    ];
    foreach (["res/docs/"] as $root)
    {
        if (!is_dir($root))
            continue ;
        $key = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($root, "/"));
        $roots[$key] = [
            "label" => "Ressources fixes: ".trim($root, "/"),
            "root" => $root,
            "writable" => false,
        ];
    }
    return ($roots);
}

function document_source_normalize_path($path)
{
    $path = resolve_path(str_replace("\\", "/", $path));
    if ($path == ".")
        $path = "";
    return ($path);
}

function document_source_is_hidden_entry($entry)
{
    return ($entry != "" && $entry[0] == ".");
}

function document_source_path_is_visible($path)
{
    foreach (explode("/", document_source_normalize_path($path)) as $entry)
    {
        if (document_source_is_hidden_entry($entry))
            return (false);
    }
    return (true);
}

function _document_source_list_dab($root, $dir = "")
{
    $out = [];
    $dir = document_source_normalize_path($dir);
    $base = rtrim($root, "/").($dir != "" ? "/$dir" : "");
    if (!is_dir($base))
        return ($out);
    foreach (scandir($base) as $entry)
    {
        if ($entry == "." || $entry == ".." || $entry == "index.php" || document_source_is_hidden_entry($entry))
            continue ;
        $path = ($dir != "" ? "$dir/" : "").$entry;
        $full = rtrim($root, "/")."/$path";
        if (is_dir($full))
            $out = array_merge($out, _document_source_list_dab($root, $path));
        else if (pathinfo($full, PATHINFO_EXTENSION) == "dab")
            $out[] = $path;
    }
    sort($out);
    return ($out);
}

function get_document_sources()
{
    $out = [];
    foreach (document_source_roots() as $source => $root)
    {
        $out[$source] = $root;
        $out[$source]["documents"] = _document_source_list_dab($root["root"]);
    }
    return ($out);
}


function document_source_editor_path($source, $path)
{
    $roots = document_source_roots();
    $path = document_source_normalize_path($path);
    if (!isset($roots[$source]) || $path == "" || !document_source_path_is_visible($path))
        return (NULL);

    $project_root = realpath(__DIR__."/..");
    $absolute = realpath(rtrim($roots[$source]["root"], "/")."/".$path);
    if ($project_root === false || $absolute === false || !is_file($absolute))
        return (NULL);

    $project_prefix = rtrim($project_root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    if (strncmp($absolute, $project_prefix, strlen($project_prefix)) === 0)
        return (str_replace(DIRECTORY_SEPARATOR, "/", substr($absolute, strlen($project_prefix))));

    // dres may be a symlink or a mount outside the source tree. The Dabsic
    // editor nevertheless addresses it through the project-relative dres path.
    $dres_root = realpath($project_root.DIRECTORY_SEPARATOR."dres");
    if ($dres_root !== false)
    {
        $dres_prefix = rtrim($dres_root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (strncmp($absolute, $dres_prefix, strlen($dres_prefix)) === 0)
            return ("dres/".str_replace(DIRECTORY_SEPARATOR, "/", substr($absolute, strlen($dres_prefix))));
    }
    return (NULL);
}

function document_reference_from_source_path($source, $path)
{
    return ($source.":".document_source_normalize_path($path));
}

function document_reference_to_path($reference)
{
    $roots = document_source_roots();
    $split = explode(":", $reference, 2);
    if (count($split) != 2)
        bad_request();
    $source = $split[0];
    $path = document_source_normalize_path($split[1]);
    if (!isset($roots[$source]) ||
        $path == "" ||
        pathinfo($path, PATHINFO_EXTENSION) != "dab" ||
        !document_source_path_is_visible($path))
        bad_request();
    $full = rtrim($roots[$source]["root"], "/")."/$path";
    if (!file_exists($full) || !is_file($full))
        bad_request();
    return ($full);
}



function document_reference_from_editor_path($path)
{
    $path = document_source_normalize_path($path);
    if ($path == "" || !document_source_path_is_visible($path))
        return (NULL);

    $project_root = realpath(__DIR__."/..");
    if ($project_root === false)
        return (NULL);
    $absolute = realpath($project_root.DIRECTORY_SEPARATOR.$path);
    if ($absolute === false || !is_file($absolute))
        return (NULL);

    foreach (document_source_roots() as $source => $root)
    {
        $source_root = realpath($root["root"]);
        if ($source_root === false)
            continue ;
        $prefix = rtrim($source_root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (strncmp($absolute, $prefix, strlen($prefix)) !== 0)
            continue ;
        $relative = str_replace(DIRECTORY_SEPARATOR, "/", substr($absolute, strlen($prefix)));
        if ($relative != "" && document_source_path_is_visible($relative))
            return (document_reference_from_source_path($source, $relative));
    }
    return (NULL);
}

function document_model_workflow_mailbox($file)
{
    if (!is_file($file) || !is_readable($file))
        return ("");
    $content = @file_get_contents($file);
    if ($content === false)
        return ("");

    $stack = [];
    foreach (preg_split('/\r\n|\r|\n/', $content) as $line)
    {
        $trim = trim($line);
        if ($trim == "" || $trim[0] == "'")
            continue ;
        if (preg_match('/^\[([A-Za-z_][A-Za-z0-9_]*)\s*$/D', $trim, $match))
        {
            $stack[] = $match[1];
            continue ;
        }
        if ($trim === "]")
        {
            if (count($stack))
                array_pop($stack);
            continue ;
        }
        if (count($stack) === 1 && $stack[0] === "Workflow"
            && preg_match('/^Mailbox\s*=\s*"([^"]*)"\s*$/D', $trim, $match))
        {
            $mailbox = strtolower(trim(stripcslashes($match[1])));
            if (function_exists("school_mailbox_purpose_is_valid"))
                return (school_mailbox_purpose_is_valid($mailbox) ? $mailbox : "");
            return (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $mailbox) ? $mailbox : "");
        }
    }
    return ("");
}

function document_title_fallback($path)
{
    $name = pathinfo((string)$path, PATHINFO_FILENAME);
    $name = trim(preg_replace('/[_-]+/', ' ', $name));
    if ($name == "")
        return ("Document");
    return (function_exists("mb_strtoupper")
        ? mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1)
        : ucfirst($name));
}

function document_title_from_file($file, $fallback = "")
{
    static $cache = [];

    $file = (string)$file;
    if ($fallback == "")
        $fallback = document_title_fallback($file);
    $cache_key = $file."\0".$fallback;
    if (isset($cache[$cache_key]))
        return ($cache[$cache_key]);

    $content = @file_get_contents($file);
    if ($content !== false &&
        preg_match('/^[ \\t]*Title[ \\t]*=[ \\t]*"((?:\\\\.|[^"\\\\])*)"/m', $content, $match))
    {
        $title = trim(stripcslashes($match[1]));
        if ($title != "")
            return ($cache[$cache_key] = $title);
    }
    return ($cache[$cache_key] = $fallback);
}

function document_reference_label($reference)
{
    $reference = (string)$reference;
    $split = explode(":", $reference, 2);
    if (count($split) != 2)
    {
        $relative = document_source_normalize_path($reference);
        if ($relative != "" && pathinfo($relative, PATHINFO_EXTENSION) == "dab" &&
            document_source_path_is_visible($relative))
        {
            $candidate = realpath(__DIR__."/../".$relative);
            if ($candidate !== false && is_file($candidate))
                return (document_title_from_file($candidate, document_title_fallback($relative)));
        }
        return (document_title_fallback($reference));
    }

    $roots = document_source_roots();
    $source = $split[0];
    $path = document_source_normalize_path($split[1]);
    if (!isset($roots[$source]) || $path == "" || !document_source_path_is_visible($path))
        return (document_title_fallback($path != "" ? $path : $reference));

    $file = rtrim($roots[$source]["root"], "/")."/".$path;
    if (!is_file($file))
        return (document_title_fallback($path));
    return (document_title_from_file($file, document_title_fallback($path)));
}

function get_contract_document_sources()
{
    $contracts = [];

    foreach (get_document_sources() as $source => $docs)
    {
        foreach ($docs["documents"] as $doc)
        {
            $basename = mb_strtolower(basename($doc));
            if (strpos($basename, "contrat") === false && strpos($basename, "contract") === false)
                continue ;
            $editor_path = document_source_editor_path($source, $doc);
            if ($editor_path === NULL)
                continue ;
            $contracts[] = [
                "source" => $source,
                "path" => $doc,
                "reference" => document_reference_from_source_path($source, $doc),
                "editor_path" => $editor_path,
                "label" => document_reference_label(document_reference_from_source_path($source, $doc)),
            ];
        }
    }
    usort($contracts, function ($a, $b) {
        return (strnatcasecmp($a["label"], $b["label"]));
    });
    return ($contracts);
}
