<?php

/**
 * Generic document-signature declarations.
 *
 * A document model declares the semantic signature slots it needs under a
 * top-level Signatures scope. Slot names are deliberately restricted to ANSI C
 * identifiers so they can safely become Dabsic scope names and Infosphere
 * semantic identifiers:
 *
 * [Signatures
 *   [Director
 *     Required = 1
 *   ]
 *   [Student
 *     Required = 1
 *   ]
 * ]
 *
 * The model does not contain the signatory identity. Infosphere binds a real
 * context (user, director, legal representative, ...) to each slot. The chosen
 * identity is injected as an ordinary Dabsic scope carrying Signatory=1 and
 * As="<Slot>"; DocBuilder performs the Signatories.<Slot> normalization.
 */

function document_signature_slot_is_valid($slot)
{
    return (is_string($slot) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $slot) === 1);
}

function document_signature_bool($value, $default = true)
{
    $value = strtolower(trim((string)$value));
    if ($value === '')
        return ((bool)$default);
    if (in_array($value, ['0', 'false', 'no', 'non', 'optional'], true))
        return (false);
    if (in_array($value, ['1', 'true', 'yes', 'oui', 'required'], true))
        return (true);
    return ((bool)$default);
}

function document_signature_parse_scope($file, $scope_name)
{
    if (!is_file($file) || !is_readable($file))
        return ([]);
    $content = @file_get_contents($file);
    if ($content === false)
        return ([]);

    $slots = [];
    $stack = [];
    $in_scope = false;
    $current_slot = NULL;
    foreach (preg_split('/\r\n|\r|\n/', $content) as $line)
    {
        $trim = trim($line);
        if ($trim === '' || $trim[0] === "'")
            continue ;

        if (preg_match('/^\[([A-Za-z_][A-Za-z0-9_]*)\s*$/D', $trim, $m))
        {
            $stack[] = $m[1];
            if (count($stack) === 1 && $m[1] === $scope_name)
            {
                $in_scope = true;
                $current_slot = NULL;
                continue ;
            }
            if ($in_scope && count($stack) === 2 && document_signature_slot_is_valid($m[1]))
            {
                $current_slot = $m[1];
                if (!isset($slots[$current_slot]))
                    $slots[$current_slot] = ['required' => true];
            }
            continue ;
        }
        if ($trim === ']')
        {
            if ($in_scope && count($stack) === 2)
                $current_slot = NULL;
            if (count($stack))
                array_pop($stack);
            if ($in_scope && !count($stack))
                $in_scope = false;
            continue ;
        }
        if ($in_scope && $current_slot !== NULL && preg_match('/^Required\s*=\s*(.*?)\s*$/D', $trim, $m))
        {
            $slots[$current_slot]['required'] = document_signature_bool(trim($m[1], "\"'"), true);
            continue ;
        }
        if ($in_scope && $current_slot !== NULL && preg_match('/^Role\s*=\s*(.*?)\s*$/D', $trim, $m))
        {
            $role_label = trim((string)$m[1]);
            if (strlen($role_label) >= 2)
            {
                $first = $role_label[0];
                $last = $role_label[strlen($role_label) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'"))
                    $role_label = substr($role_label, 1, -1);
            }
            if ($role_label !== '')
                $slots[$current_slot]['role_label'] = $role_label;
        }
    }
    return ($slots);
}

function document_signature_resolve_include($source_file, $include)
{
    $include = trim((string)$include);
    if ($include === "")
        return (NULL);

    $candidates = [dirname($source_file).DIRECTORY_SEPARATOR.$include];
    $project_root = realpath(__DIR__."/..");
    if ($project_root !== false)
        $candidates[] = rtrim($project_root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$include;

    foreach ($candidates as $candidate)
    {
        $real = realpath($candidate);
        if ($real !== false && is_file($real))
            return ($real);
    }
    return (NULL);
}

function document_signature_include_files($file, &$visited = NULL)
{
    if ($visited === NULL)
        $visited = [];
    $real = realpath($file);
    if ($real === false || isset($visited[$real]) || !is_file($real))
        return ([]);
    $visited[$real] = true;
    $files = [$real];
    $content = @file_get_contents($real);
    if ($content === false)
        return ($files);
    if (preg_match_all('/@include\s+"([^"]+)"/', $content, $matches))
        foreach ($matches[1] as $include)
        {
            $resolved = document_signature_resolve_include($real, $include);
            if ($resolved !== NULL)
                $files = array_merge($files, document_signature_include_files($resolved, $visited));
        }
    return (array_values(array_unique($files)));
}

function document_signature_model_slots($file)
{
    $slots = [];
    foreach (document_signature_include_files($file) as $source)
        foreach (document_signature_parse_scope($source, 'Signatures') as $slot => $definition)
            $slots[$slot] = $definition;
    return ($slots);
}

function document_signature_slot_names($file)
{
    return (array_keys(document_signature_model_slots($file)));
}

function document_signature_missing_required_bindings(array $schema, array $bindings)
{
    $missing = [];
    foreach ($schema as $slot => $definition)
        if (!empty($definition["required"]) && !isset($bindings[$slot]))
            $missing[] = $slot;
    return ($missing);
}

function document_signature_normalize_bindings($bindings, array $allowed_slots = [])
{
    if (is_string($bindings))
        $bindings = json_decode($bindings, true);
    if (!is_array($bindings))
        return ([]);
    $allowed = count($allowed_slots) ? array_flip($allowed_slots) : NULL;
    $out = [];
    foreach ($bindings as $slot => $source)
    {
        if (!document_signature_slot_is_valid($slot) || ($allowed !== NULL && !isset($allowed[$slot])))
            continue ;
        if (!is_string($source) || !document_signature_slot_is_valid($source))
            continue ;
        $out[$slot] = $source;
    }
    return ($out);
}
