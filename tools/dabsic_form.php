<?php

require_once (__DIR__."/dabsic_editor.php");
require_once (__DIR__."/document_context.php");

/**
 * Output files accepted by the Dabsic form page.
 *
 * The URL only exposes the symbolic key. The API resolves the real path again
 * from this server-side list before every read or write.
 *
 * Add future destinations here, for example:
 *
 *   "student-certificate" => [
 *       "file" => "dres/doc/data/student-certificate.dab",
 *       "label" => "Données du certificat étudiant"
 *   ],
 */

function dabsic_form_dynamic_output($key, $trusted_user_document_id = NULL)
{
    global $Configuration;

    if (preg_match('/^needs-analysis:([0-9]+)$/', (string)$key, $m))
    {
        $prospect = document_context_user((int)$m[1]);
        if (!is_array($prospect) || ($prospect["profile_status"] ?? "") != "prospect" ||
            empty($prospect["codename"]))
            return (NULL);

        $user_root = realpath($Configuration->UsersDir($prospect["codename"]));
        if ($user_root === false || !is_dir($user_root))
            return (NULL);
        return ([
            "absolute_file" => $user_root.DIRECTORY_SEPARATOR."admin/subscription/analyse_besoin.dab",
            "authorized_root" => $user_root,
            "label" => "Analyse du besoin - ".trim(($prospect["first_name"] ?? "")." ".($prospect["family_name"] ?? "")),
            "create_parent" => true,
            "private_user_output" => true
        ]);
    }

    if (preg_match('/^user-document:([0-9]+):([a-f0-9]{32}):([0-5])$/', (string)$key, $m))
    {
        $id_user = (int)$m[1];
        if ((int)$trusted_user_document_id !== $id_user && !is_director_for_student($id_user))
            return (NULL);
        $user = db_select_one("codename FROM user WHERE id = $id_user AND authority != -1");
        if ($user == NULL || !isset($user["codename"]))
            return (NULL);
        $directory = $Configuration->UsersDir($user["codename"]).
            "admin/documentation/forms/";
        new_directory($directory."index.php");
        $project_root = realpath(dabsic_editor_project_root());
        $directory_root = realpath($directory);
        if ($project_root === false || $directory_root === false ||
            !dabsic_editor_path_is_inside($directory_root, $project_root))
            return (NULL);
        $relative = str_replace(DIRECTORY_SEPARATOR, "/",
            substr($directory_root, strlen(rtrim($project_root, DIRECTORY_SEPARATOR)) + 1));
        return ([
            "file" => rtrim($relative, "/")."/".$m[2]."-".$m[3].".dab",
            "label" => "Données documentaires de ".$user["codename"],
            "create_parent" => true
        ]);
    }

    if (!preg_match('/^school-contract:([0-9]+):([a-f0-9]{32}):([a-f0-9]{16})$/', (string)$key, $m))
        return (NULL);
    $school = fetch_school((int)$m[1]);
    if ($school == NULL || !is_array($school))
        return (NULL);
    $codename = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($school["codename"] ?? $m[1]));
    return ([
        "file" => "dres/doc/data/contracts/".$codename."/".$m[2]."-".$m[3].".dab",
        "label" => "Données de contrat pour ".($school["name"] ?? $codename),
        "create_parent" => true
    ]);
}

function dabsic_form_allowed_outputs()
{
    return ([
        "test" => [
            "file" => "dres/doc/test.form.dab",
            "label" => "Test Dabsic"
        ]
    ]);
}

function dabsic_form_url($reference, $output)
{
    return (
        "index.php?p=DabsicFormMenu&file=".rawurlencode((string)$reference).
        "&output=".rawurlencode((string)$output)
    );
}

function dabsic_form_output_label($definition, $fallback)
{
    global $Dictionnary;

    if (isset($definition["label_key"]) && isset($Dictionnary[$definition["label_key"]]))
        return ($Dictionnary[$definition["label_key"]]);
    if (isset($definition["label"]) && trim((string)$definition["label"]) !== "")
        return ((string)$definition["label"]);
    return ((string)$fallback);
}

