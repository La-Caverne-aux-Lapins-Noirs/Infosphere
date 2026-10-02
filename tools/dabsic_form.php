<?php

require_once (__DIR__."/dabsic_editor.php");
require_once (__DIR__."/document_context.php");
require_once (__DIR__."/billing.php");
require_once (__DIR__."/document_hash.php");
require_once (__DIR__."/form_field.php");
require_once (__DIR__."/internship_session_sync.php");

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

    // Temporary/manual completion created from the generic Documents page.
    // The symbolic key is the only value exposed to the browser; the actual
    // path remains constrained to Infosphere's document data directory.
    if (preg_match('/^document-page:([a-f0-9]{32}):([a-f0-9]{16})$/', (string)$key, $m))
        return ([
            "file" => "dres/doc/data/manual/".$m[1]."-".$m[2].".dab",
            "label" => "Complément manuel de document",
            "create_parent" => true
        ]);

    if (preg_match('/^prospect-convocation:(motivation-theory|practical):([0-9]+)$/', (string)$key, $m))
    {
        require_once (__DIR__."/prospect_convocation.php");
        $kind = (string)$m[1];
        $id_prospect = (int)$m[2];
        $spec = prospect_convocation_spec($kind);
        $prospect = document_context_user($id_prospect);
        if ($spec == NULL || !is_array($prospect) ||
            ($prospect["profile_status"] ?? "") != "prospect" || empty($prospect["codename"]))
            return (NULL);

        $user_root = realpath($Configuration->UsersDir($prospect["codename"]));
        if ($user_root === false || !is_dir($user_root))
            return (NULL);
        $directory = $user_root.DIRECTORY_SEPARATOR."admin/admission";
        $absolute_file = $directory.DIRECTORY_SEPARATOR.$spec["output"];

        return ([
            "absolute_file" => $absolute_file,
            "authorized_root" => $user_root,
            "label" => $spec["label"]." - ".trim(($prospect["first_name"] ?? "")." ".($prospect["family_name"] ?? "")),
            "create_parent" => true,
            "private_user_output" => true,
            "owner_user_id" => $id_prospect,
        ]);
    }


    if (preg_match('/^prospect-admission:(domestic|foreign):([0-9]+)$/', (string)$key, $m))
    {
        $kind = (string)$m[1];
        $id_prospect = (int)$m[2];
        $prospect = document_context_user($id_prospect);
        if (!is_array($prospect) || ($prospect["profile_status"] ?? "") != "prospect" ||
            empty($prospect["codename"]))
            return (NULL);

        $user_root = realpath($Configuration->UsersDir($prospect["codename"]));
        if ($user_root === false || !is_dir($user_root))
            return (NULL);
        $directory = $user_root.DIRECTORY_SEPARATOR."admin/admission";
        $absolute_file = $directory.DIRECTORY_SEPARATOR."attestation_admission_".$kind."_form.dab";

        return ([
            "absolute_file" => $absolute_file,
            "authorized_root" => $user_root,
            "label" => "Frais d'admission - ".trim(($prospect["first_name"] ?? "")." ".($prospect["family_name"] ?? "")),
            "create_parent" => true,
            "private_user_output" => true,
            "owner_user_id" => $id_prospect,
        ]);
    }

    if (preg_match('/^post-interview-report:([0-9]+)$/', (string)$key, $m))
    {
        $prospect = document_context_user((int)$m[1]);
        if (!is_array($prospect) || ($prospect["profile_status"] ?? "") != "prospect" ||
            empty($prospect["codename"]))
            return (NULL);

        $user_root = realpath($Configuration->UsersDir($prospect["codename"]));
        if ($user_root === false || !is_dir($user_root))
            return (NULL);

        $subscription_dir = $user_root.DIRECTORY_SEPARATOR."admin/subscription";
        $absolute_file = $subscription_dir.DIRECTORY_SEPARATOR."compte_rendu_post_entretien.dab";

        return ([
            "absolute_file" => $absolute_file,
            "authorized_root" => $user_root,
            "label" => "Compte rendu post-entretien - ".trim(($prospect["first_name"] ?? "")." ".($prospect["family_name"] ?? "")),
            "create_parent" => true,
            "private_user_output" => true,
            "owner_user_id" => (int)$m[1]
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
            "create_parent" => true,
            "owner_user_id" => $id_user
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

function dabsic_form_user_can_access_output($key)
{
    if (is_admin())
        return (true);

    // Manual completion from the Documents page follows the access rules of
    // that page. The output path itself is still resolved server-side.
    if (preg_match('/^document-page:[a-f0-9]{32}:[a-f0-9]{16}$/', (string)$key))
        return (
            am_i_cycle_director() ||
            is_director_for_school(-1) ||
            am_i_secretariat() ||
            am_i_commercial()
        );

    // Admission convocations use the same prospect-side authorization model
    // as the post-interview report.
    if (preg_match('/^prospect-convocation:(?:motivation-theory|practical):([0-9]+)$/', (string)$key, $m))
    {
        $prospect = document_context_user((int)$m[1]);
        if (!is_array($prospect) || ($prospect["profile_status"] ?? "") != "prospect")
            return (false);
        $school = document_context_first_school_for_user((int)$m[1]);
        $id_school = is_array($school) ? (int)($school["id_school"] ?? -1) : -1;
        return (
            is_director_for_school($id_school) ||
            is_secretariat_for_school($id_school) ||
            is_commercial_for_school($id_school)
        );
    }


    if (preg_match('/^prospect-admission:(?:domestic|foreign):([0-9]+)$/', (string)$key, $m))
    {
        $prospect = document_context_user((int)$m[1]);
        if (!is_array($prospect) || ($prospect["profile_status"] ?? "") != "prospect")
            return (false);
        $school = document_context_first_school_for_user((int)$m[1]);
        $id_school = is_array($school) ? (int)($school["id_school"] ?? -1) : -1;
        return (
            is_director_for_school($id_school) ||
            is_secretariat_for_school($id_school) ||
            is_commercial_for_school($id_school)
        );
    }

    // Post-interview reports are prospect-side admission documents.
    if (preg_match('/^post-interview-report:([0-9]+)$/', (string)$key, $m))
    {
        $prospect = document_context_user((int)$m[1]);
        if (!is_array($prospect) || ($prospect["profile_status"] ?? "") != "prospect")
            return (false);
        $school = document_context_first_school_for_user((int)$m[1]);
        $id_school = is_array($school) ? (int)($school["id_school"] ?? -1) : -1;
        return (
            is_director_for_school($id_school) ||
            is_secretariat_for_school($id_school) ||
            is_commercial_for_school($id_school)
        );
    }

    // Profile documentation already exposes completion to staff allowed to
    // manage the target learner.
    if (preg_match('/^user-document:([0-9]+):[a-f0-9]{32}:[0-5]$/', (string)$key, $m))
        return (is_director_for_student((int)$m[1]));

    return (false);
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
    foreach (document_builder_dabsic_hash_fields("") as $key => $value)
        $cmd .= " -m ".escapeshellarg($key."=".$value);
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
    foreach (array_keys(document_builder_dabsic_hash_fields("")) as $field)
        $defined[$field] = true;
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


function dabsic_form_extract_root_scope($content, $scope)
{
    if (!is_string($content) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', (string)$scope))
        return (NULL);
    $needle = '['.$scope;
    $start = strpos($content, $needle);
    if ($start === false)
        return (NULL);

    $depth = 0;
    $quoted = false;
    $escape = false;
    $length = strlen($content);
    for ($i = $start; $i < $length; ++$i)
    {
        $c = $content[$i];
        if ($quoted)
        {
            if ($escape)
                $escape = false;
            else if ($c === "\\")
                $escape = true;
            else if ($c === '"')
                $quoted = false;
            continue ;
        }
        if ($c === '"')
        {
            $quoted = true;
            continue ;
        }
        if ($c === '[')
            ++$depth;
        else if ($c === ']')
        {
            --$depth;
            if ($depth === 0)
                return (substr($content, $start, $i - $start + 1));
        }
    }
    return (NULL);
}

function dabsic_form_empty_form_metadata()
{
    return ([
        "fields" => [],
        "labels" => [],
        "groups" => [],
        "group_order" => [],
        "roles" => [],
        "field_errors" => [],
    ]);
}

function dabsic_form_metadata_string_list($value)
{
    if (!is_array($value))
        $value = $value === NULL || $value === "" ? [] : [$value];
    $out = [];
    foreach ($value as $entry)
    {
        if (is_array($entry) || is_object($entry))
            continue ;
        $entry = trim((string)$entry);
        if ($entry != "" && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $entry))
            $out[$entry] = true;
    }
    return (array_keys($out));
}

function dabsic_form_metadata_scalar_list($value)
{
    if ($value === NULL || $value === "")
        return ([]);
    if (!is_array($value))
        $value = [$value];
    $out = [];
    foreach ($value as $entry)
    {
        if (is_array($entry) || is_object($entry))
            continue ;
        $entry = trim((string)$entry);
        if ($entry !== "" && !in_array($entry, $out, true))
            $out[] = $entry;
    }
    return ($out);
}

function dabsic_form_metadata_scalar($value, $fallback = "")
{
    if (is_array($value) || is_object($value) || $value === NULL)
        return ((string)$fallback);
    return (trim((string)$value));
}

function dabsic_form_metadata_boolean($value)
{
    return (!is_array($value) && !is_object($value) && !empty($value));
}

function dabsic_form_metadata_is_scalar_sequence($value)
{
    if (!is_array($value))
        return (!is_object($value));
    foreach ($value as $entry)
        if (is_array($entry) || is_object($entry))
            return (false);
    return (true);
}

function dabsic_form_has_scalar_metadata(array $tree, $name)
{
    return (array_key_exists($name, $tree)
        && !is_array($tree[$name])
        && !is_object($tree[$name]));
}

function dabsic_form_parse_group_fields(array $tree, $prefix, $group, array &$metadata)
{
    foreach ($tree as $key => $child)
    {
        $key = (string)$key;
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key) || !is_array($child))
            continue ;
        $path = $prefix == "" ? $key : $prefix.".".$key;
        // A business field is allowed to be named Label, Type, Default, etc.
        // mergeconf then exposes that name as a nested array. Only scalar
        // metadata (or a flat choice sequence) identifies the current node as
        // a field; a nested scope bearing the same name must be traversed.
        $is_field = dabsic_form_has_scalar_metadata($child, "Label")
            || dabsic_form_has_scalar_metadata($child, "Required")
            || dabsic_form_has_scalar_metadata($child, "Default")
            || dabsic_form_has_scalar_metadata($child, "Readonly")
            || dabsic_form_has_scalar_metadata($child, "ReadonlyIfPrefilled")
            || dabsic_form_has_scalar_metadata($child, "RawLatex")
            || dabsic_form_has_scalar_metadata($child, "Type")
            || dabsic_form_has_scalar_metadata($child, "Points")
            || dabsic_form_has_scalar_metadata($child, "Policy")
            || dabsic_form_has_scalar_metadata($child, "Penalty")
            || (array_key_exists("Choices", $child)
                && dabsic_form_metadata_is_scalar_sequence($child["Choices"]))
            || (array_key_exists("ChoiceValues", $child)
                && dabsic_form_metadata_is_scalar_sequence($child["ChoiceValues"]));
        if ($is_field)
        {
            $field = dabsic_form_normalize_field($path);
            if ($field === NULL)
                continue ;
            $label = dabsic_form_metadata_scalar($child["Label"] ?? NULL, $field);
            if ($label == "")
                $label = $field;
            $field_definition = [
                "label" => $label,
                "group" => (string)$group,
                "required" => dabsic_form_metadata_boolean($child["Required"] ?? false),
                "default" => dabsic_form_metadata_scalar($child["Default"] ?? NULL),
                // Readonly is a field-level presentation/access constraint.
                // FormRole remains group-based, but a semantic context value
                // can thus be displayed inside an otherwise editable group.
                "readonly" => dabsic_form_metadata_boolean($child["Readonly"] ?? false),
                // Public contributors may fill a missing configured value, but
                // cannot overwrite identity/organisation data already known by
                // Infosphere. Staff editors are intentionally unaffected.
                "readonly_if_prefilled" => dabsic_form_metadata_boolean($child["ReadonlyIfPrefilled"] ?? false),
                // Form values are plain text by default. RawLatex is an
                // explicit, model-author-only escape hatch for the rare field
                // whose stored value is intentionally TeX markup. Never set
                // it on user-editable free-text fields.
                "raw_latex" => dabsic_form_metadata_boolean($child["RawLatex"] ?? false),
                // Type only becomes authoritative for document forms when it
                // was explicitly declared. Historical administrative fields
                // without Type keep their dedicated heuristic renderer.
                "type" => strtolower(dabsic_form_metadata_scalar($child["Type"] ?? NULL, "text")),
                "type_explicit" => dabsic_form_has_scalar_metadata($child, "Type"),
                "points" => is_numeric($child["Points"] ?? NULL) ? (float)$child["Points"] : NULL,
                "policy" => dabsic_form_metadata_scalar($child["Policy"] ?? NULL),
                "penalty" => is_numeric($child["Penalty"] ?? NULL) ? (float)$child["Penalty"] : NULL,
                // Virtual structured fields may refer to sibling Dabsic values.
                // The browser value is only a transport envelope; the save path
                // expands it back into the nested Dabsic tree.
                "start_field" => dabsic_form_metadata_scalar($child["StartField"] ?? NULL),
                "end_field" => dabsic_form_metadata_scalar($child["EndField"] ?? NULL),
                "summary_field" => dabsic_form_metadata_scalar($child["SummaryField"] ?? NULL),
                // Choices/ChoiceValues are paired sequences. Preserve their
                // order and duplicates so malformed definitions can be rejected
                // instead of being silently repaired during metadata parsing.
                "choices" => form_field_sequence($child["Choices"] ?? []),
                "choice_values" => form_field_sequence($child["ChoiceValues"] ?? []),
                "correct" => dabsic_form_metadata_scalar_list($child["Correct"] ?? []),
                "medals" => dabsic_form_metadata_scalar_list($child["Medals"] ?? []),
            ];
            if ($field_definition["type_explicit"] && form_field_is_common_type($field_definition["type"]))
            {
                $contract = form_field_definition($field_definition);
                $field_definition["type"] = $contract["type"];
                $field_definition["choices"] = $contract["choices"];
                $field_definition["choice_values"] = $contract["choice_values"];
                $field_definition["valid"] = $contract["valid"];
                $field_definition["errors"] = $contract["errors"];
                if (!$contract["valid"])
                    $metadata["field_errors"][$field] = $contract["errors"];
            }
            else
            {
                $field_definition["valid"] = true;
                $field_definition["errors"] = [];
            }
            $metadata["fields"][$field] = $field_definition;
            $metadata["labels"][$field] = $label;
            $metadata["groups"][$group]["fields"][] = $field;
            continue ;
        }
        dabsic_form_parse_group_fields($child, $path, $group, $metadata);
    }
}

function dabsic_form_form_metadata($reference)
{
    $content = @file_get_contents($reference);
    if ($content === false)
        return (dabsic_form_empty_form_metadata());

    // Form metadata is frequently inherited from a common @include (for
    // example .base_relance_suivi/base.dab).  The old parser only inspected
    // the leaf model, so such documents had a perfectly valid FormGroup in
    // Dabsic but appeared to Infosphere as having no form at all.
    $metadata_sources = dabsic_form_docbuilder_collect_sources($reference);
    $metadata = dabsic_form_empty_form_metadata();

    $group_scope = dabsic_form_extract_root_scope($content, "FormGroup");
    $group_reference = $reference;
    if ($group_scope === NULL)
        foreach ($metadata_sources as $source_path => $source_content)
        {
            if ($source_path === realpath($reference)
                || strtolower(pathinfo($source_path, PATHINFO_EXTENSION)) !== "dab")
                continue ;
            $candidate = dabsic_form_extract_root_scope($source_content, "FormGroup");
            if ($candidate !== NULL)
            {
                $group_scope = $candidate;
                $group_reference = $source_path;
                break ;
            }
        }
    if ($group_scope !== NULL)
    {
        $command = "mergeconf";
        foreach (dabsic_form_include_paths($group_reference) as $path)
            $command .= " -I ".escapeshellarg($path);
        $command .= " -if .dabsic -of .json";
        $process = dabsic_form_process($command, $group_scope."\n");
        if ($process["status"] === 0)
        {
            $data = json_decode($process["stdout"], true);
            $root = is_array($data) && isset($data["FormGroup"]) && is_array($data["FormGroup"])
                ? $data["FormGroup"] : [];
            foreach ($root as $group => $tree)
            {
                $group = (string)$group;
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $group) || !is_array($tree))
                    continue ;
                $label = dabsic_form_metadata_scalar($tree["Label"] ?? NULL, $group);
                if ($label == "")
                    $label = $group;
                $metadata["groups"][$group] = [
                    "label" => $label,
                    "fields" => [],
                    "minimum_percent" => is_numeric($tree["MinimumPercent"] ?? NULL)
                        ? (float)$tree["MinimumPercent"] : NULL,
                    "medals" => dabsic_form_metadata_scalar_list($tree["Medals"] ?? []),
                    "exclude_models" => dabsic_form_metadata_string_list($tree["ExcludeModels"] ?? []),
                ];
                $metadata["group_order"][] = $group;
                $fields = isset($tree["Fields"]) && is_array($tree["Fields"])
                    ? $tree["Fields"] : [];
                dabsic_form_parse_group_fields($fields, "", $group, $metadata);
            }
        }
    }

    $role_scope = dabsic_form_extract_root_scope($content, "FormRole");
    $role_reference = $reference;
    if ($role_scope === NULL)
        foreach ($metadata_sources as $source_path => $source_content)
        {
            if ($source_path === realpath($reference)
                || strtolower(pathinfo($source_path, PATHINFO_EXTENSION)) !== "dab")
                continue ;
            $candidate = dabsic_form_extract_root_scope($source_content, "FormRole");
            if ($candidate !== NULL)
            {
                $role_scope = $candidate;
                $role_reference = $source_path;
                break ;
            }
        }
    if ($role_scope !== NULL)
    {
        $command = "mergeconf";
        foreach (dabsic_form_include_paths($role_reference) as $path)
            $command .= " -I ".escapeshellarg($path);
        $command .= " -if .dabsic -of .json";
        $process = dabsic_form_process($command, $role_scope."\n");
        if ($process["status"] === 0)
        {
            $data = json_decode($process["stdout"], true);
            $root = is_array($data) && isset($data["FormRole"]) && is_array($data["FormRole"])
                ? $data["FormRole"] : [];
            foreach ($root as $role => $tree)
            {
                $role = (string)$role;
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $role) || !is_array($tree))
                    continue ;
                $label = dabsic_form_metadata_scalar($tree["Label"] ?? NULL, $role);
                if ($label == "")
                    $label = $role;
                $definition = [
                    "label" => $label,
                    "required" => dabsic_form_metadata_boolean($tree["Required"] ?? false),
                    "required_if_recipient" => dabsic_form_metadata_boolean($tree["RequiredIfRecipient"] ?? false),
                    // Internal roles are editing views for staff. They never
                    // create a public contribution or block the workflow.
                    "internal" => dabsic_form_metadata_boolean($tree["Internal"] ?? false),
                    "recipient" => dabsic_form_metadata_scalar($tree["Recipient"] ?? NULL),
                    // If a declared recipient exists but cannot receive mail,
                    // the document may explicitly delegate that contribution
                    // to another semantic recipient (typically Student).
                    "fallback_recipient" => dabsic_form_metadata_scalar($tree["FallbackRecipient"] ?? NULL),
                    // ProfileScope means that fields under this Dabsic scope
                    // are also persisted back to the real recipient profile.
                    "profile_scope" => dabsic_form_metadata_scalar($tree["ProfileScope"] ?? NULL),
                    "profile_scopes" => dabsic_form_metadata_string_list($tree["ProfileScopes"] ?? []),
                    "depends_on" => dabsic_form_metadata_string_list($tree["DependsOn"] ?? []),
                    "read" => dabsic_form_metadata_string_list($tree["Read"] ?? []),
                    "edit" => dabsic_form_metadata_string_list($tree["Edit"] ?? []),
                    "validate" => dabsic_form_metadata_string_list($tree["Validate"] ?? []),
                ];
                // Edit and Validate always imply Read. Unknown group names are
                // ignored so a typo in ACL metadata cannot expose fields.
                $read = [];
                foreach (array_merge($definition["read"], $definition["edit"], $definition["validate"]) as $group)
                    if (isset($metadata["groups"][$group]))
                        $read[$group] = true;
                $definition["read"] = array_keys($read);
                $definition["edit"] = array_values(array_filter($definition["edit"], function($group) use ($metadata) {
                    return (isset($metadata["groups"][$group]));
                }));
                $definition["validate"] = array_values(array_filter($definition["validate"], function($group) use ($metadata) {
                    return (isset($metadata["groups"][$group]));
                }));
                $contribution_groups = array_unique(array_merge($definition["edit"], $definition["validate"]));
                $definition["has_contribution"] = false;
                foreach ($contribution_groups as $group)
                    if (count($metadata["groups"][$group]["fields"] ?? []))
                    {
                        $definition["has_contribution"] = true;
                        break ;
                    }
                $metadata["roles"][$role] = $definition;
            }
        }
    }
    return ($metadata);
}

