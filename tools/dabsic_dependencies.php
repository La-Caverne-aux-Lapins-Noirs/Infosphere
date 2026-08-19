<?php

/*
** Lightweight inspection helpers for Dabsic source dependencies.
**
** These helpers intentionally inspect only static quoted paths.  Dabsic may
** compute directive paths through expressions; such references remain valid
** Dabsic but cannot be indexed safely without executing the configuration.
*/

function dabsic_dependency_mask_comments($content)
{
    $content = (string)$content;
    $masked = $content;
    $length = strlen($content);
    $state = "normal";

    for ($i = 0; $i < $length; ++$i)
    {
        $char = $content[$i];
        if ($state === "string")
        {
            if ($char === "\\" && $i + 1 < $length)
            {
                ++$i;
                continue ;
            }
            if ($char === '"')
                $state = "normal";
            continue ;
        }
        if ($state === "line")
        {
            if ($char === "\n" || $char === "\r")
                $state = "normal";
            else
                $masked[$i] = " ";
            continue ;
        }
        if ($state === "block")
        {
            if ($char === "*" && $i + 1 < $length && $content[$i + 1] === "]")
            {
                $masked[$i] = " ";
                $masked[$i + 1] = " ";
                ++$i;
                $state = "normal";
            }
            else if ($char !== "\n" && $char !== "\r")
                $masked[$i] = " ";
            continue ;
        }

        if ($char === '"')
            $state = "string";
        else if ($char === "'")
        {
            $masked[$i] = " ";
            $state = "line";
        }
        else if ($char === "[" && $i + 1 < $length && $content[$i + 1] === "*")
        {
            $masked[$i] = " ";
            $masked[$i + 1] = " ";
            ++$i;
            $state = "block";
        }
    }
    return ($masked);
}

function dabsic_dependency_static_references($content, array $extensions = ["dab", "dabsic"])
{
    $content = (string)$content;
    $references = [];
    if ($content === "")
        return ($references);

    $masked = dabsic_dependency_mask_comments($content);
    $pattern = '/@(include|insert|push)\\b(?:\\s+[A-Za-z_][A-Za-z0-9_]*)?(?:\\s*\\([^\\r\\n)]*\\))?\\s*"([^"\\r\\n]+)"/mi';
    if (!preg_match_all($pattern, $masked, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        return ($references);

    $extensions = array_values(array_filter(array_map(function($extension) {
        return (strtolower(ltrim(trim((string)$extension), ".")));
    }, $extensions)));

    foreach ($matches as $match)
    {
        $requested = (string)$match[2][0];
        $extension = strtolower(pathinfo($requested, PATHINFO_EXTENSION));
        if (count($extensions) && !in_array($extension, $extensions, true))
            continue ;
        $offset = (int)$match[0][1];
        $length = strlen((string)$match[0][0]);
        $references[] = [
            "directive" => strtolower((string)$match[1][0]),
            "requested_path" => $requested,
            "offset" => $offset,
            "length" => $length,
            "source" => substr($content, $offset, $length),
        ];
    }
    return ($references);
}

function dabsic_dependency_normalize_path($path)
{
    $path = str_replace("\\", "/", (string)$path);
    $prefix = substr($path, 0, 1) === "/" ? "/" : "";
    $parts = [];
    foreach (explode("/", $path) as $part)
    {
        if ($part === "" || $part === ".")
            continue ;
        if ($part === "..")
        {
            if (count($parts) && end($parts) !== "..")
                array_pop($parts);
            else if ($prefix === "")
                $parts[] = "..";
            continue ;
        }
        $parts[] = $part;
    }
    return ($prefix.implode("/", $parts));
}

function dabsic_dependency_project_root()
{
    return (dabsic_dependency_normalize_path(dirname(__DIR__)));
}

function dabsic_dependency_resolve($source_file, $requested_path, array $extra_roots = [])
{
    $source_file = (string)$source_file;
    $requested_path = trim((string)$requested_path);
    if ($requested_path === "")
        return (NULL);

    $candidates = [];
    if (substr($requested_path, 0, 1) === "/")
        $candidates[] = $requested_path;
    else
    {
        if ($source_file !== "")
            $candidates[] = dirname($source_file)."/".$requested_path;
        foreach ($extra_roots as $root)
            if (trim((string)$root) !== "")
                $candidates[] = rtrim((string)$root, "/")."/".$requested_path;
        $candidates[] = dabsic_dependency_project_root()."/".$requested_path;
    }

    foreach ($candidates as $candidate)
    {
        $candidate = dabsic_dependency_normalize_path($candidate);
        $real = realpath($candidate);
        if ($real !== false && is_file($real) && is_readable($real))
            return (dabsic_dependency_normalize_path($real));
    }
    return (NULL);
}

function dabsic_dependency_relative_path($source_file, $target_file)
{
    $source_dir = dabsic_dependency_normalize_path(dirname((string)$source_file));
    $target = dabsic_dependency_normalize_path((string)$target_file);
    $from = array_values(array_filter(explode("/", trim($source_dir, "/")), "strlen"));
    $to = array_values(array_filter(explode("/", trim($target, "/")), "strlen"));

    while (count($from) && count($to) && $from[0] === $to[0])
    {
        array_shift($from);
        array_shift($to);
    }
    $relative = str_repeat("../", count($from)).implode("/", $to);
    return ($relative === "" ? basename($target) : $relative);
}

function dabsic_dependency_walk($source_file, $max_depth = 16)
{
    $source_file = realpath((string)$source_file);
    if ($source_file === false || !is_file($source_file))
        return ([]);
    $source_file = dabsic_dependency_normalize_path($source_file);
    $max_depth = max(0, min(64, (int)$max_depth));
    $edges = [];
    $visited = [];

    $walk = function($file, $depth, $chain) use (&$walk, &$edges, &$visited, $max_depth) {
        $visit_key = $file."@".$depth;
        if (isset($visited[$visit_key]) || $depth > $max_depth)
            return ;
        $visited[$visit_key] = true;
        $content = @file_get_contents($file);
        if ($content === false)
            return ;
        foreach (dabsic_dependency_static_references($content) as $reference)
        {
            $resolved = dabsic_dependency_resolve($file, $reference["requested_path"]);
            $edge = $reference;
            $edge["source_file"] = $file;
            $edge["resolved_path"] = $resolved;
            $edge["depth"] = $depth;
            $edge["chain"] = array_merge($chain, [$file]);
            $edges[] = $edge;
            if ($resolved !== NULL && $depth < $max_depth && !in_array($resolved, $edge["chain"], true))
                $walk($resolved, $depth + 1, $edge["chain"]);
        }
    };
    $walk($source_file, 0, []);
    return ($edges);
}
