<?php

require_once ("./tools/document_sources.php");
require_once ("./tools/document_context.php");
require_once ("./tools/dabsic_form.php");
require_once ("./tools/document_workflow.php");
require_once ("./tools/document_print.php");



function document_upload_distrans_reason($response)
{
    if (!is_array($response))
        return ("");

    foreach (["message", "msg", "error", "content"] as $field)
        if (isset($response[$field]) && trim((string)$response[$field]) != "")
            return (trim((string)$response[$field]));

    return ("");
}


function document_generation_log_raw_output($label, $raw)
{
    $internal = trim((string)$raw);

    if ($internal == "")
        return ;
    error_log("[Infosphere document generation][$label] ".str_replace("\n", " | ", $internal));
}

function document_generation_public_error($raw)
{
    $internal = trim((string)$raw);
    document_generation_log_raw_output("visible", $internal);

    $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $internal);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/\n?Raw output:\s*.*$/si', '', $text);
    $text = preg_replace('/\n?Raw configuration:\s*.*$/si', '', $text);
    $text = preg_replace('/\n?Raw JSON:\s*.*$/si', '', $text);

    $out = [];
    $sql_block = false;
    foreach (explode("\n", $text) as $line)
    {
        $line = trim($line);
        if ($line == "")
            continue ;
        if (preg_match('/^-{8,}$/', $line))
        {
            $sql_block = !$sql_block;
            continue ;
        }
        if ($sql_block)
        {
            if (!preg_match('/#>\s*(.*)$/', $line, $m))
                continue ;
            $line = trim($m[1]);
            if ($line == "" || preg_match('/\.php:\d+$/', $line))
                continue ;
            $out[] = "Erreur SQL pendant la préparation des données: ".$line;
            continue ;
        }
        if (preg_match('/#>\s*(.*)$/', $line, $m))
            $line = trim($m[1]);
        if ($line == "" || preg_match('/\.php:\d+$/', $line) || preg_match('#^/[^ ]+\.php:\d+$#', $line))
            continue ;
        if (preg_match('/^\s*[\{\}\[\],]/', $line))
            continue ;
        if (stripos($line, 'Invalid JSON from mergeconf') !== false)
        {
            $out[] = "Le document Dabsic produit une configuration invalide.";
            continue ;
        }
        $line = str_ireplace('mergeconf', 'Dabsic', $line);
        $line = str_ireplace('JSON', 'configuration', $line);
        $line = preg_replace('/\s+/', ' ', $line);
        $out[] = $line;
    }

    $out = array_values(array_unique($out));
    if (!count($out))
        $out[] = "Le document n'a pas pu être généré.";

    // Les diagnostics utiles sont souvent à la fin de stderr : exception
    // fatale, directive inconnue, dernière frame de la trace, etc. Ne garder
    // que les premières lignes masquait précisément la cause réelle.
    $important = [];
    $ordinary = [];
    foreach ($out as $line)
    {
        if (preg_match('/(?:fatal error|uncaught|unknown directive|exception|parse error|syntax error)/i', $line))
            $important[] = $line;
        else
            $ordinary[] = $line;
    }

    $selected = array_slice($ordinary, 0, 4);
    foreach ($important as $line)
        if (!in_array($line, $selected, true))
            $selected[] = $line;
    foreach (array_slice($ordinary, -8) as $line)
        if (!in_array($line, $selected, true))
            $selected[] = $line;

    return (implode("\n", $selected));
}

function document_generation_full_process_output(array $processes)
{
    $out = [];

    // DocBuilder est généralement le dernier processus et sa fin de stderr
    // contient l'exception décisive. On l'affiche donc avant les diagnostics
    // antérieurs de mergeconf.
    foreach (array_reverse($processes, true) as $label => $process)
    {
        if (!is_array($process))
            continue ;
        $stderr = trim((string)($process["stderr"] ?? ""));
        $stdout = trim((string)($process["stdout"] ?? ""));
        if ($stderr != "")
            $out[] = "--- ".$label." stderr ---\n".$stderr;
        if ($stdout != "")
            $out[] = "--- ".$label." stdout ---\n".$stdout;
    }
    return (implode("\n\n", $out));
}

function document_generation_run_command($cmd)
{
    $stdout_file = tempnam(sys_get_temp_dir(), "infosphere_doc_stdout_");
    $stderr_file = tempnam(sys_get_temp_dir(), "infosphere_doc_stderr_");

    if ($stdout_file === false || $stderr_file === false)
    {
        if ($stdout_file !== false)
            @unlink($stdout_file);
        if ($stderr_file !== false)
            @unlink($stderr_file);
        return ([
            "status" => 127,
            "stdout" => "",
            "stderr" => "Impossible de créer les fichiers temporaires nécessaires à la génération du document."
        ]);
    }

    $lines = [];
    $status = 0;
    exec($cmd." > ".escapeshellarg($stdout_file)." 2> ".escapeshellarg($stderr_file), $lines, $status);

    $stdout = file_exists($stdout_file) ? file_get_contents($stdout_file) : "";
    $stderr = file_exists($stderr_file) ? file_get_contents($stderr_file) : "";
    @unlink($stdout_file);
    @unlink($stderr_file);

    return ([
        "status" => $status,
        "stdout" => $stdout === false ? "" : $stdout,
        "stderr" => $stderr === false ? "" : $stderr
    ]);
}

function document_generation_process_error($process)
{
    if (trim($process["stdout"]) != "")
        document_generation_log_raw_output("stdout", $process["stdout"]);
    if (trim($process["stderr"]) != "")
        return (document_generation_public_error($process["stderr"]));
    return (document_generation_public_error($process["stdout"]));
}

function document_generation_process_combined_error($processes)
{
    $public = [];

    foreach (array_reverse($processes, true) as $label => $process)
    {
        if (!is_array($process))
            continue ;
        if (trim($process["stdout"]) != "")
            document_generation_log_raw_output($label." stdout", $process["stdout"]);
        if (trim($process["stderr"]) != "")
            $public[] = $process["stderr"];
    }
    if (count($public))
        return (document_generation_public_error(implode("\n", $public)));

    foreach ($processes as $label => $process)
    {
        if (!is_array($process))
            continue ;
        if (trim($process["stdout"]) != "")
            return (document_generation_public_error($process["stdout"]));
    }
    return (document_generation_public_error(""));
}

function document_generation_mergeconf_command($files, $fields, $output)
{
    $cmd = "mergeconf";

    foreach ($files as $file)
        $cmd .= " -i ".escapeshellarg($file);
    foreach ($fields as $field)
        $cmd .= " -m ".escapeshellarg($field);
    $cmd .= " -o ".escapeshellarg($output)." --resolve";
    return ($cmd);
}

function document_generation_docbuilder_command($input, $output, $blank = false, $hash_file = "")
{
    return (
        "docbuilder".($blank ? " --blank" : "").
        ($hash_file != "" ? " --hash-file ".escapeshellarg($hash_file) : "").
        " -i ".escapeshellarg($input)." -o ".escapeshellarg($output)
    );
}

/**
 * Resolve the school whose institutional data must remain available when a
 * document is generated as a blank template. Blank means "no beneficiary /
 * workflow values", not "remove the organisation branding".
 */
function document_generation_blank_school_id(array $chain)
{
    global $User;

    // Prefer an explicitly selected school. If the chain only contains a
    // person/session, it may still tell us which school the document belongs
    // to without exposing that person's values to the blank document.
    foreach ($chain as $entry)
    {
        if (!is_array($entry) || !isset($entry["type"]))
            continue ;
        $type = strtolower(trim((string)$entry["type"]));
        if ($type === "school" && isset($entry["id"]) && trim((string)$entry["id"]) !== "")
        {
            $id = document_context_school_id($entry["id"]);
            if ($id != NULL)
                return ((int)$id);
        }
        if (in_array($type, ["user", "student", "eleve", "staff", "jury"], true)
            && isset($entry["id"]) && trim((string)$entry["id"]) !== "")
        {
            $id_user = document_context_user_id($entry["id"]);
            if ($id_user != NULL && ($school = document_context_first_school_for_user($id_user)) != NULL)
                return ((int)$school["id_school"]);
        }
        if ($type === "title_session" && !empty($entry["id"])
            && function_exists("fetch_title_session_basic"))
        {
            $session = fetch_title_session_basic((int)$entry["id"]);
            if (is_array($session) && !empty($session["id_school"]))
                return ((int)$session["id_school"]);
        }
    }

    if (function_exists("get_school_from_url"))
    {
        $school = get_school_from_url();
        if (is_array($school) && !empty($school["id"]))
            return ((int)$school["id"]);
    }

    if (is_array($User ?? NULL))
    {
        if (!empty($User["last_school"]))
        {
            $id = document_context_school_id($User["last_school"]);
            if ($id != NULL)
                return ((int)$id);
        }
        if (!empty($User["id"]) && ($school = document_context_first_school_for_user((int)$User["id"])) != NULL)
            return ((int)$school["id_school"]);
    }
    return (NULL);
}