function dabsic_form_role_definition(array $metadata, $role)
{
    $role = trim((string)$role);
    return (isset($metadata["roles"][$role]) && is_array($metadata["roles"][$role])
        ? $metadata["roles"][$role] : NULL);
}


function dabsic_form_staff_role(array $metadata)
{
    foreach (($metadata["roles"] ?? []) as $role => $definition)
        if (is_array($definition) && !empty($definition["internal"]))
            return ((string)$role);
    // Compatibility with documents authored before Internal=1 existed.
    return (dabsic_form_role_definition($metadata, "Etablissement") != NULL ? "Etablissement" : "");
}

function dabsic_form_role_has_contribution(array $definition)
{
    if (!empty($definition["internal"]))
        return (false);
    if (array_key_exists("has_contribution", $definition))
        return (!empty($definition["has_contribution"]));
    // Persisted invitations created before this metadata existed still carry
    // their Edit/Validate ACLs. Preserve their workflow semantics.
    return (count($definition["edit"] ?? []) > 0 || count($definition["validate"] ?? []) > 0);
}

function dabsic_form_role_groups(array $metadata, $role, $access = "read")
{
    $definition = dabsic_form_role_definition($metadata, $role);
    if ($definition == NULL)
        return ([]);
    $access = strtolower(trim((string)$access));
    if (!in_array($access, ["read", "edit", "validate"], true))
        return ([]);
    return (isset($definition[$access]) && is_array($definition[$access])
        ? $definition[$access] : []);
}

