<?php

require_once ("./tools/document_sources.php");
require_once ("./tools/document_context.php");
require_once ("./tools/dabsic_form.php");
require_once ("./tools/document_workflow.php");



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

function document_generation_docbuilder_command($input, $output, $blank = false)
{
    return (
        "docbuilder".($blank ? " --blank" : "").
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

function document_generation_apply_blank_context(&$fields, &$files, &$temporary_files, array $chain)
{
    $id_school = document_generation_blank_school_id($chain);
    if ($id_school == NULL)
        return (false);

    // Reuse the normal context builder, but inject only School. Student,
    // staff, custom fields and saved form values deliberately stay absent.
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


function document_generation_keep_dab($merged)
{
    $root = dirname(__DIR__);
    $directory = $root."/dres/debug/documents";

    if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory))
        return (NULL);
    if (!is_file($merged))
        return (NULL);

    $name = pathinfo($merged, PATHINFO_FILENAME);
    $target = $directory."/".$name.".dab";
    if (!@copy($merged, $target))
        return (NULL);
    return ($target);
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
    $out = "\n\n===== DEBUG GENERATION DOCUMENT =====\n";
    $out .= "Appel prévu à DocBuilder :\n".$docbuilder_command."\n";
    $out .= "\nFichiers de contexte hors dépôt :\n";

    foreach (array_values(array_unique($temporary_files)) as $file)
        $out .= document_generation_debug_file($file);

    // Le Dabsic fusionné peut être très volumineux. Le recopier intégralement
    // dans la réponse HTTP repoussait la fin de stderr hors de la réponse
    // visible. Il est conservé séparément et son chemin suffit ici.
    $out .= "Fichier Dabsic fusionné : ".$merged."\n";
    $out .= "===== FIN DEBUG GENERATION DOCUMENT =====";
    return ($out);
}

function _GenerateDoc($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    $files = [];
    $context_files = [];
    $fields = [];
    $temporary_files = [];
    $form_output_key = isset($data["form_output"]) ? (string)$data["form_output"] : "";
    $save_user_document = isset($data["save_user_document"]) ? (int)$data["save_user_document"] : 0;
    $finalize_document = !empty($data["finalize_document"]);
    $blank_document = !empty($data["blank_document"]);
    $target_year = isset($data["target_year"]) ? (int)$data["target_year"] : 0;
    $signature_bindings = $data["signature_bindings"] ?? [];
    $selected_document_reference = "";
    $selected_document_file = "";
    unset(
        $data["action"], $data["form_output"], $data["save_user_document"],
        $data["finalize_document"], $data["blank_document"], $data["target_year"],
        $data["signature_bindings"]
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

    $document_chain = isset($data["chain"])
        ? document_context_parse_chain($data["chain"]) : [];
    if ($blank_document)
    {
        if (!document_generation_apply_blank_context(
            $fields,
            $context_files,
            $temporary_files,
            $document_chain
        ))
            return (new ErrorResponse(
                "MissingField",
                "School"
            ));
    }
    else if (count($document_chain))
        document_context_apply_chain(
            $fields,
            $document_chain,
            $context_files,
            $temporary_files
        );
    unset($data["chain"]);

    // Nouveau format robuste: doc_<hash>=0|1 + docref_<hash>=source:path.
    foreach ($data as $key => $val)
    {
        if (strncmp($key, "doc_", 4) != 0 || $val == 0)
            continue ;
        $hash = substr($key, 4);
        if (!isset($data["docref_".$hash]))
            continue ;
        $reference = (string)$data["docref_".$hash];
        $file = document_reference_to_path($reference);
        if ($selected_document_reference == "")
        {
            $selected_document_reference = $reference;
            $selected_document_file = $file;
        }
        if ($val == 1)
            array_unshift($files, $file);
        else
            $files[] = $file;
    }

    // Ancien format conservé pour compatibilité avec les pages encore non migrées.
    foreach ($data as $file => $val)
    {
        if ($val == 0 || strncmp($file, "doc_", 4) == 0 || strncmp($file, "docref_", 7) == 0)
            continue ;
        $file = str_replace("_dab", ".dab", $file);
        $file = $Configuration->DocDir().$file;
        if (!file_exists($file))
            continue ;
        if ($val == 1)
            array_unshift($files, $file);
        else
            $files[] = $file;
    }

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
            $required_roles = document_workflow_required_model_roles($selected_document_file);
            if (count($required_roles) && document_workflow_completed_form_for_output($save_user_document, $form_output_key) == NULL)
                return (new ErrorResponse(
                    "DocumentRequiredTasks",
                    implode(", ", array_values($required_roles))
                ));
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

    // On exécute explicitement mergeconf avant docbuilder.
    // DocBuilder sait aussi résoudre des fichiers Dabsic, mais dans ce mode
    // ses erreurs internes ne remontent pas toujours le stderr de mergeconf.
    // La page Documents doit afficher les erreurs Dabsic sans jamais exposer
    // la sortie de travail stdout.
    $merge_command = document_generation_mergeconf_command($files, $fields, $merged);
    $docbuilder_command = document_generation_docbuilder_command($merged, $pdf, $blank_document);
    $merge_process = document_generation_run_command($merge_command);
    $kept_dab = document_generation_keep_dab($merged);
    if ($merge_process["status"] != 0 || !file_exists($merged))
    {
        $processes = [
            "mergeconf" => $merge_process
        ];
        $details = document_generation_process_combined_error($processes);
        $raw_output = document_generation_full_process_output($processes);
        if ($raw_output != "")
            $details .= "\n\n===== SORTIE COMPLÈTE DES PROCESSUS =====\n".$raw_output.
                "\n===== FIN SORTIE COMPLÈTE DES PROCESSUS =====";
        $details .= document_generation_debug_report($docbuilder_command, $temporary_files, $merged);
        if ($kept_dab !== NULL)
            $details .= "\nFichier Dabsic conservé : ".$kept_dab;
        // Conserver le Dabsic fusionné afin de pouvoir reproduire et
        // diagnostiquer manuellement l’appel à DocBuilder. Son chemin est
        // déjà présent dans le rapport de débogage.
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
            $details .= "\n\n===== SORTIE COMPLÈTE DES PROCESSUS =====\n".$raw_output.
                "\n===== FIN SORTIE COMPLÈTE DES PROCESSUS =====";
        $details .= document_generation_debug_report($docbuilder_command, $temporary_files, $merged);
        if ($kept_dab !== NULL)
            $details .= "\nFichier Dabsic conservé : ".$kept_dab;
        // Conserver le Dabsic fusionné afin de pouvoir le tester
        // directement avec mergeconf/docbuilder après un échec.
        @unlink($pdf);
        foreach ($temporary_files as $temporary_file)
            @unlink($temporary_file);
        return (new ErrorResponse("DocumentGenerationFailed", $details));
    }

    $saved_document = "";
    $document_instance = NULL;
    $task_plan = document_generation_task_plan($merged);
    if ($finalize_document && $save_user_document > 0)
    {
        if (!is_director_for_student($save_user_document))
            forbidden();
        if ($selected_document_reference == "" || $selected_document_file == "")
            return (new ErrorResponse("InvalidParameter", "document model"));
        $document_instance = document_workflow_create_frozen_instance(
            $save_user_document,
            $selected_document_reference,
            $selected_document_file,
            $target_year,
            $signature_bindings,
            $content,
            $task_plan
        );
        if ($document_instance->is_error())
            return ($document_instance);
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

    // Backward-compatible generated-file storage remains available for callers
    // that explicitly request it without entering the document workflow.
    if ($save_user_document > 0 && !$finalize_document)
    {
        if (!is_director_for_student($save_user_document))
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

    // Le Dabsic fusionné est volontairement conservé dans /tmp pour
    // permettre sa vérification manuelle, même après une génération réussie.
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

    return (new ValueResponse([
        "filename" => $output_filename,
        "content" => $content,
        "saved_document" => $saved_document,
        "document_instance" => ($document_instance instanceof ValueResponse) ? $document_instance->value : NULL
    ]));
}


function GenerateDoc($id, $data, $method, $output, $module)
{
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
        is_teacher(),
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

function CompleteDocTask($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $ret = document_workflow_complete_form_role_as_staff(
        $data["form_id"] ?? 0,
        $data["role"] ?? ""
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
	    "is_teacher",
	    "DisplayDoc",
	]
    ],
    "POST" => [
	"" => [
	    "is_teacher",
	    "AddDoc",
	],
	"generate" => [
	    "is_teacher",
	    "GenerateDoc",
	],
	"expire" => [
	    "is_teacher",
	    "ExpireDocRequest",
	],
	"task" => [
	    "is_teacher",
	    "CompleteDocTask",
	],
        "remind" => [
            "logged_in",
            "RemindDocSignatures",
        ]
    ],
    "DELETE" => [
	"file" => [
	    "is_teacher",
	    "DeleteDoc",
	]
    ],
];