function document_generation_apply_blank_context(&$fields, &$files, &$temporary_files, array $chain, array $preserved = [])
{
    $has_school = false;
    foreach ($preserved as $entry)
        if (is_array($entry) && strtolower(trim((string)($entry["type"] ?? ""))) === "school")
            $has_school = true;

    if (count($preserved))
        document_context_apply_chain($fields, $preserved, $files, $temporary_files);

    if ($has_school)
        return (true);

    $id_school = document_generation_blank_school_id($chain);
    if ($id_school == NULL)
        return (false);

    // Blank removes beneficiary/workflow values, but the institutional
    // identity stays available. Models may explicitly opt other visible
    // context values into blank generation with Contexts.<Name>.Blank = 1.
    document_context_apply_chain(
        $fields,
        [["type" => "school", "prefix" => "School", "id" => (string)$id_school]],
        $files,
        $temporary_files
    );
    return (true);
}

function document_generation_task_plan($merged)
{
    if (!is_file($merged))
        return ([]);
    $loaded = load_configuration($merged, [], false);
    if ($loaded->is_error() || !is_array($loaded->value))
        return ([]);
    $plan = $loaded->value["TaskPlan"] ?? [];
    return (is_array($plan) ? document_task_plan_normalize($plan) : []);
}


function document_generation_workflow_recipient_contexts($value)
{
    if ($value === NULL)
        return ([]);
    if (!is_array($value))
        $value = [$value];
    $out = [];
    foreach ($value as $recipient)
    {
        if (is_array($recipient))
            continue ;
        $recipient = trim((string)$recipient);
        if ($recipient == "" || in_array($recipient, $out, true))
            continue ;
        $out[] = $recipient;
    }
    return ($out);
}

function document_generation_workflow_options($merged)
{
    if (!is_file($merged))
        return ([]);
    $loaded = load_configuration($merged, [], false);
    if ($loaded->is_error() || !is_array($loaded->value))
        return ([]);
    $workflow = $loaded->value["Workflow"] ?? [];
    if (!is_array($workflow))
        return ([]);
    $explicit_delivery = array_key_exists("DeliveryRecipients", $workflow);
    return ([
        "queue_for_print" => !empty($workflow["QueueForPrint"]),
        "print_recipient" => trim((string)($workflow["PrintRecipient"] ?? "")),
        "require_pdf_sign" => !empty($workflow["RequirePdfSign"]),
        // Optional semantic context names receiving the final immutable PDF.
        // When present, this replaces the historical implicit owner/signatory
        // delivery set instead of extending it.
        "delivery_explicit" => $explicit_delivery,
        "delivery_recipients" => $explicit_delivery
            ? document_generation_workflow_recipient_contexts($workflow["DeliveryRecipients"])
            : [],
    ]);
}


function document_generation_debug_directory()
{
    $root = dirname(__DIR__)."/dres/debug/document_generation";
    $directory = $root."/last";

    if (!is_dir($root) && !@mkdir($root, 0770, true) && !is_dir($root))
        return (NULL);
    // The directory may contain Dabsic contexts with personal data. It is for
    // local server diagnostics only, never a downloadable dres resource.
    $htaccess = $root."/.htaccess";
    if (!is_file($htaccess))
        @file_put_contents($htaccess, "Require all denied\n");

    if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory))
        return (NULL);
    foreach (glob($directory."/*") ?: [] as $entry)
        if (is_file($entry) || is_link($entry))
            @unlink($entry);
    return ($directory);
}

function document_generation_debug_safe_name($value)
{
    $value = preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename((string)$value));
    $value = trim((string)$value, '._-');
    return ($value == "" ? "input.dab" : $value);
}

function document_generation_debug_snapshot(
    array $files,
    array $temporary_files,
    $merged,
    array $merge_fields,
    $merge_command,
    $docbuilder_command,
    array $processes = []
)
{
    $directory = document_generation_debug_directory();
    if ($directory === NULL)
        return (NULL);

    $repo_root = realpath(dirname(__DIR__));
    $temporary = array_fill_keys(array_map('strval', $temporary_files), true);
    $reproduction_files = [];
    $manifest = [];
    $manifest[] = "Date: ".date('c');
    $manifest[] = "Repository: ".($repo_root ?: dirname(__DIR__));
    $manifest[] = "";
    $manifest[] = "Inputs:";

    foreach (array_values($files) as $index => $file)
    {
        $file = (string)$file;
        $real = realpath($file);
        $exists = is_file($file);
        $readable = $exists && is_readable($file);
        $size = $exists ? @filesize($file) : false;
        $hash = $readable ? @hash_file('sha256', $file) : false;
        $manifest[] = sprintf(
            "%02d  exists=%s readable=%s size=%s sha256=%s temporary=%s  %s",
            $index + 1,
            $exists ? 'yes' : 'NO',
            $readable ? 'yes' : 'NO',
            $size === false ? '?' : (string)$size,
            $hash === false ? '-' : $hash,
            isset($temporary[$file]) ? 'yes' : 'no',
            $file
        );

        // Repository document models often use relative @include directives;
        // keep those on their original path for the reproduction command.
        $inside_repo = $real !== false && $repo_root !== false &&
            ($real === $repo_root || str_starts_with($real, $repo_root.'/'));
        if (!$readable || $inside_repo)
        {
            $reproduction_files[] = $file;
            continue ;
        }

        $target = $directory.'/input-'.sprintf('%02d', $index + 1).'-'.
            document_generation_debug_safe_name($file);
        if (@copy($file, $target))
        {
            @chmod($target, 0660);
            $reproduction_files[] = $target;
        }
        else
            $reproduction_files[] = $file;
    }

    $debug_merged = $directory.'/merged.dab';
    if (is_file($merged) && @copy($merged, $debug_merged))
        @chmod($debug_merged, 0660);
    else
        $debug_merged = $directory.'/reproduced-merged.dab';

    $reproduce_merge = document_generation_mergeconf_command(
        $reproduction_files,
        $merge_fields,
        $directory.'/reproduced-merged.dab'
    );
    $reproduce_docbuilder = document_generation_docbuilder_command(
        is_file($directory.'/merged.dab') ? $directory.'/merged.dab' : $directory.'/reproduced-merged.dab',
        $directory.'/reproduced.pdf',
        false,
        $directory.'/reproduced.sha256'
    );

    $manifest[] = "";
    $manifest[] = "Merge command used by the web request:";
    $manifest[] = $merge_command;
    $manifest[] = "";
    $manifest[] = "DocBuilder command used by the web request:";
    $manifest[] = $docbuilder_command;
    $manifest[] = "";
    $manifest[] = "Reproduction commands from the shell:";
    $manifest[] = $reproduce_merge;
    $manifest[] = $reproduce_docbuilder;
    @file_put_contents($directory.'/manifest.txt', implode("\n", $manifest)."\n");
    @chmod($directory.'/manifest.txt', 0660);

    $script = "#!/bin/sh\nset -eu\n".
        $reproduce_merge."\n".
        $reproduce_docbuilder."\n";
    @file_put_contents($directory.'/reproduce.sh', $script);
    @chmod($directory.'/reproduce.sh', 0700);

    $process_text = document_generation_full_process_output($processes);
    if ($process_text != "")
    {
        @file_put_contents($directory.'/process-output.txt', $process_text."\n");
        @chmod($directory.'/process-output.txt', 0660);
    }
    return ($directory);
}

function document_generation_debug_file($file)
{
    $out = "--- ".$file." ---\n";
    if (!is_file($file))
        return ($out."[fichier absent]\n");

    $content = file_get_contents($file);
    if ($content === false)
        return ($out."[fichier illisible]\n");

    $limit = 512 * 1024;
    if (strlen($content) > $limit)
        $content = substr($content, 0, $limit)."\n[contenu tronqué après ".$limit." octets]";
    return ($out.$content.(substr($content, -1) === "\n" ? "" : "\n"));
}