function dabsic_form_resolve_output($key, $for_write = false, $trusted_user_document_id = NULL)
{
    $outputs = dabsic_form_allowed_outputs();
    if (!is_string($key))
        return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);
    $definition = isset($outputs[$key]) && is_array($outputs[$key])
        ? $outputs[$key]
        : dabsic_form_dynamic_output($key, $trusted_user_document_id);
    if ($definition === NULL)
        return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);

    if (!empty($definition["private_user_output"]))
    {
        $absolute = $definition["absolute_file"] ?? "";
        $authorized_root = $definition["authorized_root"] ?? "";
        if (!is_string($absolute) || !is_string($authorized_root) ||
            strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) !== "dab")
            return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);

        $authorized_root = realpath($authorized_root);
        if ($authorized_root === false || !is_dir($authorized_root))
            return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);

        $parent_path = dirname($absolute);
        if (!is_dir($parent_path) && !empty($definition["create_parent"]))
            @mkdir($parent_path, 0770, true);
        $parent = realpath($parent_path);
        if ($parent === false || !is_dir($parent) ||
            !dabsic_editor_path_is_inside($parent, $authorized_root))
            return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);

        $absolute = $parent.DIRECTORY_SEPARATOR.basename($absolute);
        $exists = is_file($absolute);
        if ($exists && !is_readable($absolute))
            return (["ok" => false, "error" => "DabsicFormCannotReadOutput"]);
        if ($for_write && (($exists && !is_writable($absolute)) || !is_writable($parent)))
            return (["ok" => false, "error" => "DabsicFormOutputReadOnly"]);

        return ([
            "ok" => true,
            "key" => $key,
            "definition" => $definition,
            "label" => dabsic_form_output_label($definition, $key),
            "relative" => "admin/subscription/".basename($absolute),
            "absolute" => $absolute,
            "exists" => $exists
        ]);
    }

    $relative = dabsic_editor_normalize_requested_path($definition["file"] ?? "");
    if ($relative === NULL || strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== "dab")
        return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);

    $project_root = dabsic_editor_project_root();
    if ($project_root === false)
        return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);

    $candidate = $project_root.DIRECTORY_SEPARATOR.$relative;
    $exists = is_file($candidate);
    if ($exists)
    {
        $absolute = realpath($candidate);
        if ($absolute === false)
            return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);
        $parent = dirname($absolute);
    }
    else
    {
        $parent_path = dirname($candidate);
        if (!is_dir($parent_path) && !empty($definition["create_parent"]))
            @mkdir($parent_path, 0775, true);
        $parent = realpath($parent_path);
        if ($parent === false || !is_dir($parent))
            return (["ok" => false, "error" => "DabsicFormOutputDirectoryMissing"]);
        $absolute = $parent.DIRECTORY_SEPARATOR.basename($candidate);
    }

    $authorized = false;
    foreach (dabsic_editor_allowed_roots() as $root)
        if (dabsic_editor_path_is_inside($parent, $root))
        {
            $authorized = true;
            break ;
        }
    if (!$authorized)
        return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);

    if ($exists && !is_readable($absolute))
        return (["ok" => false, "error" => "DabsicFormCannotReadOutput"]);
    if ($for_write && (($exists && !is_writable($absolute)) || !is_writable($parent)))
        return (["ok" => false, "error" => "DabsicFormOutputReadOnly"]);

    return ([
        "ok" => true,
        "key" => $key,
        "definition" => $definition,
        "label" => dabsic_form_output_label($definition, $key),
        "relative" => $relative,
        "absolute" => $absolute,
        "exists" => $exists
    ]);
}

function dabsic_form_process($command, $stdin = "")
{
    $stdout_file = tempnam(sys_get_temp_dir(), "infosphere_dabsic_form_out_");
    $stderr_file = tempnam(sys_get_temp_dir(), "infosphere_dabsic_form_err_");
    if ($stdout_file === false || $stderr_file === false)
    {
        if ($stdout_file !== false)
            @unlink($stdout_file);
        if ($stderr_file !== false)
            @unlink($stderr_file);
        return ([
            "status" => 127,
            "stdout" => "",
            "stderr" => "Impossible de créer les fichiers temporaires nécessaires à mergeconf."
        ]);
    }

    $process = proc_open(
        $command,
        [
            0 => ["pipe", "r"],
            1 => ["file", $stdout_file, "w"],
            2 => ["file", $stderr_file, "w"]
        ],
        $pipes,
        dabsic_editor_project_root()
    );
    if (!is_resource($process))
    {
        @unlink($stdout_file);
        @unlink($stderr_file);
        return ([
            "status" => 127,
            "stdout" => "",
            "stderr" => "Impossible de lancer mergeconf."
        ]);
    }

    $written = dabsic_editor_write_stream($pipes[0], (string)$stdin);
    fclose($pipes[0]);
    $status = proc_close($process);
    $stdout = @file_get_contents($stdout_file);
    $stderr = @file_get_contents($stderr_file);
    @unlink($stdout_file);
    @unlink($stderr_file);

    if (!$written)
        $status = 127;
    return ([
        "status" => (int)$status,
        "stdout" => $stdout === false ? "" : $stdout,
        "stderr" => $stderr === false ? "" : $stderr
    ]);
}

