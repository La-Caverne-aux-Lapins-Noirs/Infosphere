<?php

require_once (__DIR__."/document_signatures.php");

function document_context_schema_bool($value, $default = false)
{
    $value = strtolower(trim((string)$value, " \t\n\r\0\x0B\"'"));
    if ($value === '')
        return ((bool)$default);
    if (in_array($value, ['1', 'true', 'yes', 'oui', 'required'], true))
        return (true);
    if (in_array($value, ['0', 'false', 'no', 'non', 'optional'], true))
        return (false);
    return ((bool)$default);
}

function document_context_schema_string($value)
{
    $value = trim((string)$value);
    if (strlen($value) >= 2)
    {
        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'"))
            $value = substr($value, 1, -1);
    }
    return ($value);
}

function document_context_schema_resolve_include($source_file, $include)
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

function document_context_schema_include_files($file, &$visited = NULL)
{
    if ($visited === NULL)
        $visited = [];
    $real = realpath($file);
    if ($real === false || isset($visited[$real]) || !is_file($real))
        return ([]);
    $visited[$real] = true;

    $files = [];
    $content = @file_get_contents($real);
    if ($content !== false && preg_match_all('/@include\s+"([^"]+)"/', $content, $matches))
    {
        foreach ($matches[1] as $include)
        {
            $resolved = document_context_schema_resolve_include($real, $include);
            if ($resolved !== NULL)
                $files = array_merge($files, document_context_schema_include_files($resolved, $visited));
        }
    }
    // Included bases are read first so the concrete model can override them.
    $files[] = $real;
    return (array_values(array_unique($files)));
}

function document_context_schema_parse_file($file)
{
    if (!is_file($file) || !is_readable($file))
        return ([]);
    $content = @file_get_contents($file);
    if ($content === false)
        return ([]);

    $contexts = [];
    $stack = [];
    $in_contexts = false;
    $current = NULL;
    foreach (preg_split('/\r\n|\r|\n/', $content) as $line)
    {
        $trim = trim($line);
        if ($trim === '' || $trim[0] === "'")
            continue ;

        if (preg_match('/^\[([A-Za-z_][A-Za-z0-9_]*)\s*$/D', $trim, $m))
        {
            $stack[] = $m[1];
            if (count($stack) === 1 && $m[1] === 'Contexts')
            {
                $in_contexts = true;
                $current = NULL;
                continue ;
            }
            if ($in_contexts && count($stack) === 2 && document_signature_slot_is_valid($m[1]))
            {
                $current = $m[1];
                if (!isset($contexts[$current]))
                    $contexts[$current] = [];
            }
            continue ;
        }
        if ($trim === ']')
        {
            if ($in_contexts && count($stack) === 2)
                $current = NULL;
            if (count($stack))
                array_pop($stack);
            if ($in_contexts && !count($stack))
                $in_contexts = false;
            continue ;
        }
        if (!$in_contexts || $current === NULL || !preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/D', $trim, $m))
            continue ;
        $contexts[$current][strtolower($m[1])] = document_context_schema_string($m[2]);
    }
    return ($contexts);
}

function document_context_model_schema($file)
{
    $raw = [];
    foreach (document_context_schema_include_files($file) as $source)
        foreach (document_context_schema_parse_file($source) as $name => $definition)
            $raw[$name] = array_merge($raw[$name] ?? [], $definition);

    $schema = [];
    foreach ($raw as $name => $definition)
    {
        $type = strtolower(trim((string)($definition['type'] ?? '')));
        $prefix = trim((string)($definition['prefix'] ?? $name));
        if ($type === '' || $prefix === '')
            continue ;
        $infer = trim((string)($definition['inferfrom'] ?? ''));
        $infer_from = [];
        if ($infer !== '')
            foreach (preg_split('/\s*,\s*/', $infer) as $candidate)
                if (document_signature_slot_is_valid($candidate))
                    $infer_from[] = $candidate;
        $signatory = trim((string)($definition['signatory'] ?? ''));
        if ($signatory !== '' && !document_signature_slot_is_valid($signatory))
            $signatory = '';
        $schema[$name] = [
            'type' => $type,
            'prefix' => $prefix,
            'label' => trim((string)($definition['label'] ?? $name)),
            'required' => document_context_schema_bool($definition['required'] ?? '', false),
            'signatory' => $signatory,
            'infer_from' => array_values(array_unique($infer_from)),
            'auto' => trim((string)($definition['auto'] ?? '')),
            'blank' => document_context_schema_bool($definition['blank'] ?? ($definition['keepwhenblank'] ?? ''), false),
            'placeholder' => trim((string)($definition['placeholder'] ?? '')),
        ];
    }
    return ($schema);
}

function document_context_normalize_bindings($bindings, array $schema)
{
    if (is_string($bindings))
        $bindings = json_decode($bindings, true);
    if (!is_array($bindings))
        return ([]);
    $out = [];
    foreach ($bindings as $name => $value)
    {
        if (!isset($schema[$name]) || !is_scalar($value))
            continue ;
        $value = trim((string)$value);
        if ($value !== '')
            $out[$name] = $value;
    }
    return ($out);
}

function document_context_schema_public(array $schema)
{
    $out = [];
    foreach ($schema as $name => $definition)
        $out[$name] = [
            'type' => $definition['type'],
            'label' => $definition['label'],
            'required' => !empty($definition['required']),
            'infer_from' => $definition['infer_from'],
            'auto' => $definition['auto'],
            'blank' => !empty($definition['blank']),
            'placeholder' => $definition['placeholder'],
            'signatory' => $definition['signatory'],
        ];
    return ($out);
}