function document_generation_debug_report($docbuilder_command, array $temporary_files, $merged)
{
    // This string is returned to the browser on generation failure. Never dump
    // the temporary contexts here: they can contain addresses, birth dates,
    // identifiers and other personal data, and their volume used to bury the
    // actual DocBuilder error in the notification box. Raw process output is
    // already written to the PHP error log by document_generation_*_error().
    $out = "\n\n===== DIAGNOSTIC GENERATION DOCUMENT =====\n";
    $out .= "Commande DocBuilder :\n".$docbuilder_command."\n";
    $out .= "Contextes temporaires : ".count(array_unique($temporary_files))." fichier(s), contenu masqué.\n";
    $out .= "Fichier Dabsic fusionné : ".$merged."\n";
    $out .= "===== FIN DIAGNOSTIC GENERATION DOCUMENT =====";
    return ($out);
}

function ResolveDocContext($id, $data, $method, $output, $module)
{
    global $User;

    if (!document_generation_staff_request_allowed(is_array($data) ? $data : []))
        return (new ErrorResponse("PermissionDenied"));

    $bindings = $data["context_bindings"] ?? [];
    $documents = [];
    foreach ($data as $key => $value)
    {
        if (strncmp((string)$key, "doc_", 4) != 0 || !$value)
            continue ;
        $hash = substr((string)$key, 4);
        $reference = trim((string)($data["docref_".$hash] ?? ""));
        if ($reference == "")
            continue ;
        $file = document_reference_to_path($reference);
        if ($file != NULL && is_file($file))
            $documents[] = $file;
    }
    if (!count($documents))
        return (new ErrorResponse("MissingField", "document model"));

    $resolved = [];
    $automatic = [];
    $missing = [];
    foreach ($documents as $file)
    {
        $bundle = document_context_model_bundle(
            $file,
            $bindings,
            [],
            [
                "current_user_id" => (int)($User["id"] ?? 0),
                "strict" => true,
            ]
        );
        foreach (document_context_materialized_bindings($bundle) as $name => $value)
            $resolved[$name] = $value;
        $automatic = array_merge($automatic, document_context_automatic_names($bundle));
        $missing = array_merge($missing, $bundle["missing"] ?? []);
    }

    return (new ValueResponse([
        "bindings" => $resolved,
        "automatic" => array_values(array_unique($automatic)),
        "missing" => array_values(array_unique($missing)),
    ]));
}

function document_generation_request_references(array $data)
{
    $out = [];
    foreach ($data as $key => $value)
        if (strncmp((string)$key, "docref_", 7) === 0 && trim((string)$value) != "")
            $out[] = (string)$value;
    return (array_values(array_unique($out)));
}

function document_generation_request_context_bindings(array $data)
{
    $bindings = $data["context_bindings"] ?? [];
    if (is_string($bindings))
        $bindings = json_decode($bindings, true);
    return (is_array($bindings) ? $bindings : []);
}

function document_generation_staff_request_allowed(array $data)
{
    global $User;

    if (!is_array($User))
        return (false);
    $refs = document_generation_request_references($data);
    $basenames = array_map(function($reference) {
        $split = explode(":", (string)$reference, 2);
        return (basename(count($split) == 2 ? $split[1] : $split[0]));
    }, $refs);
    $bindings = document_generation_request_context_bindings($data);

    // The rectorate export is a school-level regulatory personnel document.
    // Keep it director-only even though ordinary document generation is
    // available to teachers, and scope the permission to the semantic School
    // context rather than trusting a UI-only restriction.
    if (in_array("liste_enseignants_rectorat.dab", $basenames, true))
    {
        if (count($basenames) !== 1)
            return (false);
        $id_school = isset($bindings["School"])
            ? document_context_school_id($bindings["School"]) : NULL;
        return ($id_school !== NULL && (int)$id_school > 0
            && is_director_for_school((int)$id_school));
    }

    $current_user_id = (int)($User["id"] ?? 0);
    $administrator_account = false;
    if ($current_user_id > 0)
    {
        $administrator_row = db_select_one(
            "id, authority FROM user WHERE id = $current_user_id AND authority != -1"
        );
        $administrator_account = is_array($administrator_row)
            && ((int)$administrator_row["id"] === 1
                || (int)$administrator_row["authority"] === ADMINISTRATOR);
    }

    if ($administrator_account || am_i_teacher() || am_i_director())
        return (true);
    if (isset($data["save_user_document"])
        && can_manage_student_documents((int)$data["save_user_document"]))
        return (true);
    if (!count($refs))
        return (false);

    // Librarians can compose the overdue-book reminder from the generic
    // Documents page. The BookLoan semantic context resolves the borrower and
    // school; once a loan is supplied, authorization is scoped to that school.
    if (count($basenames) === 1 && $basenames[0] === "relance_retour_livre.dab")
    {
        $book_loan_value = trim((string)($bindings["BookLoan"] ?? ""));
        if ($book_loan_value == "")
            return (am_i_librarian());
        $loan = document_context_book_loan($book_loan_value);
        if (!is_array($loan))
            return (false);
        $id_student = (int)($loan["student_id"] ?? 0);
        $school_id = $id_student > 0 ? document_print_school_id_for_user($id_student) : 0;
        return ($id_student > 0 && $school_id > 0 && document_print_user_can_manage_context(
            (int)$User["id"],
            [
                "type" => "library",
                "owner_user_id" => $id_student,
                "school_id" => $school_id,
                "book_user_id" => (int)($loan["id"] ?? 0),
            ]
        ));
    }

    // Billing staff may only use dedicated billing documents through this
    // extra permission. Other generic document generation keeps the historical
    // teacher restriction.
    if (count($basenames) === 1 && in_array($basenames[0], [
        "relance_paiement_retard.dab",
        "echeancier_paiements.dab",
    ], true))
    {
        $id_student = (int)($bindings["Student"] ?? 0);
        $school_id = $id_student > 0 ? document_print_school_id_for_user($id_student) : 0;
        return ($id_student > 0 && $school_id > 0 && is_billing_manager_for_school($school_id));
    }

    // The cycle administration page already exposes these three generated
    // letters to cycle/school authorities. Allow the same people to actually
    // execute the generation even if they are not activity teachers.
    $cycle_models = [
        "convocation_preparation_accueil.dab",
        "invitation_journee_integration.dab",
        "convocation_rentree.dab",
    ];
    if (!array_diff($basenames, $cycle_models))
    {
        $id_cycle = (int)($bindings["Cycle"] ?? 0);
        $id_student = (int)($bindings["Student"] ?? 0);
        return ($id_cycle > 0 && $id_student > 0 && document_print_user_can_manage_context(
            (int)$User["id"],
            [
                "type" => "cycle",
                "owner_user_id" => $id_student,
                "cycle_id" => $id_cycle,
                "school_id" => document_print_school_id_for_cycle($id_cycle),
            ]
        ));
    }

    // End-of-cycle result letters are generated from the cycle student table.
    // Their Dabsic model has Student/School contexts only, so scope the extra
    // permission from the student itself rather than trusting form fields.
    $result_models = [
        "felicitations_trimestre_exceptionnel.dab",
        "felicitations_trimestre_objectif.dab",
        "encouragement_trimestre_50_99.dab",
        "encouragement_trimestre_0_49.dab",
    ];
    if (count($basenames) === 1 && in_array($basenames[0], $result_models, true))
    {
        $id_student = (int)($bindings["Student"] ?? 0);
        return ($id_student > 0 && document_print_user_can_manage_context(
            (int)$User["id"],
            [
                "type" => "user",
                "owner_user_id" => $id_student,
                "school_id" => document_print_school_id_for_user($id_student),
            ]
        ));
    }
    return (false);
}

function document_generation_contract_school_id(array $bindings)
{
    if (isset($bindings["School"]) && trim((string)$bindings["School"]) != "")
    {
        $id_school = document_context_school_id($bindings["School"]);
        if ($id_school != NULL && (int)$id_school > 0)
            return ((int)$id_school);
    }
    foreach (["Student", "User", "Owner"] as $context)
    {
        if (!isset($bindings[$context]) || trim((string)$bindings[$context]) == "")
            continue ;
        $id_user = document_context_user_id($bindings[$context]);
        if ($id_user == NULL || (int)$id_user <= 0)
            continue ;
        $school = document_context_first_school_for_user((int)$id_user);
        if (is_array($school) && (int)($school["id_school"] ?? 0) > 0)
            return ((int)$school["id_school"]);
    }
    return (0);
}