function dabsic_form_role_fields(array $metadata, $role, $access = "read")
{
    $access = strtolower(trim((string)$access));
    $groups = array_flip(dabsic_form_role_groups($metadata, $role, $access));
    $out = [];
    foreach (($metadata["fields"] ?? []) as $field => $definition)
        if (isset($groups[$definition["group"] ?? ""]) &&
            !($access === "edit" && !empty($definition["readonly"])))
            $out[] = (string)$field;
    return ($out);
}

function dabsic_form_field_value(array $values, $field, array $definition = [])
{
    $type = strtolower(trim((string)($definition["type"] ?? "")));
    if ($type === "internship_calendar")
        return (internship_calendar_payload_from_flat((string)$field, $definition, $values));
    return (array_key_exists($field, $values) ? $values[$field] : "");
}

function dabsic_form_expand_virtual_fields(array $metadata, array $submitted, array $context)
{
    $values = $submitted;
    $remove_prefixes = [];
    $remove_fields = [];
    foreach ($submitted as $field => $raw)
    {
        $definition = $metadata["fields"][$field] ?? NULL;
        if (!is_array($definition) || strtolower(trim((string)($definition["type"] ?? ""))) !== "internship_calendar")
            continue ;
        $expanded = internship_calendar_expand_submission($field, $definition, $raw, $context);
        if (!$expanded["ok"])
            return ($expanded);
        unset($values[$field]);
        foreach ($expanded["values"] as $path => $value)
            $values[$path] = $value;
        $remove_prefixes[] = (string)$expanded["remove_prefix"];
        $remove_fields[] = (string)$field;
        if (trim((string)($expanded["summary_field"] ?? "")) !== "")
            $remove_fields[] = (string)$expanded["summary_field"];
    }
    return ([
        "ok" => true,
        "values" => $values,
        "remove_prefixes" => array_values(array_unique($remove_prefixes)),
        "remove_fields" => array_values(array_unique($remove_fields)),
    ]);
}