function dabsic_form_include_paths($reference)
{
    $paths = [dirname($reference)];
    foreach (dabsic_editor_allowed_roots() as $root)
        $paths[] = $root;

    $out = [];
    foreach ($paths as $path)
    {
        $path = realpath($path);
        if ($path !== false && is_dir($path) && !in_array($path, $out, true))
            $out[] = $path;
    }
    return ($out);
}

function dabsic_form_mergeconf_command($reference, array $extra_files = [], $resolve = true, array $fields = [])
{
    $cmd = "mergeconf";
    foreach (dabsic_form_include_paths($reference) as $path)
        $cmd .= " -I ".escapeshellarg($path);
    $cmd .= " -i ".escapeshellarg($reference);
    foreach ($extra_files as $file)
        $cmd .= " -i ".escapeshellarg($file);
    foreach ($fields as $key => $value)
        $cmd .= " -m ".escapeshellarg($key."=".$value);
    $cmd .= " -of .dabsic";
    if ($resolve)
        $cmd .= " --resolve";
    return ($cmd);
}

function dabsic_form_clean_diagnostic($text)
{
    $text = preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', (string)$text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = trim($text);
    if (strlen($text) > 20000)
        $text = substr($text, 0, 20000)."\n…";
    return ($text);
}

function dabsic_form_normalize_field($field)
{
    $field = trim((string)$field);
    if (preg_match('/^\$\((.*)\)$/', $field, $match))
        $field = $match[1];
    $field = trim($field, " \t\n\r\0\x0B`'\"(){}:,;");
    while (substr($field, 0, 3) === "[].")
        $field = substr($field, 3);
    $field = ltrim($field, ".");

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $field))
        return (NULL);

    $parts = explode(".", $field);
    $last = strtolower(end($parts));
    if (in_array($last, [
        "dab", "dabsic", "json", "php", "phtml", "js", "css", "c", "cpp", "cc", "h", "hpp",
        "txt", "xml", "csv", "pdf", "html", "htm", "line", "column"
    ], true))
        return (NULL);
    if (in_array(strtolower($parts[0]), [
        "warning", "error", "mergeconf", "dabsic", "undefined", "unresolvable",
        "variable", "address", "operation", "or", "from", "context", "on", "line"
    ], true))
        return (NULL);
    return ($field);
}