/** Empty string means allowed; otherwise returns the blocked activity mode. */
function document_generation_blocked_contract_mode(array $selected_documents, $context_bindings)
{
    $modes = [];
    foreach ($selected_documents as $document)
    {
        $mode = school_activity_contract_file_mode($document["file"] ?? "");
        if ($mode !== NULL)
            $modes[$mode] = true;
    }
    if (!count($modes))
        return ("");

    $bindings = $context_bindings;
    if (is_string($bindings))
        $bindings = json_decode($bindings, true);
    if (!is_array($bindings))
        $bindings = [];
    $id_school = document_generation_contract_school_id($bindings);
    if ($id_school <= 0)
        return ((string)array_key_first($modes));
    $school = fetch_school($id_school);
    if ($school instanceof ErrorResponse || !is_array($school))
        return ((string)array_key_first($modes));
    foreach (array_keys($modes) as $mode)
        if (!school_activity_mode_allowed($school, $mode))
            return ($mode);
    return ("");
}

function _GenerateDoc($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;
    global $User;

    $files = [];
    $context_files = [];
    $fields = [];
    $temporary_files = [];
    $form_output_key = isset($data["form_output"]) ? (string)$data["form_output"] : "";
    $save_user_document = isset($data["save_user_document"]) ? (int)$data["save_user_document"] : 0;
    $mail_payment_schedule = !empty($data["mail_payment_schedule"]);
    $mail_payment_student_id = 0;
    $mail_payment_finance_id = 0;
    $finalize_document = !empty($data["finalize_document"]);
    $blank_document = !empty($data["blank_document"]);
    $target_year = isset($data["target_year"]) ? (int)$data["target_year"] : 0;
    $queue_for_print = !empty($data["queue_for_print"]);
    $print_context = [
        "type" => trim((string)($data["print_context_type"] ?? "user")),
        "owner_user_id" => (int)($data["print_owner_user_id"] ?? $save_user_document),
        "school_id" => (int)($data["print_school_id"] ?? 0),
        "cycle_id" => (int)($data["print_cycle_id"] ?? 0),
        "prospect_user_id" => (int)($data["print_prospect_user_id"] ?? 0),
        "billing_entry_id" => (int)($data["print_billing_entry_id"] ?? 0),
        "book_user_id" => (int)($data["print_book_user_id"] ?? 0),
        "source_key" => trim((string)($data["print_source_key"] ?? "")),
        "recipient_label" => trim((string)($data["print_recipient_label"] ?? "")),
    ];
    $print_label = trim((string)($data["print_label"] ?? ""));
    $signature_bindings = [];
    $context_bindings = $data["context_bindings"] ?? "";
    $selected_document_reference = "";
    $selected_document_file = "";
    $selected_documents = [];
    // The internship calendar is detected and validated from the document
    // form before rendering.  Keep that decision: ordinary user documents
    // must never be reparsed later merely to discover that they are not
    // internship agreements.
    $internship_calendar_applicable = false;

    // Contexts are semantic: the same Student/School/etc. binding can feed
    // several selected models, even when those models expose the value under
    // different Dabsic prefixes.  Form/workflow finalization still targets one
    // model because its signature/task schema is model-specific.
    foreach ($data as $key => $val)
    {
        if (strncmp($key, "doc_", 4) != 0 || $val == 0)
            continue ;
        $hash = substr($key, 4);
        if (!isset($data["docref_".$hash]))
            continue ;
        $reference = (string)$data["docref_".$hash];
        $file = document_reference_to_path($reference);
        if ($file == NULL || !is_file($file))
            continue ;
        $selected_documents[] = [
            "reference" => $reference,
            "file" => $file,
            "priority" => (int)$val,
        ];
        if ($selected_document_reference == "")
        {
            $selected_document_reference = $reference;
            $selected_document_file = $file;
        }
    }
    if ($mail_payment_schedule && (count($selected_documents) != 1 ||
        basename((string)($selected_documents[0]["file"] ?? "")) !== "echeancier_paiements.dab"))
        return (new ErrorResponse("InvalidParameter", "mail_payment_schedule"));
    if ($finalize_document && count($selected_documents) != 1)
        return (new ErrorResponse("InvalidParameter", "document model"));
    $blocked_contract_mode = document_generation_blocked_contract_mode($selected_documents, $context_bindings);
    if ($blocked_contract_mode != "")
        return (new ErrorResponse("ContractModeUnavailable", strtoupper($blocked_contract_mode)));

    unset(
        $data["action"], $data["form_output"], $data["save_user_document"],
        $data["mail_payment_schedule"],
        $data["finalize_document"], $data["blank_document"], $data["target_year"],
        $data["signature_bindings"], $data["context_bindings"],
        $data["queue_for_print"], $data["print_context_type"],
        $data["print_owner_user_id"], $data["print_school_id"], $data["print_cycle_id"],
        $data["print_prospect_user_id"], $data["print_billing_entry_id"], $data["print_book_user_id"],
        $data["print_source_key"], $data["print_recipient_label"], $data["print_label"]
    );

    if (isset($data["fields"]))
    {
        if (!is_array($data["fields"]))
            $data["fields"] = preg_split('/\s+/', trim($data["fields"]));
        foreach ($data["fields"] as $df)
        {
            if ($df == "")
                continue ;
            if (!preg_match('/^[a-zA-Z_0-9\.]+=.*/', $df))
                bad_request();
            $fields[] = $df;
        }
        unset($data["fields"]);
    }

    // The payment schedule is financial data: rebuild its values from the
    // database at generation time instead of trusting hidden fields rendered
    // when the billing page was opened.  This also prevents an already-open
    // page from sending an obsolete row layout after the document template
    // changes.
    if (count($selected_documents) === 1 &&
        basename((string)($selected_documents[0]["file"] ?? "")) === "echeancier_paiements.dab")
    {
        $bindings = document_generation_request_context_bindings([
            "context_bindings" => $context_bindings,
        ]);
        $id_schedule_student = (int)($bindings["Student"] ?? 0);
        if ($id_schedule_student > 0)
        {
            $fields = array_values(array_filter($fields, function ($field) {
                return (!str_starts_with((string)$field, "PaymentSchedule."));
            }));
            foreach (billing_payment_schedule_document_fields($id_schedule_student) as $name => $value)
                $fields[] = "PaymentSchedule.".$name."=".$value;
        }
    }

    $document_chain = [];
    $blank_preserved_chain = [];
    $missing_contexts = [];
    // Semantic user contexts resolved for this model. Workflow.DeliveryRecipients
    // refers to these names (Student, Finance, Legal1, ...), not raw user ids.
    $document_context_users = [];
    foreach ($selected_documents as $selected_document)
    {
        $context_bundle = document_context_model_bundle(
            $selected_document["file"],
            $context_bindings,
            [],
            [
                "current_user_id" => (int)($User["id"] ?? 0),
                "owner_user_id" => $save_user_document,
                "strict" => !$blank_document,
            ]
        );
        $document_chain = array_merge($document_chain, $context_bundle["chain"]);
        if ($blank_document)
            foreach (($context_bundle["schema"] ?? []) as $context_name => $definition)
                if (!empty($definition["blank"])
                    && isset($context_bundle["bindings"][$context_name])
                    && isset($context_bundle["resolved"][$context_name]))
                    $blank_preserved_chain[] = document_context_chain_entry(
                        $context_name,
                        $definition,
                        $context_bundle["resolved"][$context_name]["value"]
                    );
        $missing_contexts = array_merge($missing_contexts, $context_bundle["missing"]);
        foreach ($context_bundle["signature_bindings"] as $slot => $source)
            $signature_bindings[$slot] = $source;
        foreach (($context_bundle["schema"] ?? []) as $context_name => $definition)
        {
            if (!isset($context_bundle["resolved"][$context_name]))
                continue ;
            $context_user_id = document_context_signatory_user_id(
                $definition,
                $context_bundle["resolved"][$context_name]["value"] ?? ""
            );
            if ($context_user_id !== NULL && $context_user_id > 0)
                $document_context_users[$context_name] = (int)$context_user_id;
        }

        if ($mail_payment_schedule)
        {
            foreach (["Student" => "student", "Finance" => "finance"] as $context_name => $target)
            {
                if (!isset($context_bundle["schema"][$context_name]) ||
                    !isset($context_bundle["resolved"][$context_name]))
                    continue ;
                $id_user = document_context_signatory_user_id(
                    $context_bundle["schema"][$context_name],
                    $context_bundle["resolved"][$context_name]["value"] ?? ""
                );
                if ($id_user === NULL || $id_user <= 0)
                    continue ;
                if ($target === "student")
                    $mail_payment_student_id = (int)$id_user;
                else
                    $mail_payment_finance_id = (int)$id_user;
            }
        }
    }
    $missing_contexts = array_values(array_unique($missing_contexts));
    if (count($missing_contexts) && !$blank_document)
        return (new ErrorResponse(
            "MissingField",
            "Contexts.".implode(", Contexts.", $missing_contexts)
        ));

    if ($mail_payment_schedule)
    {
        if ($mail_payment_student_id <= 0 || $mail_payment_finance_id <= 0)
            return (new ErrorResponse("CannotSendMail"));
        if (!is_billing_manager_for_user($mail_payment_student_id))
            forbidden();
    }

    if ($blank_document)
    {
        if (!document_generation_apply_blank_context(
            $fields,
            $context_files,
            $temporary_files,
            $document_chain,
            $blank_preserved_chain
        ))
            return (new ErrorResponse(
                "MissingField",
                "School"
            ));
    }
    else if (count($document_chain))
    {
        // A workflow signature must be an explicit act.  The profile identity
        // may contain a reusable handwritten signature, but it must not be
        // injected in the PDF before the corresponding signature task has
        // actually been completed.  This applies to previews as well as the
        // immutable PDF shown to signatories.
        $workflow_collects_signatures = $save_user_document > 0
            && $selected_document_file != ""
            && count(document_signature_model_slots($selected_document_file)) > 0;
        document_context_apply_chain(
            $fields,
            $document_chain,
            $context_files,
            $temporary_files,
            ["suppress_signatory_signatures" => $workflow_collects_signatures]
        );
    }

    // Generation metadata is a fact of the produced document, not an operator
    // choice. Keep it in a normal Dabsic scope so models can use it without a
    // mergeconf transformation. A blank template deliberately has no date.
    if (!$blank_document)
    {
        $school_year_start = (int)date("Y");
        if ((int)date("n") < 9)
            --$school_year_start;
        $generation_context = document_context_data_scope_file("Generation", [
            "date" => date("d/m/Y"),
            "time" => date("H:i"),
            "datetime" => date("d/m/Y H:i"),
            "school_period" => $school_year_start."-".($school_year_start + 1),
            "school_year_start" => (string)$school_year_start,
            "school_year_end" => (string)($school_year_start + 1),
        ], $temporary_files);
        if ($generation_context != NULL)
            $context_files[] = $generation_context;
    }
    unset($data["chain"]);

    // doc_<hash>=0|1 + docref_<hash>=source:path.  Keep the historical
    // ordering semantics: priority 1 documents are prepended.
    foreach ($selected_documents as $selected_document)
        if ($selected_document["priority"] == 1)
            array_unshift($files, $selected_document["file"]);
        else
            $files[] = $selected_document["file"];

    // mergeconf résout après lecture de tous les fichiers et, en cas de
    // redéfinition, la dernière valeur l'emporte. Les contextes doivent donc
    // suivre les modèles : School complète le contrat et peut remplacer ses
    // valeurs par défaut.
    if (count($context_files))
        $files = array_merge($files, $context_files);

    // A profile document may have a complementary Dabsic form. It is resolved
    // server-side from a symbolic output key and appended after the automatic
    // contexts so explicitly completed values take precedence.
    if ($form_output_key != "")
    {
        $form_output = dabsic_form_resolve_output($form_output_key, false);
        if (!$form_output["ok"])
            return (new ErrorResponse($form_output["error"], $form_output["details"] ?? ""));
        if ($form_output["exists"])
            $files[] = $form_output["absolute"];
    }

    if ($finalize_document && $save_user_document > 0 && $form_output_key != "")
    {
        if ($selected_document_file != "")
        {
            $form_metadata = dabsic_form_form_metadata($selected_document_file);
            $workspace_semantic = [];
            if (isset($form_output) && $form_output["ok"])
            {
                $workspace = dabsic_form_load_workspace($form_output);
                if ($workspace["ok"] && $workspace["exists"])
                    $workspace_semantic = document_workflow_workspace_semantic_bindings($workspace["data"]);
            }
            if (!document_workflow_workspace_required_roles_complete(
                $save_user_document, $form_output_key, $form_metadata["roles"] ?? [],
                document_workflow_actor_id(), $workspace_semantic
            ))
            {
                $required_roles = document_workflow_required_model_roles($selected_document_file);
                return (new ErrorResponse(
                    "DocumentRequiredTasks",
                    implode(", ", array_values($required_roles))
                ));
            }
        }
        $pending_tasks = document_workflow_pending_required_form_tasks($save_user_document, $form_output_key);
        if (count($pending_tasks))
        {
            $labels = [];
            foreach ($pending_tasks as $task)
                $labels[] = trim((string)($task["role_label"] ?? "")) != ""
                    ? trim((string)$task["role_label"]) : (string)($task["role"] ?? "");
            return (new ErrorResponse(
                "DocumentRequiredTasks",
                implode(", ", array_values(array_filter($labels, function($label) { return $label !== ""; })))
            ));
        }
        $missing_fields = document_workflow_missing_required_form_fields($save_user_document, $form_output_key);
        if (count($missing_fields))
            return (new ErrorResponse(
                "DocumentRequiredFields",
                implode("\n", array_values($missing_fields))
            ));
        if (isset($form_output) && !empty($form_output["exists"]))
        {
            $calendar_validation = internship_session_validate_output($form_output_key, $form_output["absolute"]);
            $internship_calendar_applicable = !empty($calendar_validation["applicable"]);
            if ($internship_calendar_applicable && !$calendar_validation["ok"])
                return (new ErrorResponse(
                    "InvalidParameter",
                    "Calendrier de stage : ".implode(" ; ", $calendar_validation["errors"] ?? ["invalide"])
                ));
            if (!empty($calendar_validation["warnings"]))
                add_log(TRACE, "Internship calendar finalization warnings: ".implode(" ; ", $calendar_validation["warnings"]), $save_user_document);
        }
    }

    // A Dabsic form may carry explicit mergeconf overrides in a protected
    // sidecar file. Append them last so -m keeps its intended priority over
    // defaults and values loaded from Dabsic files.
    foreach ($files as $file)
        foreach (dabsic_form_mergeconf_fields_for_file($file) as $field)
            $fields[] = $field;

    if (!count($files))
        bad_request();

    // The first Dabsic input is the document model. Keep its name for the
    // downloaded PDF instead of exposing the temporary merged filename.
    $output_filename = pathinfo((string)$files[0], PATHINFO_FILENAME).".pdf";
    if ($output_filename === ".pdf")
        $output_filename = "document.pdf";

    $tmp = tempnam(sys_get_temp_dir(), "infosphere_doc_");
    if ($tmp === false)
        return (new ErrorResponse("CannotWriteFile", "temporary document"));
    @unlink($tmp);
    $merged = $tmp.".dab";
    $pdf = $tmp.".pdf";
    $hash_file = $tmp.".sha256";

    // On exécute explicitement mergeconf avant docbuilder.
    // DocBuilder sait aussi résoudre des fichiers Dabsic, mais dans ce mode
    // ses erreurs internes ne remontent pas toujours le stderr de mergeconf.
    // La page Documents doit afficher les erreurs Dabsic sans jamais exposer
    // la sortie de travail stdout.
    // Infosphere still pre-resolves the model so mergeconf diagnostics can be
    // shown cleanly in the GUI. The hash itself is now owned by DocBuilder:
    // this blank reservation only prevents form/model resolution from treating
    // DocBuilder.DabsicHash as a user field.
    $merge_fields = document_builder_append_dabsic_hash_field_strings($fields, "");
    $merge_command = document_generation_mergeconf_command($files, $merge_fields, $merged);
    $docbuilder_command = document_generation_docbuilder_command($merged, $pdf, $blank_document, $hash_file);
    // Catch missing/unreadable top-level Dabsic inputs before mergeconf. This
    // turns the otherwise opaque "config -> (nil)" error into an actionable
    // path and snapshots the inputs while Apache's PrivateTmp still exists.
    $invalid_inputs = [];
    foreach ($files as $input_file)
        if (!is_file($input_file) || !is_readable($input_file))
            $invalid_inputs[] = (string)$input_file;
    if (count($invalid_inputs))
    {
        $debug_directory = document_generation_debug_snapshot(
            $files, $temporary_files, $merged, $merge_fields,
            $merge_command, $docbuilder_command, []
        );
        $details = "Entrée(s) Dabsic absente(s) ou illisible(s) :\n".implode("\n", $invalid_inputs);
        if ($debug_directory !== NULL)
            $details .= "\nDiagnostic conservé : ".$debug_directory;
        foreach ($temporary_files as $temporary_file)
            @unlink($temporary_file);
        return (new ErrorResponse("DocumentGenerationFailed", $details));
    }

    $merge_process = document_generation_run_command($merge_command);
    if ($merge_process["status"] != 0 || !file_exists($merged))
    {
        $processes = [
            "mergeconf" => $merge_process,
        ];
        $details = document_generation_process_combined_error($processes);
        $raw_output = document_generation_full_process_output($processes);
        if ($raw_output != "")
            document_generation_log_raw_output("full failure", $raw_output);
        $debug_directory = document_generation_debug_snapshot(
            $files, $temporary_files, $merged, $merge_fields,
            $merge_command, $docbuilder_command, $processes
        );
        $details .= document_generation_debug_report($docbuilder_command, $temporary_files, $merged);
        if ($debug_directory !== NULL)
            $details .= "\nDiagnostic conservé : ".$debug_directory;
        @unlink($hash_file);
        @unlink($pdf);
        foreach ($temporary_files as $temporary_file)
            @unlink($temporary_file);
        return (new ErrorResponse("DocumentGenerationFailed", $details));
    }

    $build_process = document_generation_run_command($docbuilder_command);
    $content = file_exists($pdf) ? file_get_contents($pdf) : false;

    if ($build_process["status"] != 0 || $content === false || substr($content, 0, 4) != "%PDF")
    {
        $processes = [
            "mergeconf" => $merge_process,
            "docbuilder" => $build_process
        ];
        $details = document_generation_process_combined_error($processes);
        $raw_output = document_generation_full_process_output($processes);
        if ($raw_output != "")
            document_generation_log_raw_output("full failure", $raw_output);
        $debug_directory = document_generation_debug_snapshot(
            $files, $temporary_files, $merged, $merge_fields,
            $merge_command, $docbuilder_command, $processes
        );
        $details .= document_generation_debug_report($docbuilder_command, $temporary_files, $merged);
        if ($debug_directory !== NULL)
            $details .= "\nDiagnostic conservé : ".$debug_directory;
        // Conserver le Dabsic fusionné afin de pouvoir le tester
        // directement avec mergeconf/docbuilder après un échec.
        @unlink($pdf);
        foreach ($temporary_files as $temporary_file)
            @unlink($temporary_file);
        @unlink($hash_file);
        return (new ErrorResponse("DocumentGenerationFailed", $details));
    }

    $resolved_dabsic_hash = strtolower(trim((string)@file_get_contents($hash_file)));
    @unlink($hash_file);
    if (!preg_match('/^[a-f0-9]{64}$/D', $resolved_dabsic_hash))
        return (new ErrorResponse("DocumentGenerationFailed", "DocBuilder n’a pas produit d’empreinte Dabsic valide."));

    $saved_document = "";
    $document_instance = NULL;
    $task_plan = document_generation_task_plan($merged);
    $workflow_options = document_generation_workflow_options($merged);
    if ($finalize_document && $save_user_document > 0)
    {
        if (!can_manage_student_documents($save_user_document))
            forbidden();
        if ($selected_document_reference == "" || $selected_document_file == "")
            return (new ErrorResponse("InvalidParameter", "document model"));

        $finalization_lock = NULL;
        if ($form_output_key != "")
        {
            $finalization_lock = document_workflow_acquire_finalization_lock(
                $save_user_document, $form_output_key
            );
            if ($finalization_lock === NULL)
                return (new ErrorResponse("CannotEdit", "document finalization lock"));
        }

        try
        {
            $already_finalized = false;
            if ($form_output_key != "")
            {
                // Finalization is one-shot per logical document output, not per
                // contribution/role row.  Check for an existing active instance
                // while holding the output lock before looking at form state.
                // This also covers the case where the PDF was frozen correctly
                // but marking one or more user_form rows as processed failed.
                $existing_instance_id = document_workflow_processed_instance_for_output(
                    $save_user_document, $form_output_key
                );
                if ($existing_instance_id != "")
                {
                    $existing = document_workflow_find_instance($save_user_document, $existing_instance_id);
                    if ($existing->is_error())
                        return ($existing);
                    $existing_data = $existing->value["data"];
                    $document_instance = new ValueResponse([
                        "id" => $existing_instance_id,
                        "status" => (string)($existing_data["Status"] ?? ""),
                        "hash" => (string)($existing_data["FrozenHash"] ?? ""),
                        "directory" => $existing->value["directory"],
                        "pdf" => $existing->value["directory"].(string)($existing_data["FrozenFile"] ?? "frozen.pdf"),
                        "metadata" => $existing->value["file"],
                        "existing" => true,
                    ]);
                    $already_finalized = true;
                }
                else if (document_workflow_completed_form_for_output($save_user_document, $form_output_key) == NULL
                    && document_workflow_active_workspace_for_output(
                        $save_user_document,
                        $form_output_key,
                        md5($selected_document_reference),
                        $target_year,
                        $selected_document_reference
                    ) == NULL)
                {
                    // A workspace-only document (no recipient FormRole) has no
                    // completed user_form row by design.  Its still-active
                    // workspace is the durable proof that this output is allowed
                    // to enter finalization.  Keep the historical fallback only
                    // when neither a contribution nor a matching workspace exists.
                    $existing_instance_id = document_workflow_recent_processed_instance_for_output(
                        $save_user_document, $form_output_key
                    );
                    if ($existing_instance_id == "")
                        return (new ErrorResponse("InvalidParameter", "document already finalized"));
                    $existing = document_workflow_find_instance($save_user_document, $existing_instance_id);
                    if ($existing->is_error())
                        return ($existing);
                    $existing_data = $existing->value["data"];
                    $document_instance = new ValueResponse([
                        "id" => $existing_instance_id,
                        "status" => (string)($existing_data["Status"] ?? ""),
                        "hash" => (string)($existing_data["FrozenHash"] ?? ""),
                        "directory" => $existing->value["directory"],
                        "pdf" => $existing->value["directory"].(string)($existing_data["FrozenFile"] ?? "frozen.pdf"),
                        "metadata" => $existing->value["file"],
                        "existing" => true,
                    ]);
                    $already_finalized = true;
                }
            }

            if (!$already_finalized)
            {
                $delivery_explicit = !empty($workflow_options["delivery_explicit"]);
                if ($delivery_explicit)
                {
                    $delivery_users = [];
                    if (!count($workflow_options["delivery_recipients"] ?? []))
                        return (new ErrorResponse(
                            "InvalidParameter",
                            "Workflow.DeliveryRecipients"
                        ));
                    foreach (($workflow_options["delivery_recipients"] ?? []) as $delivery_context)
                    {
                        if ($delivery_context === "Owner")
                            $delivery_id = (int)$save_user_document;
                        else
                            $delivery_id = (int)($document_context_users[$delivery_context] ?? 0);
                        // A typo or a non-person context in the document model must
                        // never silently turn a letter into an undelivered document.
                        if ($delivery_id <= 0)
                            return (new ErrorResponse(
                                "InvalidParameter",
                                "Workflow.DeliveryRecipients.".$delivery_context
                            ));
                        // Keep the intended recipient even when their mailbox is
                        // missing/invalid. The delivery pass will then expose a
                        // DeliveryError instead of silently pretending the letter
                        // had nobody to receive it.
                        $delivery_users[] = $delivery_id;
                    }
                }
                else
                {
                    // Legacy/default behaviour: beneficiary plus form recipients;
                    // signatories are added later by the delivery layer.
                    $delivery_users = [$save_user_document];
                    if ($form_output_key != "")
                        foreach (document_workflow_active_forms_for_output($save_user_document, $form_output_key) as $delivery_form)
                        {
                            $delivery_document = document_workflow_document_form_metadata($delivery_form);
                            foreach (["recipient_user_id", "primary_recipient_user_id"] as $delivery_field)
                            {
                                $delivery_id = (int)($delivery_document[$delivery_field] ?? 0);
                                if ($delivery_id > 0 && document_workflow_user_can_receive_form($delivery_id))
                                    $delivery_users[] = $delivery_id;
                            }
                        }
                }
                $delivery_users = array_values(array_unique(array_map("intval", $delivery_users)));

                // Freeze the document first, but defer task publication until the
                // internship calendar (when present) has been synchronized. Draft
                // saves never create sessions: this is the single activation point.
                $document_instance = document_workflow_create_frozen_instance(
                    $save_user_document,
                    $selected_document_reference,
                    $selected_document_file,
                    $target_year,
                    $signature_bindings,
                    $content,
                    $task_plan,
                    [
                        "resolved_dabsic_hash" => $resolved_dabsic_hash,
                        "delivery_users" => $delivery_users,
                        "delivery_explicit" => $delivery_explicit,
                        // Keep the exact resolved Dabsic that produced the PDF shown
                        // to signatories.  Once every signature/paraph is collected,
                        // the final visible PDF is rebuilt from this immutable source
                        // with only Signature/Initials overlays added.
                        "resolved_dabsic_file" => $merged,
                        "source_context" => [
                            "type" => "UserDocument",
                            "output" => $form_output_key,
                            "model_hash" => md5($selected_document_reference),
                            "target_year" => $target_year,
                        ],
                        "queue_for_print" => !empty($workflow_options["queue_for_print"])
                            || $queue_for_print,
                        "print_recipient" => (string)($workflow_options["print_recipient"] ?? ""),
                        "require_pdf_sign" => !empty($workflow_options["require_pdf_sign"]),
                    ],
                    false
                );
                if ($document_instance->is_error())
                    return ($document_instance);

                $session_sync = ["ok" => true, "applicable" => false];
                if ($internship_calendar_applicable && $form_output_key != ""
                    && isset($form_output) && !empty($form_output["exists"]))
                    // ScheduleCalendar belongs to the complementary form and was
                    // already validated above.  Do not feed the fully resolved
                    // DocBuilder input back to load_configuration(): that file is a
                    // rendering artifact, and unrelated documents must not enter the
                    // internship synchronizer at all.
                    $session_sync = internship_session_sync_output(
                        $form_output_key, $form_output["absolute"]
                    );
                if (!$session_sync["ok"])
                {
                    if (!document_workflow_discard_unpublished_instance($document_instance->value))
                        add_log(REPORT,
                            "Cannot discard provisional document instance after internship session synchronization failure.",
                            $save_user_document);
                    return (new ErrorResponse(
                        $session_sync["error"] ?? "CannotEdit",
                        $session_sync["details"] ?? "internship sessions"
                    ));
                }

                $published_tasks = document_workflow_publish_instance_tasks($document_instance->value);
                if ($published_tasks->is_error())
                    add_log(REPORT,
                        "Cannot materialize document obligations after finalization: ".strval($published_tasks),
                        $save_user_document);

                if ($form_output_key != "")
                {
                    $instance_id = (string)($document_instance->value["id"] ?? "");
                    if ($instance_id != "" && !document_workflow_mark_document_form_processed(
                        $save_user_document,
                        $form_output_key,
                        $instance_id
                    ))
                        add_log(REPORT, "Cannot mark completed document form as processed for instance ".$instance_id, $save_user_document);
                    $workspace_output = dabsic_form_resolve_output($form_output_key, false, $save_user_document);
                    if ($workspace_output["ok"] && !dabsic_form_delete_workspace($workspace_output))
                        add_log(REPORT, "Cannot remove completed document workspace for instance ".$instance_id, $save_user_document);
                }
            }
        }
        finally
        {
            document_workflow_release_finalization_lock($finalization_lock);
        }
    }

    // Backward-compatible generated-file storage remains available for callers
    // that explicitly request it without entering the document workflow.
    if ($save_user_document > 0 && !$finalize_document)
    {
        if (!can_manage_student_documents($save_user_document))
            forbidden();
        $target_user = db_select_one("codename FROM user WHERE id = $save_user_document AND authority != -1");
        if ($target_user == NULL || !isset($target_user["codename"]))
            return (new ErrorResponse("UserNotFound"));
        $directory = $Configuration->UsersDir($target_user["codename"]).
            document_builder_documentation_file_root()."/generated/";
        new_directory($directory."index.php");
        $model_name = "document";
        foreach ($data as $key => $value)
            if (strncmp($key, "docref_", 7) == 0 && trim((string)$value) != "")
            {
                $split = explode(":", (string)$value, 2);
                $model_name = pathinfo(count($split) == 2 ? $split[1] : $split[0], PATHINFO_FILENAME);
                break ;
            }
        $model_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $model_name);
        if ($model_name == "")
            $model_name = "document";
        $saved_document = $directory.datex("Ymd_His")."_".$model_name.".pdf";
        if (file_put_contents($saved_document, $content, LOCK_EX) !== strlen($content))
            return (new ErrorResponse("CannotWriteFile", $saved_document));
        @chmod($saved_document, 0640);
    }

    $print_task = NULL;
    if ($queue_for_print && !$blank_document && !$finalize_document)
    {
        if ((int)$print_context["owner_user_id"] <= 0)
            return (new ErrorResponse("InvalidParameter", "print owner"));
        if ((int)$print_context["school_id"] <= 0 && (int)$print_context["cycle_id"] > 0)
            $print_context["school_id"] = document_print_school_id_for_cycle((int)$print_context["cycle_id"]);
        if ((int)$print_context["school_id"] <= 0)
            $print_context["school_id"] = document_print_school_id_for_user((int)$print_context["owner_user_id"]);
        if ($print_label == "")
            $print_label = pathinfo($output_filename, PATHINFO_FILENAME);
        if ((string)$print_context["source_key"] == "")
            $print_context["source_key"] = "docbuilder:".$selected_document_reference.":".(int)$print_context["owner_user_id"];
        $print_task = document_print_queue_content(
            $content,
            $output_filename,
            $print_label,
            $print_context
        );
        if ($print_task->is_error())
        {
            @unlink($hash_file);
            @unlink($pdf);
            foreach ($temporary_files as $temporary_file)
                @unlink($temporary_file);
            return ($print_task);
        }
    }

    if ($mail_payment_schedule)
    {
        $mail = billing_send_payment_schedule_document(
            $mail_payment_student_id,
            $mail_payment_finance_id,
            $output_filename,
            $content
        );
        if (!is_array($mail) || isset($mail["error"]))
        {
            @unlink($hash_file);
            @unlink($pdf);
            foreach ($temporary_files as $temporary_file)
                @unlink($temporary_file);
            return (new ErrorResponse($mail["error"] ?? "CannotSendMail"));
        }
    }

    // Le Dabsic fusionné est volontairement conservé dans /tmp pour
    // permettre sa vérification manuelle, même après une génération réussie.
    @unlink($hash_file);
    @unlink($pdf);
    foreach ($temporary_files as $temporary_file)
        @unlink($temporary_file);

    if (trim($merge_process["stdout"]) != "")
        document_generation_log_raw_output("mergeconf stdout", $merge_process["stdout"]);
    if (trim($merge_process["stderr"]) != "")
        document_generation_log_raw_output("mergeconf stderr", $merge_process["stderr"]);
    if (trim($build_process["stdout"]) != "")
        document_generation_log_raw_output("docbuilder stdout", $build_process["stdout"]);
    if (trim($build_process["stderr"]) != "")
        document_generation_log_raw_output("docbuilder stderr", $build_process["stderr"]);

    if ($mail_payment_schedule)
        return (new ValueResponse([
            "msg" => $Dictionnary["BillingPaymentScheduleSent"] ?? "Échéancier envoyé par mail.",
            "saved_document" => $saved_document,
        ]));

    return (new ValueResponse([
        "filename" => $output_filename,
        "content" => $content,
        "saved_document" => $saved_document,
        "resolved_dabsic_hash" => $resolved_dabsic_hash,
        "document_instance" => ($document_instance instanceof ValueResponse) ? $document_instance->value : NULL,
        "print_task" => ($print_task instanceof ValueResponse) ? $print_task->value : NULL
    ]));
}