function dabsic_form_remove_virtual_storage(array $values, array $expanded)
{
    foreach (array_keys($values) as $path)
    {
        foreach (($expanded["remove_prefixes"] ?? []) as $prefix)
            if (strncmp((string)$path, (string)$prefix, strlen((string)$prefix)) === 0)
            {
                unset($values[$path]);
                continue 2;
            }
        if (in_array((string)$path, $expanded["remove_fields"] ?? [], true))
            unset($values[$path]);
    }
    return ($values);
}

function dabsic_form_missing_required_fields(array $metadata, array $values, $role = "")
{
    $groups = NULL;
    $role = trim((string)$role);
    if ($role != "")
        $groups = array_flip(dabsic_form_role_groups($metadata, $role, "validate"));
    $out = [];
    foreach (($metadata["fields"] ?? []) as $field => $definition)
    {
        if (empty($definition["required"]))
            continue ;
        if ($groups !== NULL && !isset($groups[$definition["group"] ?? ""]))
            continue ;
        $value = dabsic_form_field_value($values, $field, $definition);
        if (form_field_missing_required($definition, $value))
            $out[$field] = (string)($definition["label"] ?? $field);
    }
    return ($out);
}

function dabsic_form_default_current_user_value($property)
{
    global $User;

    $id_user = isset($User["id"]) ? (int)$User["id"] : 0;
    if ($id_user <= 0 || !function_exists("document_context_person"))
        return (NULL);
    $person = document_context_person($id_user);
    if (!is_array($person))
        return (NULL);

    $property = strtolower(trim((string)$property));
    foreach ($person as $key => $value)
        if (strtolower((string)$key) === $property && !is_array($value) && !is_object($value))
            return ((string)$value);
    return (NULL);
}