function dabsic_form_extract_missing_fields($stderr)
{
    $text = dabsic_form_clean_diagnostic($stderr);
    if ($text === "")
        return ([]);

    $fields = [];
    foreach (explode("\n", $text) as $line)
    {
        if (!preg_match('/warn|missing|undefined|unresolved|resolve|not[ -]?found|unknown|introuvable|absent/i', $line))
            continue ;
        if (preg_match('/Cannot resolve all operations/i', $line) &&
            !preg_match('/Undefined variable or unresolvable address/i', $line))
            continue ;

        $candidates = [];

        // Current LibLapin diagnostic emitted by expr_compute. Parse this first
        // so the context address is never mistaken for another missing field.
        if (preg_match_all(
            '/Undefined variable or unresolvable address\s+[`\'\"]?'.
            '((?:\[\]\.)?[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)/i',
            $line,
            $matches
        ))
            $candidates = array_merge($candidates, $matches[1]);
        else
        {
            // Compatibility with older or locally modified LibLapin diagnostics.
            if (preg_match_all('/[`\'\"]((?:\[\]\.)?[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)[`\'\"]/', $line, $matches))
                $candidates = array_merge($candidates, $matches[1]);
            if (preg_match_all('/\$\(((?:\[\]\.)?[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\)/', $line, $matches))
                $candidates = array_merge($candidates, $matches[1]);
            if (preg_match_all('/(?<![a-zA-Z0-9_])(?:\[\]\.)?[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)+(?![a-zA-Z0-9_])/', $line, $matches))
                $candidates = array_merge($candidates, $matches[0]);
            if (preg_match_all('/(?:field|variable|node|symbol|reference|path|champ)\s*[:=]?\s*([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)/i', $line, $matches))
                $candidates = array_merge($candidates, $matches[1]);
            if (preg_match_all('/(?:resolve|missing|undefined|unresolved|not[ -]?found|unknown|introuvable|absent)\s+(?:field|variable|node|symbol|reference|path|champ)?\s*[:=]?\s*[`\'\"]?((?:\[\]\.)?[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)/i', $line, $matches))
                $candidates = array_merge($candidates, $matches[1]);
        }

        foreach ($candidates as $candidate)
        {
            $field = dabsic_form_normalize_field($candidate);
            if ($field !== NULL)
                $fields[$field] = true;
        }
    }

    $fields = array_keys($fields);
    natcasesort($fields);
    return (array_values($fields));
}

function dabsic_form_docbuilder_collect_sources($file, &$visited = NULL)
{
    if ($visited === NULL)
        $visited = [];
    $real = realpath($file);
    if ($real === false || !is_file($real) || isset($visited[$real]))
        return ([]);
    $visited[$real] = true;
    $content = @file_get_contents($real);
    if ($content === false)
        return ([]);

    $sources = [$real => $content];
    $directory = dirname($real);
    if (preg_match_all('/@include\s+"([^"]+)"/', $content, $matches))
        foreach ($matches[1] as $include)
            foreach (dabsic_form_docbuilder_collect_sources($directory.DIRECTORY_SEPARATOR.$include, $visited) as $path => $child)
                $sources[$path] = $child;
    if (preg_match_all('/@insert\s+txt\s*\([^)]*\)\s*"([^"]+)"/', $content, $matches))
        foreach ($matches[1] as $include)
            foreach (dabsic_form_docbuilder_collect_sources($directory.DIRECTORY_SEPARATOR.$include, $visited) as $path => $child)
                $sources[$path] = $child;
    return ($sources);
}

function dabsic_form_docbuilder_defined_fields(array $sources)
{
    $defined = [];
    foreach ($sources as $path => $content)
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== "dab")
            continue ;
        $stack = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line)
        {
            if (preg_match('/^\s*\[([a-zA-Z_][a-zA-Z0-9_]*)\s*$/', $line, $match))
            {
                $stack[] = $match[1];
                continue ;
            }
            if (preg_match('/^\s*\]\s*$/', $line))
            {
                if (count($stack))
                    array_pop($stack);
                continue ;
            }
            if (preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*=/', $line, $match))
            {
                $parts = $stack;
                $parts[] = $match[1];
                $defined[implode(".", $parts)] = true;
            }
        }
    }
    return ($defined);
}

function dabsic_form_docbuilder_fields($reference, $chain = "")
{
    $sources = dabsic_form_docbuilder_collect_sources($reference);
    $defined = dabsic_form_docbuilder_defined_fields($sources);
    foreach (array_keys(dabsic_form_prefill_from_chain($chain)) as $field)
        $defined[$field] = true;

    $fields = [];
    foreach ($sources as $content)
    {
        if (preg_match_all('/\[(?:#|@)Variable(?:;|\.)([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)/', $content, $matches))
            foreach ($matches[1] as $field)
                if (!isset($defined[$field]))
                    $fields[$field] = true;
        if (preg_match_all('/\[#IfC;([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*);/', $content, $matches))
            foreach ($matches[1] as $field)
                if (!isset($defined[$field]))
                    $fields[$field] = true;
    }
    return ($fields);
}