function CompletePrintDocTask($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_task = (int)($data["task_id"] ?? 0);
    if ($id_task <= 0)
        bad_request();
    $ret = document_print_complete_task($id_task);
    if ($ret->is_error())
        return ($ret);
    return (new ValueResponse([
        "msg" => $Dictionnary["DocumentPrintCompleted"] ?? "Document marqué comme imprimé / traité.",
        "task_id" => $id_task,
    ]));
}

function GenerateDoc($id, $data, $method, $output, $module)
{
    if (!document_generation_staff_request_allowed(is_array($data) ? $data : []))
        return (new ErrorResponse("PermissionDenied"));
    ob_start();
    $request = _GenerateDoc($id, $data, $method, $output, $module);
    $unexpected_output = ob_get_clean();

    if (trim($unexpected_output) != "")
    {
        if ($request instanceof ErrorResponse)
            return (new ErrorResponse(
                $request->label != "" ? $request->label : "DocumentGenerationFailed",
                document_generation_public_error($unexpected_output."\n".$request->details)
            ));
        return (new ErrorResponse(
            "DocumentGenerationFailed",
            document_generation_public_error($unexpected_output)
        ));
    }
    return ($request);
}

function DisplayDoc($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    if (!isset($data["path"]))
	$data["path"] = "";

    $page = $module;
    $root = $Configuration->DocDir();
    $show_hidden_entries = isset($data["show_hidden_entries"]) && $data["show_hidden_entries"] != "0";
    $path_browser_dabsic_editor =
        is_admin() &&
        isset($data["path_browser_dabsic_editor"]) &&
        $data["path_browser_dabsic_editor"] != "0";
    $html = get_dir(
        $root,
        $data["path"],
        "doc",
        0,
        "file",
        "file_browser",
        am_i_teacher(),
        "",
        false,
        "",
        NULL,
        $show_hidden_entries,
        $path_browser_dabsic_editor
    );

    return (new ValueResponse([
	"content" => $html
    ]));
}