/** Resolve field-level defaults for the authenticated staff-side editor. */
function dabsic_form_default_values($reference, $role = "")
{
    $metadata = dabsic_form_form_metadata($reference);
    $editable = trim((string)$role) != ""
        ? array_flip(dabsic_form_role_fields($metadata, $role, "edit")) : NULL;
    $out = [];
    foreach (($metadata["fields"] ?? []) as $field => $definition)
    {
        if ($editable !== NULL && !isset($editable[$field]))
            continue ;
        $marker = trim((string)($definition["default"] ?? ""));
        if ($marker == "")
            continue ;
        $value = NULL;
        if ($marker === "@Today")
            $value = date("d/m/Y");
        else if (preg_match('/^@CurrentUser\.([A-Za-z_][A-Za-z0-9_]*)$/D', $marker, $match))
            $value = dabsic_form_default_current_user_value($match[1]);
        if ($value !== NULL)
            $out[$field] = (string)$value;
    }
    return ($out);
}

function dabsic_form_form_labels($reference)
{
    $metadata = dabsic_form_form_metadata($reference);
    return ($metadata["labels"]);
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

    // A DocBuilder model can declare its editable fields explicitly through
    // FormGroup/FormRole.  In that case mergeconf may legitimately have no
    // "missing variable" diagnostic to parse (and some deployed versions
    // still emit harmless tree-lookup diagnostics such as "... School child
    // -> (nil)").  Do not turn those diagnostics into a fatal error when the
    // form contract itself already tells us which fields must be displayed.
    $form_metadata = dabsic_form_form_metadata($reference["absolute"]);
    if (trim($process["stderr"]) !== "" && !count($fields)
        && !count($form_metadata["fields"] ?? []))
        return ([
            "ok" => false,
            "error" => "DabsicFormCannotExtractFields",
            "details" => dabsic_form_clean_diagnostic($process["stderr"])
        ]);
    if (count($form_metadata["field_errors"] ?? []))
    {
        $details = [];
        foreach ($form_metadata["field_errors"] as $field => $errors)
            $details[] = $field.": ".implode(", ", $errors);
        return ([
            "ok" => false,
            "error" => "DabsicFormInvalidFieldDefinition",
            "details" => implode("\n", $details),
        ]);
    }
    return ([
        "ok" => true,
        "reference" => $reference,
        "fields" => $fields,
        "labels" => $form_metadata["labels"],
        "form_metadata" => $form_metadata,
        "warnings" => dabsic_form_clean_diagnostic($process["stderr"])
    ]);
}

