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

function document_reference_label($reference)
{
    $split = explode(":", $reference, 2);
    if (count($split) != 2)
        return ($reference);
    return ($split[0]." / ".$split[1]);
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