function AddDoc($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    if (!isset($data["file"]) || !is_array($data["file"]))
	bad_request();
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	$nam = pathinfo($files["name"], PATHINFO_FILENAME);
	$target = $Configuration->DocDir();
	$ext = pathinfo($files["name"], PATHINFO_EXTENSION);
	$content = base64_decode($files["content"]);
	if ($ext == "html")
	    $ext = "htm";
	if ($ext == "jpeg")
	    $ext = "jpg";
	if (!in_array($ext, $types = ["pdf", "htm", "dab", "json", "ini", "xml", "txt", "bin", "dat", "png", "jpg"]))
	    return (new ErrorResponse("InvalidFile", $ext, $Dictionnary["SupportedFormats"].": ".implode(", ", $types)));
	$ret = hand_request([
	    "command" => "installdoc",
	    "codename" => $files["name"],
	    "doc" => $files["content"],
	]);
	if (!is_array($ret) || !isset($ret["result"]) || $ret["result"] != "ok")
	{
	    $reason = document_upload_distrans_reason($ret);

	    // Distrans replaced InfosphereHand and does not necessarily implement
	    // the historical installdoc command. The document directory is local
	    // to the Infosphere, so this compatibility failure must not prevent
	    // the actual local upload.
	    if (!preg_match('/\bUnknownCommand\b.*\binstalldoc\b/i', $reason))
		return (new ErrorResponse("InfosphereHandDoesNotRun", $reason));

	    add_log(REPORT, "Distrans compatibility: installdoc is unavailable; using the local document upload.");
	}
	new_directory($target);
	if (file_put_contents("$target$nam.$ext", $content) === false)
	    return (new ErrorResponse("InternalError", "$target$nam.$ext"));
    }
    $ret = DisplayDoc(0, $data, "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Added"];
    return ($ret);
}