function dabsic_form_flatten_values($value, $prefix = "", &$out = NULL)
{
    if ($out === NULL)
        $out = [];
    if (is_array($value))
    {
        $is_list = $value === [] || array_keys($value) === range(0, count($value) - 1);
        if ($is_list && $prefix !== "")
        {
            $list = [];
            foreach ($value as $entry)
                if (!is_array($entry) && !is_object($entry))
                    $list[] = is_bool($entry) ? ($entry ? "true" : "false") : (string)($entry ?? "");
            $out[$prefix] = $list;
            return ($out);
        }
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
        if (array_key_exists($key, document_builder_dabsic_hash_fields("")))
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


function dabsic_form_workspace_file(array $output)
{
    $absolute = trim((string)($output["absolute"] ?? ""));
    if ($absolute == "")
        return ("");
    return (
        dirname($absolute).DIRECTORY_SEPARATOR.
        ".workspace-".pathinfo($absolute, PATHINFO_FILENAME).".json"
    );
}

function dabsic_form_load_workspace(array $output)
{
    $file = dabsic_form_workspace_file($output);
    if ($file == "" || !is_file($file))
        return (["ok" => true, "exists" => false, "data" => [], "mtime" => 0]);
    $content = @file_get_contents($file);
    if ($content === false)
        return (["ok" => false, "error" => "DabsicFormCannotReadOutput"]);
    $data = json_decode($content, true);
    if (!is_array($data))
        return (["ok" => false, "error" => "DabsicFormInvalidOutputFile"]);
    return ([
        "ok" => true,
        "exists" => true,
        "data" => $data,
        "mtime" => @filemtime($file) ?: 0,
    ]);
}

function dabsic_form_save_workspace(array $output, array $data)
{
    $file = dabsic_form_workspace_file($output);
    if ($file == "")
        return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);
    $data["updated_at"] = date("Y-m-d H:i:s");
    if (!isset($data["created_at"]) || trim((string)$data["created_at"]) == "")
        $data["created_at"] = $data["updated_at"];
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false)
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);
    $json .= "\n";
    if (file_put_contents($file, $json, LOCK_EX) !== strlen($json))
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);
    @chmod($file, 0640);
    return (["ok" => true, "file" => $file, "mtime" => @filemtime($file) ?: time(), "data" => $data]);
}

function dabsic_form_delete_workspace(array $output)
{
    $file = dabsic_form_workspace_file($output);
    if ($file != "" && is_file($file) && !@unlink($file))
        return (false);
    return (true);
}

function dabsic_form_reset_output_values(array $output)
{
    $files = [(string)($output["absolute"] ?? ""), dabsic_form_override_file($output)];
    foreach ($files as $file)
        if ($file != "" && is_file($file) && !@unlink($file))
            return (["ok" => false, "error" => "DabsicFormCannotSave", "details" => $file]);
    return (["ok" => true]);
}

function dabsic_form_reset_output_values_and_sessions(array $output, $output_key, $reason = "reset")
{
    $files = [(string)($output["absolute"] ?? ""), dabsic_form_override_file($output)];
    $backup = [];
    foreach ($files as $file)
    {
        if ($file === "")
            continue ;
        $exists = is_file($file);
        $content = $exists ? @file_get_contents($file) : "";
        if ($exists && $content === false)
            return (["ok" => false, "error" => "DabsicFormCannotReadOutput", "details" => $file]);
        $backup[$file] = ["exists" => $exists, "content" => (string)$content];
    }

    $reset = dabsic_form_reset_output_values($output);
    if (!$reset["ok"])
        return ($reset);

    $sync = internship_session_sync_deactivate_output($output_key, $reason);
    if ($sync["ok"])
        return (["ok" => true, "session_sync" => $sync]);

    $restore_ok = true;
    foreach ($backup as $file => $state)
    {
        if ($state["exists"])
            $restore_ok = (@file_put_contents($file, $state["content"], LOCK_EX) === strlen($state["content"])) && $restore_ok;
        else if (is_file($file))
            $restore_ok = @unlink($file) && $restore_ok;
    }
    if (!$restore_ok)
        add_log(REPORT, "Cannot restore Dabsic output after internship session lifecycle failure: ".(string)($output["relative"] ?? $output_key));
    return ($sync);
}