function dabsic_form_discover_fields($requested_reference, $mode = "dabsic", $chain = "")
{
    $reference = dabsic_editor_resolve_file($requested_reference, false);
    if (!$reference["ok"])
        return ($reference);

    $process = dabsic_form_process(
        dabsic_form_mergeconf_command($reference["absolute"], [], true)
    );
    if ($process["status"] === 126 || $process["status"] === 127)
        return ([
            "ok" => false,
            "error" => "DabsicEditorMergeconfUnavailable",
            "details" => dabsic_form_clean_diagnostic($process["stderr"])
        ]);
    if ($process["status"] !== 0)
        return ([
            "ok" => false,
            "error" => "DabsicFormAnalysisFailed",
            "details" => dabsic_form_clean_diagnostic($process["stderr"] !== "" ? $process["stderr"] : $process["stdout"])
        ]);

    $field_set = [];
    $prefilled = $mode === "docbuilder" ? dabsic_form_prefill_from_chain($chain) : [];
    foreach (dabsic_form_extract_missing_fields($process["stderr"]) as $field)
        if (!array_key_exists($field, $prefilled))
            $field_set[$field] = true;
    if ($mode === "docbuilder")
        foreach (dabsic_form_docbuilder_fields($reference["absolute"], $chain) as $field => $unused)
            $field_set[$field] = true;
    $fields = array_keys($field_set);
    natcasesort($fields);
    $fields = array_values($fields);
    if (trim($process["stderr"]) !== "" && !count($fields))
        return ([
            "ok" => false,
            "error" => "DabsicFormCannotExtractFields",
            "details" => dabsic_form_clean_diagnostic($process["stderr"])
        ]);

    return ([
        "ok" => true,
        "reference" => $reference,
        "fields" => $fields,
        "warnings" => dabsic_form_clean_diagnostic($process["stderr"])
    ]);
}

function dabsic_form_flatten_values($value, $prefix = "", &$out = NULL)
{
    if ($out === NULL)
        $out = [];
    if (is_array($value))
    {
        foreach ($value as $key => $child)
        {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$key))
                continue ;
            dabsic_form_flatten_values($child, $prefix === "" ? $key : $prefix.".".$key, $out);
        }
    }
    else if ($prefix !== "")
    {
        if ($value === NULL)
            $value = "";
        else if (is_bool($value))
            $value = $value ? "true" : "false";
        $out[$prefix] = (string)$value;
    }
    return ($out);
}


function dabsic_form_override_file($output)
{
    return ((string)$output["absolute"].".mergeconf.json");
}

function dabsic_form_validate_overrides($overrides)
{
    if ($overrides === NULL || $overrides === "")
        return (["ok" => true, "values" => []]);
    if (!is_array($overrides))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "overrides"]);

    $out = [];
    foreach ($overrides as $key => $value)
    {
        $raw_key = (string)$key;
        $key = dabsic_form_normalize_field($raw_key);
        if ($key === NULL)
            return (["ok" => false, "error" => "DabsicFormInvalidOverride", "details" => $raw_key]);
        if (!is_scalar($value) && $value !== NULL)
            return (["ok" => false, "error" => "InvalidParameter", "details" => $key]);
        $out[$key] = $value === NULL ? "" : (string)$value;
    }
    ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return (["ok" => true, "values" => $out]);
}

function dabsic_form_load_overrides($output)
{
    $file = dabsic_form_override_file($output);
    if (!is_file($file))
        return (["ok" => true, "values" => [], "hash" => hash("sha256", ""), "exists" => false]);
    $content = @file_get_contents($file);
    if ($content === false)
        return (["ok" => false, "error" => "DabsicFormCannotReadOverrides"]);
    $decoded = json_decode($content, true);
    $validated = dabsic_form_validate_overrides($decoded);
    if (!$validated["ok"])
        return (["ok" => false, "error" => "DabsicFormInvalidOverrides", "details" => $validated["details"] ?? ""]);
    return ([
        "ok" => true,
        "values" => $validated["values"],
        "hash" => hash("sha256", $content),
        "exists" => true
    ]);
}

function dabsic_form_mergeconf_fields_for_file($file)
{
    $sidecar = (string)$file.".mergeconf.json";
    if (!is_file($sidecar))
        return ([]);
    $content = @file_get_contents($sidecar);
    $decoded = $content === false ? NULL : json_decode($content, true);
    $validated = dabsic_form_validate_overrides($decoded);
    if (!$validated["ok"])
        return ([]);
    $fields = [];
    foreach ($validated["values"] as $key => $value)
        $fields[] = $key."=".$value;
    return ($fields);
}