function ExpireDocRequest($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $kind = trim((string)($data["kind"] ?? ""));
    if ($kind === "form")
        $ret = document_workflow_expire_document_form($data["form_id"] ?? 0);
    else if ($kind === "instance")
        $ret = document_workflow_expire_instance(
            $data["owner_user_id"] ?? 0,
            $data["instance_id"] ?? ""
        );
    else
        return (new ErrorResponse("InvalidParameter", "kind"));

    if ($ret->is_error())
        return ($ret);
    return (new ValueResponse([
        "msg" => $Dictionnary["DocumentRequestExpired"] ?? "Demande de document périmée."
    ]));
}

function ReopenDocRequest($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $ret = document_workflow_reopen_instance(
        $data["owner_user_id"] ?? 0,
        $data["instance_id"] ?? ""
    );
    if ($ret->is_error())
        return ($ret);
    return (new ValueResponse([
        "msg" => $Dictionnary["DocumentReopened"] ?? "Document rouvert : les anciennes signatures sont désormais obsolètes.",
        "content" => (string)($ret->value["content"] ?? ""),
    ]));
}

function CompleteDocTask($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ((int)($data["form_id"] ?? 0) > 0)
        $ret = document_workflow_complete_form_role_as_staff(
            $data["form_id"],
            $data["role"] ?? ""
        );
    else
        $ret = document_workflow_complete_workspace_role_as_staff(
            $data["owner_user_id"] ?? 0,
            $data
        );
    if ($ret->is_error())
        return ($ret);
    return (new ValueResponse([
        "msg" => $Dictionnary["DocumentTaskCompleted"] ?? "Partie documentaire validée."
    ]));
}