function dabsic_form_write_output_values(array $output, array $values)
{
    if (!count($values))
        return (dabsic_form_reset_output_values($output));
    ksort($values, SORT_NATURAL | SORT_FLAG_CASE);
    $built = dabsic_form_build_dabsic(array_keys($values), $values);
    if (!$built["ok"])
        return ($built);
    $absolute = (string)($output["absolute"] ?? "");
    if ($absolute == "")
        return (["ok" => false, "error" => "DabsicFormInvalidOutput"]);
    $parent = dirname($absolute);
    if (!is_dir($parent))
        @mkdir($parent, 0770, true);
    if (!is_dir($parent) || !is_writable($parent))
        return (["ok" => false, "error" => "DabsicFormOutputReadOnly"]);
    $temporary = tempnam($parent, ".infosphere-form-");
    if ($temporary === false)
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);
    $content = $built["content"];
    if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content) || !@rename($temporary, $absolute))
    {
        @unlink($temporary);
        return (["ok" => false, "error" => "DabsicFormCannotSave"]);
    }
    @chmod($absolute, 0640);
    clearstatcache(true, $absolute);
    return ([
        "ok" => true,
        "hash" => hash("sha256", $content),
        "mtime" => @filemtime($absolute) ?: time(),
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
            if (is_array($node) && isset($node[$part]) && is_array($node[$part]) && !is_array($value))
                return (false);
            if (is_array($value))
            {
                $clean = [];
                foreach ($value as $entry)
                    if (!is_array($entry) && !is_object($entry))
                        $clean[] = (string)$entry;
                $node[$part] = $clean;
            }
            else
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
        if (!is_scalar($values[$field]) && $values[$field] !== NULL && !is_array($values[$field]))
            return (["ok" => false, "error" => "InvalidParameter", "details" => $field]);
        $field_value = $values[$field] === NULL ? "" : $values[$field];
        if (!dabsic_form_assign_value($tree, $field, $field_value))
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
            // mergeconf infers the input format from the file extension. tempnam()
            // creates an extension-less path, which mergeconf cannot load as Dabsic.
            $temporary_base = tempnam(sys_get_temp_dir(), "infosphere_dabsic_form_context_");
            $temporary_context = $temporary_base === false ? false : $temporary_base.".dab";
            if ($temporary_base === false || !@rename($temporary_base, $temporary_context) ||
                file_put_contents($temporary_context, $built["content"], LOCK_EX) !== strlen($built["content"]))
            {
                if ($temporary_base !== false)
                    @unlink($temporary_base);
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

function dabsic_form_post_interview_financial_values($output_key, array $values)
{
    if (!preg_match('/^post-interview-report:([0-9]+)$/', (string)$output_key, $m))
        return (["ok" => true, "values" => $values]);

    if (!array_key_exists("InterviewReport.TariffTemplateId", $values) &&
        !array_key_exists("InterviewReport.ForeignStudent", $values))
        return (["ok" => true, "values" => $values]);

    $financial_fields = [
        "InterviewReport.TariffTemplateName",
        "InterviewReport.TuitionPrice",
        "InterviewReport.TotalPrice",
        "InterviewReport.RegistrationFee",
        "InterviewReport.ForeignTuitionAdvance",
        "InterviewReport.AmountDueAtRegistration",
        "InterviewReport.TuitionBalance",
    ];
    $id_template = (int)($values["InterviewReport.TariffTemplateId"] ?? 0);
    $foreign_value = trim((string)($values["InterviewReport.ForeignStudent"] ?? ""));

    if ($id_template <= 0 || $foreign_value === "")
    {
        foreach ($financial_fields as $field)
            $values[$field] = "";
        return (["ok" => true, "values" => $values]);
    }
    if (!in_array($foreign_value, ["0", "1"], true))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "InterviewReport.ForeignStudent"]);

    $school = document_context_first_school_for_user((int)$m[1]);
    $id_school = is_array($school) ? (int)($school["id_school"] ?? 0) : 0;
    if ($id_school <= 0)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "prospect school"]);

    $template = db_select_one("
        *
        FROM billing_template
        WHERE id = $id_template
          AND id_school = $id_school
          AND invoice_type = 'school'
          AND deleted IS NULL
    ");
    if ($template == NULL)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "InterviewReport.TariffTemplateId"]);

    $amounts = billing_admission_amounts($template, $foreign_value === "1");
    if (!is_array($amounts))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "billing tariff"]);

    $values["InterviewReport.TariffTemplateName"] = (string)($template["name"] ?? "");
    $values["InterviewReport.TuitionPrice"] = $amounts["tuition"];
    $values["InterviewReport.TotalPrice"] = $amounts["total_price"];
    $values["InterviewReport.RegistrationFee"] = $amounts["registration_fee"];
    $values["InterviewReport.ForeignTuitionAdvance"] = $amounts["foreign_advance"];
    $values["InterviewReport.AmountDueAtRegistration"] = $amounts["due_at_registration"];
    $values["InterviewReport.TuitionBalance"] = $amounts["tuition_balance"];
    return (["ok" => true, "values" => $values]);
}