function dabsic_form_load_output_values($output)
{
    if (!$output["exists"])
        return (["ok" => true, "values" => [], "content" => "", "hash" => hash("sha256", "")]);

    $content = @file_get_contents($output["absolute"]);
    if ($content === false)
        return (["ok" => false, "error" => "DabsicFormCannotReadOutput"]);

    $process = dabsic_form_process("mergeconf -if .dabsic -of .json", $content);
    if ($process["status"] !== 0)
        return ([
            "ok" => false,
            "error" => "DabsicFormInvalidOutputFile",
            "details" => dabsic_form_clean_diagnostic($process["stderr"])
        ]);
    $data = json_decode($process["stdout"], true);
    if (!is_array($data))
        return ([
            "ok" => false,
            "error" => "DabsicFormInvalidOutputFile",
            "details" => json_last_error_msg()
        ]);

    $flat = [];
    dabsic_form_flatten_values($data, "", $flat);
    return ([
        "ok" => true,
        "values" => $flat,
        "content" => $content,
        "hash" => hash("sha256", $content)
    ]);
}

function dabsic_form_signatory_roles_from_chain_entry($entry)
{
    if (!is_array($entry) || !isset($entry["signatory"]))
        return ([]);
    $roles = $entry["signatory"];
    if (is_string($roles))
        $roles = [$roles];
    if (!is_array($roles))
        return ([]);
    $out = [];
    foreach ($roles as $role)
        if (is_string($role) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $role) === 1 && !in_array($role, $out, true))
            $out[] = $role;
    return ($out);
}

function dabsic_form_alias_signatory_values(array $chain, array &$values)
{
    foreach ($chain as $entry)
    {
        if (!is_array($entry))
            continue ;
        $prefix = document_context_sanitize_key($entry["prefix"] ?? "");
        if ($prefix == "")
            continue ;
        foreach (dabsic_form_signatory_roles_from_chain_entry($entry) as $role)
        {
            $source = $prefix.".";
            $target = "Signatories.".$role.".";
            foreach (array_keys($values) as $field)
                if (strncmp($field, $source, strlen($source)) === 0)
                    $values[$target.substr($field, strlen($source))] = $values[$field];
        }
    }
}

function dabsic_form_prefill_from_chain($raw)
{
    $chain = json_decode((string)$raw, true);
    if (!is_array($chain))
        return ([]);
    $fields = [];
    $files = [];
    $temporary = [];
    document_context_apply_chain($fields, $chain, $files, $temporary);
    $values = [];
    foreach ($files as $file)
    {
        $content = @file_get_contents($file);
        if ($content === false)
            continue ;
        $process = dabsic_form_process("mergeconf -if .dabsic -of .json", $content);
        if ($process["status"] !== 0)
            continue ;
        $data = json_decode($process["stdout"], true);
        if (is_array($data))
            dabsic_form_flatten_values($data, "", $values);
    }
    foreach ($fields as $field)
    {
        $pos = strpos($field, "=");
        if ($pos === false)
            continue ;
        $values[substr($field, 0, $pos)] = substr($field, $pos + 1);
    }
    dabsic_form_alias_signatory_values($chain, $values);
    foreach ($temporary as $file)
        @unlink($file);
    return ($values);
}

function dabsic_form_assign_value(&$root, $path, $value)
{
    $parts = explode(".", $path);
    $node =& $root;
    foreach ($parts as $index => $part)
    {
        if ($index === count($parts) - 1)
        {
            if (is_array($node) && isset($node[$part]) && is_array($node[$part]))
                return (false);
            $node[$part] = (string)$value;
            return (true);
        }
        if (isset($node[$part]) && !is_array($node[$part]))
            return (false);
        if (!isset($node[$part]))
            $node[$part] = [];
        $node =& $node[$part];
    }
    return (false);
}