function RemindDocSignatures($id, $data, $method, $output, $module)
{
    $ret = document_workflow_remind_pending_signatures(
        $data["owner_user_id"] ?? 0,
        $data["instance_id"] ?? "",
        $data["slot"] ?? ""
    );
    if ($ret->is_error())
        return ($ret);
    $sent = (int)($ret->value["sent"] ?? 0);
    return (new ValueResponse([
        "msg" => $sent > 1 ? "$sent demandes de signature envoyées." : ($sent == 1 ? "Demande de signature envoyée." : "Aucune signature en attente."),
        "sent" => $sent,
    ]));
}

function RedeliverDoc($id, $data, $method, $output, $module)
{
    $ret = document_workflow_retry_delivery(
        $data["owner_user_id"] ?? 0,
        $data["instance_id"] ?? ""
    );
    if ($ret->is_error())
        return ($ret);
    return (new ValueResponse([
        "msg" => !empty($ret->value["accepted"])
            ? "Le document a été remis au service d'envoi. Le statut de livraison sera vérifié automatiquement."
            : "La nouvelle tentative d'envoi n'a pas abouti pour tous les destinataires.",
        "accepted" => !empty($ret->value["accepted"]),
        "failed" => $ret->value["failed"] ?? [],
    ]));
}

function DeleteDoc($id, $data, $method, $output, $module)
{
    global $Configuration;

    if (!isset($data["file"]))
	bad_request();
    if (substr($data["file"], 0, 1) == "-")
	$data["file"] = substr($data["file"], 1);
    $normal_dir = $Configuration->DocDir();
    $file = $data["file"];
    $file = str_replace("@", "/", $file);
    if (strncmp($normal_dir, $file, strlen($normal_dir)) != 0)
	bad_request();

    /* Actuellement non implémenté
    $ret = hand_request([
	"command" => "deletedoc",
	"codename" => $file
    ]);
    if (!isset($ret["result"]) || $ret["result"] != "ok")
	return (new ErrorResponse(@$ret["message"]));
    */

    // Tout est bon, on envoi à la poubelle
    if (remove_ressource_file("doc", "", $file) == false)
	bad_request();

    $ret = DisplayDoc(-1, $data, "GET", $output, $module);
    $ret->value["msg"] = "Deleted";
    return ($ret);
}

$Tab = [
    "PUT" => [
	"file" => [
	    "am_i_teacher",
	    "DisplayDoc",
	]
    ],
    "POST" => [
	"" => [
	    "am_i_teacher",
	    "AddDoc",
	],
	"generate" => [
	    "logged_in",
	    "GenerateDoc",
	],
        "print" => [
            "logged_in",
            "CompletePrintDocTask",
        ],
        "context" => [
            "logged_in",
            "ResolveDocContext",
        ],
	"expire" => [
	    "am_i_teacher",
	    "ExpireDocRequest",
	],
	"reopen" => [
	    "am_i_teacher",
	    "ReopenDocRequest",
	],
	"task" => [
	    "am_i_teacher",
	    "CompleteDocTask",
	],
        "remind" => [
            "logged_in",
            "RemindDocSignatures",
        ],
        "redeliver" => [
            "am_i_teacher",
            "RedeliverDoc",
        ]
    ],
    "DELETE" => [
	"file" => [
	    "am_i_teacher",
	    "DeleteDoc",
	]
    ],
];