function dabsic_form_internship_derived_values(array $metadata, array $context)
{
    $calendar_field = "Internship.ScheduleCalendar";
    $definition = $metadata["fields"][$calendar_field] ?? NULL;
    if (!is_array($definition) || strtolower(trim((string)($definition["type"] ?? ""))) !== "internship_calendar")
        return ([]);

    if (array_key_exists($calendar_field, $context))
        $raw = $context[$calendar_field];
    else
        $raw = internship_calendar_payload_from_flat($calendar_field, $definition, $context);

    $morning_hours = 3.5;
    $afternoon_hours = 3.5;
    if (function_exists("internship_session_plan_settings"))
    {
        $settings = internship_session_plan_settings();
        if (is_array($settings))
        {
            $morning_hours = internship_calendar_hours_between_times($settings["morning_start"], $settings["morning_end"], 3.5);
            $afternoon_hours = internship_calendar_hours_between_times($settings["afternoon_start"], $settings["afternoon_end"], 3.5);
        }
    }
    $metrics = internship_calendar_work_metrics($raw, $morning_hours, $afternoon_hours);
    if ($metrics === NULL)
        return ([]);

    $out = [];
    $put = function ($field, $value) use (&$out, $metadata) {
        if (isset($metadata["fields"][$field]))
            $out[$field] = (string)$value;
    };
    $put("Internship.DurationInDay", internship_calendar_format_number($metrics["days"], 2, true));
    $put("Internship.HourPerDay", internship_calendar_format_number($metrics["hours_per_day"], 2, true));
    $put("Internship.DayPerWeek", internship_calendar_format_number($metrics["days_per_week"], 2, true));
    $put("Internship.DurationInHour", internship_calendar_format_number($metrics["hours"], 2, true));

    $hourly_raw = $context["Internship.HourlyPayment"] ?? "";
    $hourly = internship_calendar_decimal_value($hourly_raw);
    if ($hourly !== NULL)
    {
        if ($hourly > 0)
        {
            $put("Internship.Paid", "Oui");
            $put("Internship.TotalPayment", internship_calendar_format_number($metrics["hours"] * $hourly, 2, false));
            $current_payment = trim((string)($context["Internship.Paiement"] ?? ""));
            if ($current_payment === "" || strpos($current_payment, internship_calendar_payment_prefix()) === 0)
                $put("Internship.Paiement", internship_calendar_payment_schedule($metrics, $hourly));
        }
        else
        {
            $put("Internship.Paid", "Non");
            $put("Internship.TotalPayment", "0,00");
            $current_payment = trim((string)($context["Internship.Paiement"] ?? ""));
            if ($current_payment === "" || strpos($current_payment, internship_calendar_payment_prefix()) === 0)
                $put("Internship.Paiement", "");
        }
    }
    else
    {
        $put("Internship.TotalPayment", "");
        $current_payment = trim((string)($context["Internship.Paiement"] ?? ""));
        if (strpos($current_payment, internship_calendar_payment_prefix()) === 0)
            $put("Internship.Paiement", "");
    }
    return ($out);
}

function dabsic_form_save($requested_reference, $output_key, $values, $overrides, $reference_hash, $output_hash, $output_exists, $overrides_hash, $overrides_exists, $mode = "dabsic", $chain = "", $trusted_user_document_id = NULL, $allow_partial = false, $form_role = "")
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

    $form_role = trim((string)$form_role);
    $metadata = $discovery["form_metadata"] ?? dabsic_form_empty_form_metadata();
    foreach ($values as $field => $value)
    {
        $definition = $metadata["fields"][$field] ?? NULL;
        if (is_array($definition) && form_field_is_common_type($definition["type"] ?? ""))
            $values[$field] = form_field_storage_value($definition, $value);
    }
    $normalized_values = dabsic_form_post_interview_financial_values($output_key, $values);
    if (!$normalized_values["ok"])
        return ($normalized_values);
    $submitted_values = $normalized_values["values"];
    $submitted_fields = array_keys($submitted_values);
    $required = $discovery["fields"];

    if ($form_role != "")
    {
        if (dabsic_form_role_definition($metadata, $form_role) == NULL)
            return (["ok" => false, "error" => "PermissionDenied", "details" => $form_role]);
        $allowed_fields = array_flip(dabsic_form_role_fields($metadata, $form_role, "edit"));
        foreach ($submitted_fields as $field)
            if (!isset($allowed_fields[$field]))
                return (["ok" => false, "error" => "PermissionDenied", "details" => $field]);
        $loaded_values = dabsic_form_load_output_values($output);
        if (!$loaded_values["ok"])
            return ($loaded_values);

        // Attendance duration and gratification are consequences of the
        // authoritative stage calendar. They are recalculated server-side so
        // a stale browser value (or a hand-edited request) cannot make the
        // convention internally inconsistent.
        $derived = dabsic_form_internship_derived_values(
            $metadata,
            array_merge($loaded_values["values"], $submitted_values)
        );
        foreach ($derived as $field => $value)
            $submitted_values[$field] = $value;

        // Expand virtual controls only after permission checking.  Their child
        // paths are implementation details and must never need to appear in a
        // FormRole declaration.  Existing children are replaced atomically so
        // shortening/changing the date range cannot leave stale Dabsic days.
        $virtual = dabsic_form_expand_virtual_fields(
            $metadata,
            $submitted_values,
            array_merge($loaded_values["values"], $submitted_values)
        );
        if (!$virtual["ok"])
            return ($virtual);
        $base_values = dabsic_form_remove_virtual_storage($loaded_values["values"], $virtual);
        $values = array_merge($base_values, $virtual["values"]);
        $build_fields = array_keys($values);
        $allow_partial = true;
    }
    else
    {
        natcasesort($submitted_fields);
        $submitted_fields = array_values($submitted_fields);
        $expected_fields = $required;
        natcasesort($expected_fields);
        $expected_fields = array_values($expected_fields);
        if (!$allow_partial && $submitted_fields !== $expected_fields)
            return (["ok" => false, "error" => "DabsicFormChanged"]);
        if ($allow_partial)
            foreach ($submitted_fields as $field)
                if (!in_array($field, $expected_fields, true))
                    return (["ok" => false, "error" => "DabsicFormChanged", "details" => $field]);
        $build_fields = $allow_partial ? $submitted_fields : $required;
    }

    $built = dabsic_form_build_dabsic($build_fields, $values);
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
    // Role-scoped document forms are intentionally partial: each actor only
    // writes the groups granted by FormRole. Full resolution is deferred until
    // the document is finalized.
    if ($validation["ok"] && !$allow_partial)
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

    // Internship sessions are deliberately NOT materialized while the Dabsic
    // form is still a draft.  The form remains freely editable until the
    // document finalization path validates and freezes the convention.  Only
    // that finalization path is allowed to synchronize ScheduleCalendar into
    // session rows (see api/doc.php).

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