function dabsic_form_build_dabsic(array $fields, array $values)
{
    $tree = [];
    foreach ($fields as $field)
    {
        if (!array_key_exists($field, $values))
            return (["ok" => false, "error" => "DabsicFormChanged"]);
        if (!is_scalar($values[$field]) && $values[$field] !== NULL)
            return (["ok" => false, "error" => "InvalidParameter", "details" => $field]);
        if (!dabsic_form_assign_value($tree, $field, $values[$field] === NULL ? "" : (string)$values[$field]))
            return (["ok" => false, "error" => "DabsicFormFieldConflict", "details" => $field]);
    }

    $json = json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false)
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);

    $process = dabsic_form_process("mergeconf -if .json -of .dabsic", $json);
    $content = $process["stdout"];
    if ($process["status"] !== 0 || trim($content) === "")
        return ([
            "ok" => false,
            "error" => "DabsicFormCannotSave",
            "details" => dabsic_form_clean_diagnostic($process["stderr"])
        ]);
    if (substr($content, -1) !== "\n")
        $content .= "\n";
    return (["ok" => true, "content" => $content]);
}

function dabsic_form_validate_resolution($reference, $data_file, array $overrides = [], $mode = "dabsic", $chain = "")
{
    $files = [$data_file];
    $temporary_context = NULL;

    if ($mode === "docbuilder" && trim((string)$chain) !== "")
    {
        $prefilled = dabsic_form_prefill_from_chain($chain);
        if (count($prefilled))
        {
            $built = dabsic_form_build_dabsic(array_keys($prefilled), $prefilled);
            if (!$built["ok"])
                return ($built);
            $temporary_context = tempnam(sys_get_temp_dir(), "infosphere_dabsic_form_context_");
            if ($temporary_context === false || file_put_contents($temporary_context, $built["content"], LOCK_EX) !== strlen($built["content"]))
            {
                if ($temporary_context !== false)
                    @unlink($temporary_context);
                return (["ok" => false, "error" => "DabsicFormCannotSave"]);
            }
            $files[] = $temporary_context;
        }
    }

    $process = dabsic_form_process(
        dabsic_form_mergeconf_command($reference, $files, true, $overrides)
    );
    if ($temporary_context !== NULL)
        @unlink($temporary_context);
    if ($process["status"] !== 0)
        return ([
            "ok" => false,
            "error" => "DabsicFormResolutionFailed",
            "details" => dabsic_form_clean_diagnostic($process["stderr"] !== "" ? $process["stderr"] : $process["stdout"])
        ]);

    $missing = dabsic_form_extract_missing_fields($process["stderr"]);
    if (count($missing))
        return ([
            "ok" => false,
            "error" => "DabsicFormStillMissing",
            "details" => implode("\n", $missing)
        ]);
    if (trim($process["stderr"]) !== "")
        return ([
            "ok" => false,
            "error" => "DabsicFormResolutionFailed",
            "details" => dabsic_form_clean_diagnostic($process["stderr"])
        ]);
    return (["ok" => true]);
}

function dabsic_form_save($requested_reference, $output_key, $values, $overrides, $reference_hash, $output_hash, $output_exists, $overrides_hash, $overrides_exists, $mode = "dabsic", $chain = "", $trusted_user_document_id = NULL)
{
    $discovery = dabsic_form_discover_fields($requested_reference, $mode, $chain);
    if (!$discovery["ok"])
        return ($discovery);
    $reference = $discovery["reference"];

    $reference_content = @file_get_contents($reference["absolute"]);
    if ($reference_content === false)
        return (["ok" => false, "error" => "DabsicEditorCannotRead"]);
    if (!is_string($reference_hash) || !preg_match('/^[a-f0-9]{64}$/i', $reference_hash) ||
        !hash_equals(strtolower($reference_hash), hash("sha256", $reference_content)))
        return (["ok" => false, "error" => "DabsicFormChanged"]);

    $output = dabsic_form_resolve_output($output_key, true, $trusted_user_document_id);
    if (!$output["ok"])
        return ($output);
    if (!is_array($values))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "values"]);
    $override_validation = dabsic_form_validate_overrides($overrides);
    if (!$override_validation["ok"])
        return ($override_validation);
    $overrides = $override_validation["values"];

    $required = $discovery["fields"];
    $submitted_fields = array_keys($values);
    natcasesort($submitted_fields);
    $submitted_fields = array_values($submitted_fields);
    $expected_fields = $required;
    natcasesort($expected_fields);
    $expected_fields = array_values($expected_fields);
    if ($submitted_fields !== $expected_fields)
        return (["ok" => false, "error" => "DabsicFormChanged"]);

    $built = dabsic_form_build_dabsic($required, $values);
    if (!$built["ok"])
        return ($built);

    if (!is_string($output_hash) || !preg_match('/^[a-f0-9]{64}$/i', $output_hash))
        return (["ok" => false, "error" => "DabsicEditorConflict"]);
    $output_exists = ((string)$output_exists === "1" || $output_exists === true || $output_exists === 1);
    $overrides_exists = ((string)$overrides_exists === "1" || $overrides_exists === true || $overrides_exists === 1);

    $lock_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.
        "infosphere_dabsic_form_".hash("sha256", $output["absolute"]).".lock";
    $lock = @fopen($lock_path, "c");
    if ($lock === false || !flock($lock, LOCK_EX))
    {
        if (is_resource($lock))
            fclose($lock);
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);
    }

    clearstatcache(true, $output["absolute"]);
    $currently_exists = is_file($output["absolute"]);
    $current_content = $currently_exists ? @file_get_contents($output["absolute"]) : "";
    if ($current_content === false || $currently_exists !== $output_exists ||
        !hash_equals(strtolower($output_hash), hash("sha256", $current_content)))
    {
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicEditorConflict"]);
    }

    $override_file = dabsic_form_override_file($output);
    $current_override_exists = is_file($override_file);
    $current_override_content = $current_override_exists ? @file_get_contents($override_file) : "";
    if ($current_override_content === false || $current_override_exists !== $overrides_exists ||
        !is_string($overrides_hash) || !preg_match('/^[a-f0-9]{64}$/i', $overrides_hash) ||
        !hash_equals(strtolower($overrides_hash), hash("sha256", $current_override_content)))
    {
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicEditorConflict"]);
    }

    $temporary_base = tempnam(dirname($output["absolute"]), ".dabsic-form-");
    $temporary = $temporary_base === false ? false : $temporary_base.".dab";
    if ($temporary_base !== false)
        @unlink($temporary_base);
    if ($temporary === false || file_put_contents($temporary, $built["content"], LOCK_EX) !== strlen($built["content"]))
    {
        if ($temporary !== false)
            @unlink($temporary);
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);
    }

    $validation = dabsic_editor_validate_content($built["content"]);
    if ($validation["ok"])
        $validation = dabsic_form_validate_resolution(
            $reference["absolute"],
            $temporary,
            $overrides,
            $mode,
            $chain
        );
    if (!$validation["ok"])
    {
        @unlink($temporary);
        flock($lock, LOCK_UN);
        fclose($lock);
        return ($validation);
    }

    if ($currently_exists)
    {
        $stat = @stat($output["absolute"]);
        if (is_array($stat))
        {
            @chmod($temporary, $stat["mode"] & 0777);
            @chown($temporary, $stat["uid"]);
            @chgrp($temporary, $stat["gid"]);
        }
    }
    $override_content = json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($override_content === false)
    {
        @unlink($temporary);
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);
    }
    $override_content .= "\n";
    $temporary_override = $override_file.".tmp.".uniqid("", true);
    if (count($overrides) && file_put_contents($temporary_override, $override_content, LOCK_EX) !== strlen($override_content))
    {
        @unlink($temporary);
        @unlink($temporary_override);
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);
    }

    if (!@rename($temporary, $output["absolute"]))
    {
        @unlink($temporary);
        @unlink($temporary_override);
        flock($lock, LOCK_UN);
        fclose($lock);
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);
    }
    if (count($overrides))
    {
        if (!@rename($temporary_override, $override_file))
        {
            flock($lock, LOCK_UN);
            fclose($lock);
            return (["ok" => false, "error" => "DabsicFormCannotSave"]);
        }
    }
    else
        @unlink($override_file);

    clearstatcache(true, $output["absolute"]);
    $hash = hash("sha256", $built["content"]);
    $override_hash = count($overrides) ? hash("sha256", $override_content) : hash("sha256", "");
    flock($lock, LOCK_UN);
    fclose($lock);

    return ([
        "ok" => true,
        "reference" => $reference["relative"],
        "output" => $output["relative"],
        "hash" => $hash,
        "exists" => true,
        "overrides_hash" => $override_hash,
        "overrides_exists" => count($overrides) > 0,
        "fields" => $required,
        "mtime" => @filemtime($output["absolute"])
    ]);
}
