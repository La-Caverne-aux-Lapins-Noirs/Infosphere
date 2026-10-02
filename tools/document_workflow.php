<?php

require_once (__DIR__."/document_signatures.php");
require_once (__DIR__."/generate_dabsic.php");
require_once (__DIR__."/document_tasks.php");
require_once (__DIR__."/internship_session_sync.php");
require_once (__DIR__."/run_command.php");

/**
 * Generic persisted state for a generated document.
 *
 * This layer deliberately does not send signature requests yet. It freezes the
 * exact PDF that will later be submitted to signatories and records the
 * expected roles. Albedo will own the following state transitions.
 */

function document_workflow_root()
{
    return (document_builder_documentation_file_root()."/instances");
}


function document_workflow_acquire_finalization_lock($id_user, $output_key)
{
    $id_user = (int)$id_user;
    $output_key = trim((string)$output_key);
    if ($id_user <= 0 || $output_key == "")
        return (NULL);
    $file = rtrim(sys_get_temp_dir(), "/")."/infosphere-document-finalize-".
        hash("sha256", $id_user."|".$output_key).".lock";
    $handle = @fopen($file, "c");
    if ($handle === false)
        return (NULL);
    if (!@flock($handle, LOCK_EX))
    {
        @fclose($handle);
        return (NULL);
    }
    return ($handle);
}

function document_workflow_release_finalization_lock($handle)
{
    if (is_resource($handle))
    {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function document_workflow_new_id()
{
    try
    {
        $random = bin2hex(random_bytes(8));
    }
    catch (Throwable $e)
    {
        $random = substr(sha1(uniqid("document", true).mt_rand()), 0, 16);
    }
    return (date("YmdHis")."_".$random);
}

function document_workflow_actor_id()
{
    global $OriginalUser;
    global $User;

    if (isset($OriginalUser["id"]))
        return ((int)$OriginalUser["id"]);
    if (isset($User["id"]))
        return ((int)$User["id"]);
    return (0);
}

function document_workflow_school_codename_for_user($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (NULL);
    $school = db_select_one("school.codename AS codename FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id_user AND school.deleted IS NULL
        ORDER BY user_school.id ASC");
    if ($school == NULL || trim((string)($school["codename"] ?? "")) == "")
        return (NULL);
    return ((string)$school["codename"]);
}

function document_workflow_instance_directory($user_codename, $instance_id)
{
    global $Configuration;

    if (!preg_match('/^[A-Za-z0-9_-]+$/D', (string)$instance_id))
        return (NULL);
    return ($Configuration->UsersDir($user_codename).document_workflow_root()."/".$instance_id."/");
}

function document_workflow_signature_state(array $schema, array $bindings)
{
    $out = [];
    foreach ($schema as $role => $definition)
    {
        if (!document_signature_slot_is_valid($role))
            continue ;
        $out[$role] = [
            "required" => !empty($definition["required"]),
            "source" => isset($bindings[$role]) ? (string)$bindings[$role] : "",
            "status" => "Pending",
        ];
        $role_label = trim((string)($definition["role_label"] ?? ""));
        if ($role_label != "")
            $out[$role]["role_label"] = $role_label;
    }
    return ($out);
}

function document_workflow_signature_semantic_role($slot, array $signature)
{
    $role = trim((string)($signature["TaskRole"] ?? ($signature["task_role"] ?? "")));
    return (document_task_role_is_valid($role) ? $role : (string)$slot);
}

function document_workflow_signature_role_label($slot, array $signature)
{
    $label = trim((string)($signature["RoleLabel"] ?? ($signature["role_label"] ?? "")));
    return ($label != "" ? $label : document_workflow_signature_semantic_role($slot, $signature));
}

function document_workflow_signature_is_external_campaign(array $signature)
{
    return (trim((string)($signature["ExternalCampaignInstanceId"] ?? ($signature["external_campaign_instance_id"] ?? ""))) != "");
}

function document_workflow_signature_assignee_user_id($owner_user_id, array $signature, $created_by = 0)
{
    $direct = (int)($signature["SignatoryUserId"] ?? ($signature["signatory_user_id"] ?? 0));
    if ($direct > 0)
        return ($direct);
    $source = (string)($signature["Source"] ?? ($signature["source"] ?? ""));
    return ((int)(document_workflow_source_user_id((int)$owner_user_id, $source, (int)$created_by) ?? 0));
}

/**
 * Dynamic sign tasks become ordinary technical signature slots in the frozen
 * instance.  Slot is unique, TaskRole remains semantic: Teacher_42 and
 * Teacher_57 are both Role=Teacher.
 */
function document_workflow_signature_state_apply_task_plan(array $state, array $task_plan)
{
    foreach (document_task_plan_normalize($task_plan) as $slot => $definition)
    {
        if ($definition["action"] !== "sign" || !document_signature_slot_is_valid($slot))
            continue ;
        if (!isset($state[$slot]) || !is_array($state[$slot]))
            $state[$slot] = [
                "required" => $definition["required"],
                "source" => "",
                "status" => "Pending",
            ];
        $state[$slot]["task_role"] = $definition["role"];
        $state[$slot]["role_label"] = $definition["role_label"];
        if ((int)$definition["id_assignee_user"] > 0)
            $state[$slot]["signatory_user_id"] = (int)$definition["id_assignee_user"];
    }
    return ($state);
}

function document_workflow_instance_task_plan(array $instance)
{
    $plan = $instance["TaskPlan"] ?? ($instance["task_plan"] ?? []);
    return (is_array($plan) ? document_task_plan_normalize($plan) : []);
}

function document_workflow_signature_obligation_plan($owner_user_id, array $signatures,
    array $task_plan, $created_by = 0)
{
    $plan = document_task_plan_normalize($task_plan);
    foreach ($signatures as $slot => $signature)
    {
        if (!document_signature_slot_is_valid($slot) || !is_array($signature))
            continue ;
        $role = document_workflow_signature_semantic_role($slot, $signature);
        $label = document_workflow_signature_role_label($slot, $signature);
        $assignee = document_workflow_signature_assignee_user_id($owner_user_id, $signature, $created_by);
        $required = document_task_plan_bool($signature["Required"] ?? ($signature["required"] ?? true), true);
        $source = (string)($signature["Source"] ?? ($signature["source"] ?? ""));

        if (!isset($plan[$slot]))
            $plan[$slot] = [
                "action" => "sign",
                "role" => $role,
                "role_label" => $label,
                "id_assignee_user" => $assignee,
                "assignee_label" => "",
                "required" => $required,
                "metadata" => [],
            ];
        else if ((int)$plan[$slot]["id_assignee_user"] <= 0 && $assignee > 0)
            $plan[$slot]["id_assignee_user"] = $assignee;
        $plan[$slot]["metadata"]["slot"] = (string)$slot;
        if ($source != "")
            $plan[$slot]["metadata"]["source"] = $source;
    }
    return (document_task_plan_normalize($plan));
}

function document_workflow_materialize_instance_task_plan(array $instance)
{
    $plan = document_workflow_instance_task_plan($instance);
    if (!count($plan))
        return (new ValueResponse(["available" => document_task_table_available(), "tasks" => [], "plan" => []]));
    return (document_task_materialize_plan(
        (int)($instance["OwnerUserId"] ?? ($instance["owner_user_id"] ?? 0)),
        0,
        (string)($instance["Id"] ?? ($instance["id"] ?? "")),
        $plan,
        "instance-obligations"
    ));
}

function document_workflow_create_frozen_instance($id_user, $model_reference, $model_file, $target_year, $signature_bindings, $pdf_content, array $task_plan = [], array $extra_metadata = [], $materialize_tasks = true)
{
    global $Configuration;

    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (new ErrorResponse("InvalidParameter", "id_user"));
    if (!is_string($pdf_content) || substr($pdf_content, 0, 4) !== "%PDF")
        return (new ErrorResponse("InvalidParameter", "pdf"));

    $target_user = db_select_one("codename FROM user WHERE id = $id_user AND authority != -1");
    if ($target_user == NULL || !isset($target_user["codename"]))
        return (new ErrorResponse("UserNotFound"));

    $schema = document_signature_model_slots($model_file);
    $bindings = document_signature_normalize_bindings($signature_bindings, array_keys($schema));
    $normalized_task_plan = document_task_plan_normalize($task_plan);
    $missing = document_signature_missing_required_bindings($schema, $bindings);
    $missing = array_values(array_filter($missing, function ($slot) use ($normalized_task_plan) {
        return (!(isset($normalized_task_plan[$slot])
            && $normalized_task_plan[$slot]["action"] === "sign"
            && (int)$normalized_task_plan[$slot]["id_assignee_user"] > 0));
    }));
    if (count($missing))
        return (new ErrorResponse("DocumentRequiredSignatories", implode(", ", $missing)));

    $signature_state = document_workflow_signature_state($schema, $bindings);
    $signature_state = document_workflow_signature_state_apply_task_plan($signature_state, $normalized_task_plan);

    $instance_id = document_workflow_new_id();
    $directory = document_workflow_instance_directory($target_user["codename"], $instance_id);
    if ($directory === NULL)
        return (new ErrorResponse("CannotWriteFile", "document instance"));
    if (($mkdir = new_directory($directory."index.php"))->is_error())
        return ($mkdir);

    $frozen_pdf = $directory."frozen.pdf";
    if (file_put_contents($frozen_pdf, $pdf_content, LOCK_EX) !== strlen($pdf_content))
        return (new ErrorResponse("CannotWriteFile", $frozen_pdf));
    @chmod($frozen_pdf, 0640);

    $frozen_dabsic = "";
    $frozen_dabsic_hash = "";
    $resolved_dabsic_file = trim((string)($extra_metadata["resolved_dabsic_file"] ?? ""));
    if ($resolved_dabsic_file != "")
    {
        if (!is_file($resolved_dabsic_file) || !is_readable($resolved_dabsic_file))
        {
            @unlink($frozen_pdf);
            return (new ErrorResponse("MissingFile", "resolved Dabsic"));
        }
        $frozen_dabsic = $directory."frozen.dab";
        if (!@copy($resolved_dabsic_file, $frozen_dabsic))
        {
            @unlink($frozen_pdf);
            return (new ErrorResponse("CannotWriteFile", $frozen_dabsic));
        }
        @chmod($frozen_dabsic, 0640);
        $frozen_dabsic_hash = (string)hash_file("sha256", $frozen_dabsic);
        if (!preg_match('/^[a-f0-9]{64}$/D', $frozen_dabsic_hash))
        {
            @unlink($frozen_dabsic);
            @unlink($frozen_pdf);
            return (new ErrorResponse("CannotReadFile", $frozen_dabsic));
        }
    }

    $created_by = document_workflow_actor_id();
    $obligation_plan = document_workflow_signature_obligation_plan(
        $id_user, $signature_state, $normalized_task_plan, $created_by
    );
    $has_signatures = count($signature_state) > 0;
    $school_codename = document_workflow_school_codename_for_user($id_user);
    $created_at = date("Y-m-d H:i:s");
    $frozen_hash = hash("sha256", $pdf_content);
    $metadata = [
        "id" => $instance_id,
        "owner_user_id" => $id_user,
        "school_codename" => $school_codename ?? "",
        "model" => (string)$model_reference,
        "target_year" => max(0, min(5, (int)$target_year)),
        "status" => $has_signatures ? "AwaitingSignature" : "Completed",
        "created_at" => $created_at,
        "created_by" => $created_by,
        "frozen_hash" => $frozen_hash,
        "frozen_file" => "frozen.pdf",
        "signatures" => $signature_state,
    ];
    if ($frozen_dabsic != "")
    {
        $metadata["frozen_dabsic_file"] = "frozen.dab";
        $metadata["frozen_dabsic_hash"] = $frozen_dabsic_hash;
    }
    // A document without signature slots is already the immutable final PDF.
    // Persist the normal final metadata immediately so Albedo can archive and
    // deliver it instead of looking for a non-existent sealed.pdf.
    if (!$has_signatures)
    {
        $metadata["completed_at"] = $created_at;
        $metadata["final_file"] = "frozen.pdf";
        $metadata["final_hash"] = $frozen_hash;
        $metadata["completion_mode"] = "NoSignature";
    }
    if (count($obligation_plan))
        $metadata["task_plan"] = $obligation_plan;

    // Callers may attach non-authoritative workflow metadata (archive targets,
    // source context, display label, resolved Dabsic fingerprint). Core integrity/state fields above cannot
    // be overridden through this extension point.
    foreach (["display_name", "archive_targets", "source_context", "resolved_dabsic_hash", "delivery_users", "delivery_explicit"] as $extra_key)
        if (array_key_exists($extra_key, $extra_metadata))
            $metadata[$extra_key] = $extra_metadata[$extra_key];
    if (array_key_exists("queue_for_print", $extra_metadata))
        $metadata["queue_for_print"] = !empty($extra_metadata["queue_for_print"]) ? 1 : 0;
    if (array_key_exists("print_recipient", $extra_metadata)
        && trim((string)$extra_metadata["print_recipient"]) != "")
        $metadata["print_recipient"] = trim((string)$extra_metadata["print_recipient"]);
    if (array_key_exists("require_pdf_sign", $extra_metadata))
        $metadata["require_pdf_sign"] = !empty($extra_metadata["require_pdf_sign"]) ? 1 : 0;

    $metadata_file = $directory."instance.dab";
    $generated = generate_dabsic($metadata, $metadata_file);
    if ($generated->is_error())
    {
        @unlink($frozen_dabsic);
        @unlink($frozen_pdf);
        return ($generated);
    }
    @chmod($metadata_file, 0640);

    if ($materialize_tasks && count($obligation_plan))
    {
        $materialized = document_task_materialize_plan(
            $id_user, 0, $instance_id, $obligation_plan, "instance-obligations"
        );
        if ($materialized->is_error())
            add_log(REPORT, "Cannot materialize document obligations for instance $instance_id: ".strval($materialized), $id_user);
    }

    return (new ValueResponse([
        "id" => $instance_id,
        "status" => $metadata["status"],
        "hash" => $metadata["frozen_hash"],
        "directory" => $directory,
        "pdf" => $frozen_pdf,
        "dabsic" => $frozen_dabsic,
        "metadata" => $metadata_file,
    ]));
}

/**
 * Remove an instance created during a finalization attempt before it has been
 * published to the rest of the workflow.  This is intentionally conservative:
 * only the three files created by document_workflow_create_frozen_instance()
 * before task materialization are accepted.
 */
function document_workflow_discard_unpublished_instance(array $created)
{
    $directory = rtrim((string)($created["directory"] ?? ""), "/")."/";
    $pdf = (string)($created["pdf"] ?? "");
    $dabsic = (string)($created["dabsic"] ?? "");
    $metadata = (string)($created["metadata"] ?? "");
    if ($directory === "/" || $directory === "./" || !is_dir($directory))
        return (false);

    foreach ([$pdf, $dabsic, $metadata] as $file)
    {
        if ($file === "")
            continue ;
        $parent = rtrim(dirname($file), "/")."/";
        if ($parent !== $directory)
            return (false);
    }

    $ok = true;
    foreach ([$pdf, $dabsic, $metadata, $directory."index.php"] as $file)
        if ($file !== "" && is_file($file) && !@unlink($file))
            $ok = false;
    if (is_dir($directory) && !@rmdir($directory))
        $ok = false;
    return ($ok);
}

function document_workflow_publish_instance_tasks(array $created)
{
    $directory = (string)($created["directory"] ?? "");
    if ($directory === "")
        return (new ErrorResponse("InvalidParameter", "document instance"));
    $loaded = document_workflow_load_instance($directory);
    if ($loaded->is_error())
        return ($loaded);
    return (document_workflow_materialize_instance_task_plan($loaded->value["data"]));
}

function document_workflow_load_instance($directory)
{
    $file = rtrim((string)$directory, "/")."/instance.dab";
    if (!is_file($file))
        return (new ErrorResponse("MissingFile", $file));
    $loaded = load_configuration($file, [], false);
    if ($loaded->is_error() || !is_array($loaded->value))
        return ($loaded->is_error() ? $loaded : new ErrorResponse("InvalidFile", $file));
    return (new ValueResponse(["file" => $file, "directory" => dirname($file)."/", "data" => $loaded->value]));
}

function document_workflow_write_instance($file, array $data)
{
    $ret = generate_dabsic($data, $file);
    if (!$ret->is_error())
        @chmod($file, 0640);
    return ($ret);
}

function document_workflow_find_instance($owner_user_id, $instance_id)
{
    $owner_user_id = (int)$owner_user_id;
    $user = db_select_one("codename FROM user WHERE id = $owner_user_id AND authority != -1");
    if ($user == NULL)
        return (new ErrorResponse("UserNotFound"));
    $directory = document_workflow_instance_directory($user["codename"], $instance_id);
    if ($directory === NULL)
        return (new ErrorResponse("InvalidParameter", "instance_id"));
    return (document_workflow_load_instance($directory));
}

function document_workflow_source_user_id($owner_user_id, $source, $created_by = 0, array $semantic_bindings = [])
{
    $owner_user_id = (int)$owner_user_id;
    $source = trim((string)$source);
    if ($source != "" && isset($semantic_bindings[$source]))
    {
        $bound = trim((string)$semantic_bindings[$source]);
        if (preg_match('/^User_([0-9]+)$/D', $bound, $m))
        {
            $id = (int)$m[1];
            if (db_select_one("id FROM user WHERE id = $id AND authority != -1") != NULL)
                return ($id);
        }
        if (function_exists("document_context_user_id"))
        {
            $id = document_context_user_id($bound);
            if ($id !== NULL && $id > 0)
                return ((int)$id);
        }
    }
    if (preg_match('/^User_([0-9]+)$/D', $source, $m))
    {
        $id = (int)$m[1];
        return (db_select_one("id FROM user WHERE id = $id AND authority != -1") != NULL ? $id : NULL);
    }
    if ($source == "Student")
        return ($owner_user_id);
    if ($source == "Generator")
        return ((int)$created_by > 0 ? (int)$created_by : NULL);

    if ($source == "Director")
    {
        $school = db_select_one("id_school FROM user_school WHERE id_user = $owner_user_id ORDER BY id_school ASC");
        if ($school == NULL)
            return (NULL);
        $row = db_select_one("user.id AS id FROM user_school LEFT JOIN user ON user.id = user_school.id_user
            WHERE user_school.id_school = ".(int)$school["id_school"]."
              AND (user_school.authority = 'DIRECTOR' OR user_school.authority = 1)
              AND user.id IS NOT NULL AND user.authority != -1
            ORDER BY user.id ASC");
        return ($row == NULL ? NULL : (int)$row["id"]);
    }

    if (in_array($source, ["Legal1", "Legal2", "Finance"], true))
    {
        $relation = $source == "Finance" ? "financial" : "legal";
        $wanted = $source == "Legal2" ? 1 : 0;
        $rows = db_select_all("user.id AS id, parent_child.relation AS relation FROM parent_child
            LEFT JOIN user ON user.id = parent_child.id_parent
            WHERE parent_child.id_child = $owner_user_id AND user.id IS NOT NULL AND user.authority != -1
            ORDER BY parent_child.id ASC");
        $matches = [];
        foreach ($rows as $row)
            if (function_exists("user_relation_has") && user_relation_has($row["relation"] ?? "", $relation))
                $matches[] = $row;
        if (isset($matches[$wanted]))
            return ((int)$matches[$wanted]["id"]);
        // Le bénéficiaire est son propre responsable financier lorsqu'aucune
        // relation financière distincte n'est déclarée.
        if ($source == "Finance")
            return ($owner_user_id);
        return (NULL);
    }
    return (NULL);
}

function document_workflow_active_forms_for_output($id_user, $output_key)
{
    $id_user = (int)$id_user;
    $output_key = trim((string)$output_key);
    if ($id_user <= 0 || $output_key == "" || !in_array("user_form", db_get_tables(), true))
        return ([]);
    $out = [];
    foreach (db_select_all("* FROM user_form
        WHERE id_user = $id_user AND kind LIKE 'DOC-%' AND revoked_at IS NULL
          AND (completed_at IS NOT NULL OR expires_at >= NOW())
        ORDER BY id DESC") as $form)
    {
        $document = document_workflow_document_form_metadata($form);
        if (trim((string)($document["output"] ?? "")) !== $output_key
            || trim((string)($document["processed_at"] ?? "")) != "")
            continue ;
        $out[] = $form;
    }
    return ($out);
}

function document_workflow_completed_roles_for_output($id_user, $output_key)
{
    $roles = [];
    foreach (document_workflow_active_forms_for_output($id_user, $output_key) as $form)
    {
        if (($form["completed_at"] ?? NULL) === NULL)
            continue ;
        $document = document_workflow_document_form_metadata($form);
        $role = trim((string)($document["form_role"] ?? ""));
        if ($role != "")
            $roles[$role] = true;
    }
    return ($roles);
}

function document_workflow_form_role_dependencies_satisfied($id_user, $output_key, array $definition)
{
    $dependencies = is_array($definition["depends_on"] ?? NULL)
        ? $definition["depends_on"] : [];
    if (!count($dependencies))
        return (true);
    $completed = document_workflow_completed_roles_for_output($id_user, $output_key);
    foreach ($dependencies as $role)
        if (!isset($completed[(string)$role]))
            return (false);
    return (true);
}

function document_workflow_user_can_receive_form($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);
    $user = db_select_one("mail FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL)
        return (false);
    $mail = trim((string)($user["mail"] ?? ""));
    return ($mail != "" && strcasecmp($mail, "nomail") != 0
        && filter_var($mail, FILTER_VALIDATE_EMAIL) !== false);
}

function document_workflow_form_role_primary_assignee($id_user, array $definition, $created_by = 0, array $semantic_bindings = [])
{
    $source = trim((string)($definition["recipient"] ?? "Student"));
    return ((int)(document_workflow_source_user_id($id_user, $source, $created_by, $semantic_bindings) ?? 0));
}

function document_workflow_form_role_assignee($id_user, array $definition, $created_by = 0, array $semantic_bindings = [])
{
    $primary = document_workflow_form_role_primary_assignee($id_user, $definition, $created_by, $semantic_bindings);
    $fallback = trim((string)($definition["fallback_recipient"] ?? ""));
    if ($primary > 0 && ($fallback == "" || document_workflow_user_can_receive_form($primary)))
        return ($primary);
    if ($fallback != "")
    {
        $alternate = (int)(document_workflow_source_user_id($id_user, $fallback, $created_by, $semantic_bindings) ?? 0);
        if ($alternate > 0)
            return ($alternate);
    }
    return ($primary);
}

function document_workflow_form_role_is_required($id_user, array $definition, $created_by = 0, array $semantic_bindings = [])
{
    if (!dabsic_form_role_has_contribution($definition))
        return (false);
    if (!empty($definition["required"]))
        return (true);
    // RequiredIfRecipient tests the semantic primary recipient, not the fallback:
    // an absent second legal representative must not suddenly create a second
    // guardian form for the student.
    return (!empty($definition["required_if_recipient"])
        && document_workflow_form_role_primary_assignee($id_user, $definition, $created_by, $semantic_bindings) > 0);
}

function document_workflow_dabsic_boolean($value)
{
    $value = trim((string)$value);
    if (strlen($value) >= 2
        && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
            || ($value[0] === "'" && $value[strlen($value) - 1] === "'")))
        $value = trim(substr($value, 1, -1));
    $value = strtolower($value);
    if (in_array($value, ["1", "true", "yes", "on"], true))
        return (true);
    if (in_array($value, ["", "0", "false", "no", "off"], true))
        return (false);
    return (is_numeric($value) && (float)$value != 0.0);
}

function document_workflow_model_auto_finalize($model_file)
{
    $model_file = trim((string)$model_file);
    if ($model_file == "" || !is_file($model_file))
        return (false);

    // Do not feed the complete model to load_configuration() here. Document
    // models commonly start with a relative @include (for example
    // .base_letter/base.dab). load_configuration() pipes the file to mergeconf
    // without changing to the model directory, so that relative include makes
    // the whole parse fail and AutoFinalize used to be silently read as false.
    // Workflow is metadata: inspect that root scope directly, and only fall back
    // to included sources when the leaf model does not define it itself.
    $sources = [];
    $content = @file_get_contents($model_file);
    if ($content === false)
        return (false);
    $sources[] = $content;
    if (function_exists("dabsic_form_docbuilder_collect_sources"))
    {
        $collected = dabsic_form_docbuilder_collect_sources($model_file);
        if (is_array($collected))
            foreach ($collected as $source_file => $source_content)
            {
                if (realpath((string)$source_file) === realpath($model_file))
                    continue ;
                if (is_string($source_content))
                    $sources[] = $source_content;
            }
    }

    foreach ($sources as $source)
    {
        $scope = function_exists("dabsic_form_extract_root_scope")
            ? dabsic_form_extract_root_scope($source, "Workflow") : NULL;
        if ($scope === NULL)
            continue ;
        foreach (preg_split('/\R/', $scope) as $line)
        {
            $line = trim((string)$line);
            if ($line == "" || str_starts_with($line, "#") || str_starts_with($line, "//"))
                continue ;
            if (!preg_match('/^AutoFinalize\s*=\s*(.+?)\s*$/i', $line, $match))
                continue ;
            return (document_workflow_dabsic_boolean($match[1]));
        }
    }
    return (false);
}


function document_workflow_workspace_semantic_bindings(array $workspace)
{
    $out = [];
    foreach (["context_bindings", "signature_bindings"] as $field)
        if (isset($workspace[$field]) && is_array($workspace[$field]))
            foreach ($workspace[$field] as $name => $value)
                if (is_scalar($value) && trim((string)$value) != "")
                    $out[(string)$name] = trim((string)$value);
    return ($out);
}

function document_workflow_form_role_states($id_user, $output_key, array $roles, $created_by = 0, array $semantic_bindings = [])
{
    $id_user = (int)$id_user;
    $workspace_data = [];
    $output = dabsic_form_resolve_output($output_key, false, $id_user);
    if ($output["ok"])
    {
        $workspace = dabsic_form_load_workspace($output);
        if ($workspace["ok"] && $workspace["exists"])
        {
            $workspace_data = $workspace["data"];
            if (!count($semantic_bindings))
                $semantic_bindings = document_workflow_workspace_semantic_bindings($workspace_data);
        }
    }
    $skipped_roles = is_array($workspace_data["skipped_roles"] ?? NULL)
        ? $workspace_data["skipped_roles"] : [];
    $forms = document_workflow_active_forms_for_output($id_user, $output_key);
    $by_role = [];
    foreach ($forms as $form)
    {
        $document = document_workflow_document_form_metadata($form);
        $role = trim((string)($document["form_role"] ?? ""));
        if ($role != "" && !isset($by_role[$role]))
            $by_role[$role] = $form;
    }

    $states = [];
    foreach ($roles as $role => $definition)
    {
        if (!is_array($definition) || !document_task_role_is_valid((string)$role)
            || !dabsic_form_role_has_contribution($definition))
            continue ;
        $primary_assignee = document_workflow_form_role_primary_assignee(
            $id_user, $definition, $created_by, $semantic_bindings
        );
        $applicable = !empty($definition["required"])
            || empty($definition["required_if_recipient"])
            || $primary_assignee > 0;
        if (!$applicable)
            continue ;
        $assignee = document_workflow_form_role_assignee($id_user, $definition, $created_by, $semantic_bindings);
        $assignee_user = $assignee > 0
            ? db_select_one("first_name, family_name, codename FROM user WHERE id = $assignee AND authority != -1")
            : NULL;
        $assignee_name = $assignee_user == NULL ? "" : trim(
            (string)($assignee_user["first_name"] ?? "")." ".
            (string)($assignee_user["family_name"] ?? "")
        );
        if ($assignee_name == "" && $assignee_user != NULL)
            $assignee_name = (string)($assignee_user["codename"] ?? "");
        $form = $by_role[$role] ?? NULL;
        $states[(string)$role] = [
            "role" => (string)$role,
            "label" => trim((string)($definition["label"] ?? $role)),
            "required" => document_workflow_form_role_is_required($id_user, $definition, $created_by, $semantic_bindings),
            "recipient_source" => trim((string)($definition["recipient"] ?? "Student")),
            "fallback_recipient_source" => trim((string)($definition["fallback_recipient"] ?? "")),
            "primary_recipient_user_id" => $primary_assignee,
            "recipient_user_id" => $assignee,
            "recipient_name" => $assignee_name,
            "delegated" => $primary_assignee > 0 && $assignee > 0 && $primary_assignee !== $assignee,
            "depends_on" => is_array($definition["depends_on"] ?? NULL) ? $definition["depends_on"] : [],
            "dependencies_satisfied" => document_workflow_form_role_dependencies_satisfied($id_user, $output_key, $definition),
            "form" => $form,
            "completed" => isset($skipped_roles[(string)$role])
                || ($form != NULL && ($form["completed_at"] ?? NULL) !== NULL),
            "skipped" => isset($skipped_roles[(string)$role]),
            "pending" => !isset($skipped_roles[(string)$role])
                && $form != NULL && ($form["completed_at"] ?? NULL) === NULL,
        ];
    }
    return ($states);
}

function document_workflow_workspace_required_roles_complete($id_user, $output_key, array $roles, $created_by = 0, array $semantic_bindings = [])
{
    foreach (document_workflow_form_role_states(
        $id_user, $output_key, $roles, $created_by, $semantic_bindings
    ) as $state)
        if (!empty($state["required"]) && empty($state["completed"]))
            return (false);
    return (true);
}

function document_workflow_complete_workspace_role_as_staff($id_user, array $data)
{
    global $Database;

    $id_user = (int)$id_user;
    $role = trim((string)($data["form_role"] ?? ""));
    if (!document_workflow_can_monitor() || $id_user <= 0 || !document_task_role_is_valid($role))
        return (new ErrorResponse("PermissionDenied"));

    $reference = trim((string)($data["document_file"] ?? ""));
    $model_hash = strtolower(trim((string)($data["model_hash"] ?? "")));
    $target_year = (int)($data["target_year"] ?? 0);
    if (!preg_match('/^[a-f0-9]{32}$/D', $model_hash) || $target_year < 0 || $target_year > 5)
        return (new ErrorResponse("InvalidParameter", "document workspace role"));
    $resolved = dabsic_editor_resolve_file($reference, false);
    if (!$resolved["ok"])
        return (new ErrorResponse($resolved["error"], $resolved["details"] ?? ""));
    $source_reference = (string)(document_reference_from_editor_path($resolved["relative"]) ?? "");
    if ($source_reference == "" || !hash_equals(md5($source_reference), $model_hash))
        return (new ErrorResponse("InvalidParameter", "model_hash"));

    $metadata = dabsic_form_form_metadata($resolved["absolute"]);
    $definition = dabsic_form_role_definition($metadata, $role);
    if ($definition == NULL)
        return (new ErrorResponse("InvalidParameter", "form_role"));
    $output_key = "user-document:".$id_user.":".$model_hash.":".$target_year;
    if (!document_workflow_form_role_dependencies_satisfied($id_user, $output_key, $definition))
        return (new ErrorResponse("DocumentRoleDependencyPending", $role));

    $output = dabsic_form_resolve_output($output_key, false, $id_user);
    if (!$output["ok"])
        return (new ErrorResponse($output["error"], $output["details"] ?? ""));
    $loaded = dabsic_form_load_output_values($output);
    if (!$loaded["ok"])
        return (new ErrorResponse($loaded["error"], $loaded["details"] ?? ""));
    $values = $loaded["values"];
    $workspace = dabsic_form_load_workspace($output);
    if ($workspace["ok"] && $workspace["exists"])
        $values = array_merge(
            dabsic_form_prefill_from_chain((string)($workspace["data"]["chain"] ?? "")),
            $values
        );
    $missing = dabsic_form_missing_required_fields($metadata, $values, $role);
    if (count($missing))
        return (new ErrorResponse("DocumentRequiredFields", implode("\n", array_values($missing))));

    require_once (__DIR__."/registration_form.php");
    $actor = max(1, document_workflow_actor_id());
    $created = registration_form_create_document_invitation(
        $id_user,
        $reference,
        $model_hash,
        $target_year,
        $data["document_label"] ?? "",
        $actor,
        $data["context_bindings"] ?? [],
        $role,
        $actor
    );
    if (!$created["ok"])
        return (new ErrorResponse($created["error"] ?? "CannotEdit", $created["details"] ?? ""));
    if (!empty($created["skipped"]))
    {
        $workspace = dabsic_form_load_workspace($output);
        if (!$workspace["ok"] || !$workspace["exists"])
            return (new ErrorResponse("DocumentWorkspaceNotFound"));
        $workspace_data = $workspace["data"];
        if (!isset($workspace_data["skipped_roles"]) || !is_array($workspace_data["skipped_roles"]))
            $workspace_data["skipped_roles"] = [];
        $workspace_data["skipped_roles"][$role] = date("Y-m-d H:i:s");
        $saved_workspace = dabsic_form_save_workspace($output, $workspace_data);
        if (!$saved_workspace["ok"])
            return (new ErrorResponse($saved_workspace["error"], $saved_workspace["details"] ?? ""));
        add_log(EDITING_OPERATION, "Document role $role skipped for user $id_user: no field to complete", $id_user);
        return (new ValueResponse(["completed" => true, "skipped" => true, "role" => $role]));
    }
    $id_form = (int)$created["id"];
    // This row records an internal contribution, but no public invitation was
    // actually sent. Expire its secret immediately while keeping the completed
    // row active as workflow history.
    if ($Database->query("UPDATE user_form
        SET completed_at = NOW(), last_saved_at = NOW(),
            expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)
        WHERE id = $id_form") === false)
    {
        registration_form_revoke_token($created["token"]);
        return (new ErrorResponse("CannotEdit"));
    }
    $completed = document_task_complete_form_role($id_form, $role, $actor, $actor);
    if ($completed->is_error())
    {
        registration_form_revoke_token($created["token"]);
        return ($completed);
    }
    add_log(EDITING_OPERATION, "Document role $role completed by staff for user $id_user", $id_user);
    return (new ValueResponse(["completed" => true, "form_id" => $id_form, "role" => $role]));
}

function document_workflow_signature_file(array $instance, $directory, $role)
{
    if (!document_signature_slot_is_valid($role))
        return (NULL);
    return (rtrim((string)$directory, "/")."/signatures/".$role."/signature.png");
}

function document_workflow_initials_file(array $instance, $directory, $role)
{
    if (!document_signature_slot_is_valid($role))
        return (NULL);
    return (rtrim((string)$directory, "/")."/signatures/".$role."/initials.png");
}

function document_workflow_evidence_file($directory, $role)
{
    if (!document_signature_slot_is_valid($role))
        return (NULL);
    return (rtrim((string)$directory, "/")."/signatures/".$role."/evidence.dab");
}

function document_workflow_all_required_signed(array $instance)
{
    foreach (($instance["Signatures"] ?? []) as $role => $signature)
        if (is_array($signature) && !empty($signature["Required"])
            && (($signature["Status"] ?? "") != "Signed"))
            return (false);
    return (true);
}

/**
 * Schools whose document workflows may be monitored by the current user.
 * NULL means unrestricted (global administrator); an empty array means none.
 *
 * Do not use get_user_school() here: that helper currently contains a legacy
 * fallback school and must never grant access to confidential documents.
 */
function document_workflow_monitor_school_codenames()
{
    global $User;

    if (!isset($User["id"]) || (int)$User["id"] <= 0)
        return ([]);
    if (is_admin())
        return (NULL);

    $rows = db_select_all("school.codename AS codename, user_school.authority AS authority
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = ".(int)$User["id"]."
          AND school.id IS NOT NULL AND school.deleted IS NULL");
    $out = [];
    $numeric = [1 => "DIRECTOR", 2 => "SECRETARIAT", 3 => "COMMERCIAL"];
    foreach ($rows as $row)
    {
        $authority = $row["authority"] ?? "";
        if (is_numeric($authority))
            $authority = $numeric[(int)$authority] ?? "";
        else
            $authority = strtoupper(trim((string)$authority));
        if (!in_array($authority, ["DIRECTOR", "SECRETARIAT", "COMMERCIAL"], true))
            continue ;
        $codename = trim((string)($row["codename"] ?? ""));
        if ($codename != "")
            $out[$codename] = true;
    }
    return (array_keys($out));
}

function document_workflow_can_monitor()
{
    $schools = document_workflow_monitor_school_codenames();
    return ($schools === NULL || count($schools) > 0);
}

function document_workflow_instance_school_codenames(array $instance)
{
    $school = trim((string)($instance["SchoolCodename"] ?? ""));
    if ($school != "")
        return ([$school]);

    // Compatibility for instances frozen before SchoolCodename was persisted.
    $owner = (int)($instance["OwnerUserId"] ?? 0);
    if ($owner <= 0)
        return ([]);
    $rows = db_select_all("school.codename AS codename FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $owner
          AND school.id IS NOT NULL AND school.deleted IS NULL");
    $out = [];
    foreach ($rows as $row)
    {
        $codename = trim((string)($row["codename"] ?? ""));
        if ($codename != "")
            $out[$codename] = true;
    }
    return (array_keys($out));
}

function document_workflow_can_view_instance(array $instance)
{
    $source = $instance["SourceContext"] ?? [];
    if (is_array($source)
        && in_array((string)($source["Type"] ?? ""), ["AttendanceRegister", "AttendanceRegisterCampaign"], true)
        && (int)($source["CycleId"] ?? 0) > 0
        && is_director_for_cycle((int)$source["CycleId"]))
        return (true);

    $schools = document_workflow_monitor_school_codenames();
    if ($schools === NULL)
        return (true);
    if (!count($schools))
        return (false);
    return (count(array_intersect($schools, document_workflow_instance_school_codenames($instance))) > 0);
}


function document_workflow_form_role_definitions(array $form)
{
    $schema = document_workflow_document_form_schema($form);
    $document = isset($schema["document"]) && is_array($schema["document"])
        ? $schema["document"] : [];
    return (isset($document["form_roles"]) && is_array($document["form_roles"])
        ? $document["form_roles"] : []);
}

function document_workflow_document_form_current_values(array $form)
{
    $schema = document_workflow_document_form_schema($form);
    $document = isset($schema["document"]) && is_array($schema["document"])
        ? $schema["document"] : [];
    $values = dabsic_form_prefill_from_chain((string)($document["chain"] ?? ""));
    $output_key = trim((string)($document["output"] ?? ""));
    if ($output_key != "")
    {
        $output = dabsic_form_resolve_output($output_key, false, (int)($form["id_user"] ?? 0));
        if ($output["ok"])
        {
            $loaded = dabsic_form_load_output_values($output);
            if ($loaded["ok"])
                $values = array_merge($values, $loaded["values"]);
        }
    }
    return ($values);
}

function document_workflow_document_form_model_metadata(array $form)
{
    $document = document_workflow_document_form_metadata($form);
    $reference = trim((string)($document["reference"] ?? ""));
    if ($reference == "")
        return (dabsic_form_empty_form_metadata());
    $resolved = dabsic_editor_resolve_file($reference, false);
    return ($resolved["ok"]
        ? dabsic_form_form_metadata($resolved["absolute"])
        : dabsic_form_empty_form_metadata());
}

function document_workflow_active_workspace_for_output($id_user, $output_key, $model_hash = "", $target_year = NULL, $source_reference = "")
{
    $id_user = (int)$id_user;
    $output_key = trim((string)$output_key);
    $model_hash = strtolower(trim((string)$model_hash));
    $source_reference = trim((string)$source_reference);
    if ($id_user <= 0 || $output_key == "")
        return (NULL);

    $output = dabsic_form_resolve_output($output_key, false, $id_user);
    if (!$output["ok"])
        return (NULL);
    $workspace = dabsic_form_load_workspace($output);
    if (!$workspace["ok"] || !$workspace["exists"] || !is_array($workspace["data"] ?? NULL))
        return (NULL);

    $data = $workspace["data"];
    if ($model_hash != "" && strtolower(trim((string)($data["model_hash"] ?? ""))) !== $model_hash)
        return (NULL);
    if ($target_year !== NULL && (int)($data["target_year"] ?? -1) !== (int)$target_year)
        return (NULL);
    if ($source_reference != "" && trim((string)($data["source_reference"] ?? "")) !== $source_reference)
        return (NULL);
    return ($data);
}

function document_workflow_completed_form_for_output($id_user, $output_key)
{
    $id_user = (int)$id_user;
    $output_key = trim((string)$output_key);
    if ($id_user <= 0 || $output_key == "")
        return (NULL);
    foreach (db_select_all("* FROM user_form
        WHERE id_user = $id_user AND kind LIKE 'DOC-%'
          AND completed_at IS NOT NULL AND revoked_at IS NULL
        ORDER BY completed_at DESC, id DESC") as $form)
    {
        $document = document_workflow_document_form_metadata($form);
        if (trim((string)($document["output"] ?? "")) !== $output_key
            || trim((string)($document["processed_at"] ?? "")) != "")
            continue ;
        return ($form);
    }
    return (NULL);
}

function document_workflow_processed_instance_for_output($id_user, $output_key)
{
    $id_user = (int)$id_user;
    $output_key = trim((string)$output_key);
    if ($id_user <= 0 || $output_key == "")
        return ("");

    // Prefer the explicit source context carried by new workflow instances.
    // This makes finalization idempotent even if the user_form audit rows could
    // not be updated after the PDF was frozen.
    $user = db_select_one("codename FROM user WHERE id = $id_user AND authority != -1");
    if ($user != NULL && trim((string)($user["codename"] ?? "")) != "")
    {
        global $Configuration;
        $pattern = rtrim($Configuration->UsersDir($user["codename"]), "/")."/".
            trim(document_workflow_root(), "/")."/*/instance.dab";
        $files = glob($pattern);
        if (is_array($files))
            foreach ($files as $file)
            {
                $loaded = document_workflow_load_instance(dirname($file));
                if ($loaded->is_error())
                    continue ;
                $instance = $loaded->value["data"];
                if (!is_array($instance) || (string)($instance["Status"] ?? "") === "Expired")
                    continue ;
                $source = $instance["SourceContext"] ?? [];
                if (is_array($source)
                    && (string)($source["Type"] ?? "") === "UserDocument"
                    && trim((string)($source["Output"] ?? "")) === $output_key)
                    return ((string)($instance["Id"] ?? ""));
            }
    }

    // Backward compatibility for instances created before SourceContext.Output
    // was stored: use the processed contribution rows as the durable mapping.
    if (!in_array("user_form", db_get_tables(), true))
        return ("");
    foreach (db_select_all("id, fields FROM user_form
        WHERE id_user = $id_user AND kind LIKE 'DOC-%'
          AND completed_at IS NOT NULL
        ORDER BY id DESC") as $form)
    {
        $document = document_workflow_document_form_metadata($form);
        if (trim((string)($document["output"] ?? "")) !== $output_key)
            continue ;
        $instance_id = trim((string)($document["instance_id"] ?? ""));
        if ($instance_id == "")
            continue ;
        $existing = document_workflow_find_instance($id_user, $instance_id);
        if ($existing->is_error())
            continue ;
        $status = (string)($existing->value["data"]["Status"] ?? "");
        if ($status !== "Expired")
            return ($instance_id);
    }
    return ("");
}

function document_workflow_recent_processed_instance_for_output($id_user, $output_key, $max_age = 300)
{
    $id_user = (int)$id_user;
    $output_key = trim((string)$output_key);
    $max_age = max(1, (int)$max_age);
    if ($id_user <= 0 || $output_key == "")
        return ("");
    foreach (db_select_all("* FROM user_form
        WHERE id_user = $id_user AND kind LIKE 'DOC-%'
          AND completed_at IS NOT NULL
        ORDER BY id DESC") as $form)
    {
        $document = document_workflow_document_form_metadata($form);
        if (trim((string)($document["output"] ?? "")) !== $output_key)
            continue ;
        $processed_at = trim((string)($document["processed_at"] ?? ""));
        $instance_id = trim((string)($document["instance_id"] ?? ""));
        if ($processed_at == "" || $instance_id == "")
            continue ;
        $time = strtotime($processed_at);
        if ($time !== false && abs(time() - $time) <= $max_age)
            return ($instance_id);
    }
    return ("");
}

function document_workflow_required_model_roles($model_file)
{
    $metadata = dabsic_form_form_metadata($model_file);
    $out = [];
    foreach (($metadata["roles"] ?? []) as $role => $definition)
        if (is_array($definition) && dabsic_form_role_has_contribution($definition)
            && (!empty($definition["required"]) || !empty($definition["required_if_recipient"])))
            $out[(string)$role] = trim((string)($definition["label"] ?? "")) != ""
                ? trim((string)$definition["label"]) : (string)$role;
    return ($out);
}

function document_workflow_missing_required_form_fields($id_user, $output_key)
{
    $id_user = (int)$id_user;
    $output_key = trim((string)$output_key);
    if ($id_user <= 0 || $output_key == "")
        return ([]);
    foreach (db_select_all("* FROM user_form
        WHERE id_user = $id_user AND kind LIKE 'DOC-%'
          AND completed_at IS NOT NULL AND revoked_at IS NULL
        ORDER BY completed_at DESC, id DESC") as $form)
    {
        $document = document_workflow_document_form_metadata($form);
        if (trim((string)($document["output"] ?? "")) !== $output_key
            || trim((string)($document["processed_at"] ?? "")) != "")
            continue ;
        return (dabsic_form_missing_required_fields(
            document_workflow_document_form_model_metadata($form),
            document_workflow_document_form_current_values($form)
        ));
    }
    return ([]);
}

function document_workflow_ensure_form_tasks(array $form)
{
    if (!document_task_table_available())
        return ([]);
    $id_form = (int)($form["id"] ?? 0);
    $id_user = (int)($form["id_user"] ?? 0);
    if ($id_form <= 0 || $id_user <= 0)
        return ([]);

    $schema = document_workflow_document_form_schema($form);
    $document = isset($schema["document"]) && is_array($schema["document"])
        ? $schema["document"] : [];
    $recipient_role = trim((string)($document["form_role"] ?? ""));
    $roles = document_workflow_form_role_definitions($form);
    $definition = $roles[$recipient_role] ?? NULL;
    // One invitation represents one autonomous contributor. Other roles get
    // their own invitation and task; creating them here leaves ghost tasks
    // attached to the wrong token after a resend or reopen.
    $recipient_user_id = (int)($document["recipient_user_id"] ?? $id_user);
    if (is_array($definition)
        && document_workflow_form_role_is_required($id_user, $definition, (int)($form["id_creator"] ?? 0))
        && document_task_role_is_valid($recipient_role))
    {
        $assignee = $recipient_user_id;
        $key = document_task_key("form", $id_form, "fill", $recipient_role, $assignee);
        $created = document_task_create(
            $key,
            $id_user,
            $id_form,
            "",
            "fill",
            $recipient_role,
            (string)($definition["label"] ?? $recipient_role),
            $assignee,
            true,
            ["document_label" => (string)($document["label"] ?? "")]
        );
        if ($created->is_error())
            add_log(REPORT, "Cannot create document fill task $recipient_role for form $id_form: ".strval($created), $id_user);
    }

    // Old completed invitations predate document_task.  Their recipient role
    // has nevertheless been explicitly validated through the public form.
    if (($form["completed_at"] ?? NULL) !== NULL && $recipient_role != "")
        document_task_complete_form_role($id_form, $recipient_role, $recipient_user_id, $recipient_user_id);
    return (document_task_rows_for_form($id_form));
}

function document_workflow_form_task_progress(array $form)
{
    return (document_task_progress(document_workflow_ensure_form_tasks($form), "fill"));
}

function document_workflow_pending_required_form_tasks($id_user, $output_key)
{
    $id_user = (int)$id_user;
    $output_key = trim((string)$output_key);
    if ($id_user <= 0 || $output_key == "")
        return ([]);
    $forms = document_workflow_active_forms_for_output($id_user, $output_key);
    if (!count($forms))
        return ([]);
    $sample = $forms[0];
    $created_by = (int)($sample["id_creator"] ?? 0);
    $semantic_bindings = [];
    $output = dabsic_form_resolve_output($output_key, false, $id_user);
    if ($output["ok"])
    {
        $workspace = dabsic_form_load_workspace($output);
        if ($workspace["ok"] && $workspace["exists"])
            $semantic_bindings = document_workflow_workspace_semantic_bindings($workspace["data"]);
    }
    $by_role = [];
    foreach ($forms as $form)
    {
        $document = document_workflow_document_form_metadata($form);
        $role = trim((string)($document["form_role"] ?? ""));
        if ($role == "" || isset($by_role[$role]))
            continue ;
        $by_role[$role] = $form;
    }
    $pending = [];
    foreach (document_workflow_form_role_definitions($sample) as $role => $definition)
    {
        if (!document_workflow_form_role_is_required($id_user, $definition, $created_by, $semantic_bindings))
            continue ;
        $assignee = document_workflow_form_role_assignee($id_user, $definition, $created_by, $semantic_bindings);
        $label = trim((string)($definition["label"] ?? $role));
        if (!isset($by_role[$role]))
        {
            $pending[] = [
                "id" => 0,
                "id_form" => NULL,
                "task_action" => "fill",
                "role" => (string)$role,
                "role_label" => $label,
                "id_assignee_user" => $assignee > 0 ? $assignee : NULL,
                "required" => 1,
                "status" => "pending",
                "metadata" => "{}",
            ];
            continue ;
        }
        $found = NULL;
        foreach (document_workflow_ensure_form_tasks($by_role[$role]) as $task)
            if (($task["task_action"] ?? "") === "fill" && ($task["role"] ?? "") === $role)
            {
                $found = $task;
                break ;
            }
        if ($found == NULL || ($found["status"] ?? "") !== "completed")
            $pending[] = $found ?? [
                "id" => 0,
                "id_form" => (int)$by_role[$role]["id"],
                "task_action" => "fill",
                "role" => (string)$role,
                "role_label" => $label,
                "id_assignee_user" => $assignee > 0 ? $assignee : NULL,
                "required" => 1,
                "status" => "pending",
                "metadata" => "{}",
            ];
    }
    return ($pending);
}

function document_workflow_complete_form_role_as_staff($id_form, $role)
{
    $id_form = (int)$id_form;
    $role = trim((string)$role);
    if (!document_workflow_can_monitor() || $id_form <= 0 || !document_task_role_is_valid($role))
        return (new ErrorResponse("PermissionDenied"));
    $form = db_select_one("* FROM user_form WHERE id = $id_form AND kind LIKE 'DOC-%'");
    if ($form == NULL)
        return (new ErrorResponse("InvalidParameter", "form_id"));
    if (!document_workflow_can_view_document_form($form))
        return (new ErrorResponse("PermissionDenied"));
    if (($form["completed_at"] ?? NULL) === NULL || ($form["revoked_at"] ?? NULL) !== NULL)
        return (new ErrorResponse("InvalidParameter", "document form state"));

    $tasks = document_workflow_ensure_form_tasks($form);
    $task = NULL;
    foreach ($tasks as $candidate)
        if (($candidate["task_action"] ?? "") === "fill" && ($candidate["role"] ?? "") === $role)
        {
            $task = $candidate;
            break ;
        }
    if ($task == NULL || empty($task["required"]))
        return (new ErrorResponse("InvalidParameter", "document role"));

    $document = document_workflow_document_form_metadata($form);
    if ($role === trim((string)($document["form_role"] ?? ""))
        && ($task["status"] ?? "") !== "completed")
        return (new ErrorResponse("PermissionDenied", "recipient role"));

    $missing = dabsic_form_missing_required_fields(
        document_workflow_document_form_model_metadata($form),
        document_workflow_document_form_current_values($form),
        $role
    );
    if (count($missing))
        return (new ErrorResponse("DocumentRequiredFields", implode("\n", array_values($missing))));

    return (document_task_complete_form_role(
        $id_form,
        $role,
        document_workflow_actor_id(),
        document_workflow_actor_id()
    ));
}

function document_workflow_create_signature_tasks($id_user, $instance_id, array $schema, array $bindings, $created_by = 0)
{
    if (!document_task_table_available())
        return (true);
    $id_user = (int)$id_user;
    foreach ($schema as $role => $definition)
    {
        if (empty($definition["required"]) || !document_task_role_is_valid($role))
            continue ;
        $source = isset($bindings[$role]) ? (string)$bindings[$role] : "";
        $assignee = document_workflow_source_user_id($id_user, $source, $created_by);
        $key = document_task_key("instance", $instance_id, "sign", $role, (int)$assignee);
        $created = document_task_create(
            $key,
            $id_user,
            0,
            $instance_id,
            "sign",
            $role,
            (string)($definition["role"] ?? $role),
            (int)$assignee,
            true,
            ["source" => $source]
        );
        if ($created->is_error())
            return (false);
    }
    return (true);
}

function document_workflow_document_form_schema(array $form)
{
    if (isset($form["schema"]) && is_array($form["schema"]))
        return ($form["schema"]);
    $schema = json_decode((string)($form["fields"] ?? ""), true);
    return (is_array($schema) ? $schema : []);
}

function document_workflow_document_form_metadata(array $form)
{
    $schema = document_workflow_document_form_schema($form);
    return (isset($schema["document"]) && is_array($schema["document"])
        ? $schema["document"] : []);
}

function document_workflow_output_for_instance($owner_user_id, $instance_id)
{
    $owner_user_id = (int)$owner_user_id;
    $instance_id = trim((string)$instance_id);
    if ($owner_user_id <= 0 || $instance_id === "" || !in_array("user_form", db_get_tables(), true))
        return ("");

    foreach (db_select_all("id, fields FROM user_form
        WHERE id_user = $owner_user_id AND kind LIKE 'DOC-%'
        ORDER BY id DESC") as $row)
    {
        $document = document_workflow_document_form_metadata($row);
        if (trim((string)($document["instance_id"] ?? "")) !== $instance_id)
            continue ;
        return (trim((string)($document["output"] ?? "")));
    }
    return ("");
}

function document_workflow_form_for_instance($owner_user_id, $instance_id)
{
    $owner_user_id = (int)$owner_user_id;
    $instance_id = trim((string)$instance_id);
    if ($owner_user_id <= 0 || $instance_id === "" || !in_array("user_form", db_get_tables(), true))
        return (NULL);

    foreach (db_select_all("* FROM user_form
        WHERE id_user = $owner_user_id AND kind LIKE 'DOC-%'
        ORDER BY id DESC") as $row)
    {
        $document = document_workflow_document_form_metadata($row);
        if (trim((string)($document["instance_id"] ?? "")) === $instance_id)
            return ($row);
    }
    return (NULL);
}

function document_workflow_form_ids_for_instance($owner_user_id, $instance_id)
{
    $owner_user_id = (int)$owner_user_id;
    $instance_id = trim((string)$instance_id);
    if ($owner_user_id <= 0 || $instance_id === "" || !in_array("user_form", db_get_tables(), true))
        return ([]);
    $ids = [];
    foreach (db_select_all("id, fields FROM user_form
        WHERE id_user = $owner_user_id AND kind LIKE 'DOC-%'
        ORDER BY id DESC") as $row)
    {
        $document = document_workflow_document_form_metadata($row);
        if (trim((string)($document["instance_id"] ?? "")) === $instance_id)
            $ids[] = (int)$row["id"];
    }
    return ($ids);
}

function document_workflow_enrich_document_form(array $row)
{
    $document = document_workflow_document_form_metadata($row);
    $row["document_label"] = trim((string)($document["label"] ?? ""));
    $row["document_reference"] = trim((string)($document["reference"] ?? ""));
    $row["document_source_reference"] = trim((string)($document["source_reference"] ?? ""));
    if ($row["document_source_reference"] == "" && $row["document_reference"] != ""
        && function_exists("document_reference_from_editor_path"))
        $row["document_source_reference"] = (string)(document_reference_from_editor_path($row["document_reference"]) ?? "");
    $row["document_target_year"] = (int)($document["target_year"] ?? 0);
    $row["document_output"] = trim((string)($document["output"] ?? ""));
    $row["document_mailbox"] = trim((string)($document["mailbox"] ?? ""));
    $row["document_processed_at"] = trim((string)($document["processed_at"] ?? ""));
    $row["document_instance_id"] = trim((string)($document["instance_id"] ?? ""));
    $row["document_expired_at"] = trim((string)($document["expired_at"] ?? ""));
    $row["document_chain"] = $document["chain"] ?? "[]";
    $row["document_context_bindings"] = $document["context_bindings"] ?? [];
    $row["document_signature_bindings"] = $document["signature_bindings"] ?? [];
    $row["school_codenames"] = document_workflow_document_form_school_codenames($row);
    $row["document_tasks"] = document_workflow_ensure_form_tasks($row);
    $row["document_task_progress"] = document_task_progress($row["document_tasks"], "fill");
    return ($row);
}

function document_workflow_document_form_school_codenames(array $form)
{
    $schema = document_workflow_document_form_schema($form);
    if (is_array($schema))
    {
        $chain = $schema["document"]["chain"] ?? [];
        if (is_string($chain))
            $chain = json_decode($chain, true);
        if (is_array($chain))
        {
            $schools = [];
            foreach ($chain as $entry)
            {
                if (!is_array($entry) || ($entry["type"] ?? "") !== "school")
                    continue ;
                $codename = trim((string)($entry["id"] ?? ""));
                if ($codename != "")
                    $schools[$codename] = true;
            }
            if (count($schools))
                return (array_keys($schools));
        }
    }

    $id_user = (int)($form["id_user"] ?? 0);
    if ($id_user <= 0)
        return ([]);
    $out = [];
    foreach (db_select_all("school.codename AS codename FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id_user
          AND school.id IS NOT NULL AND school.deleted IS NULL") as $row)
    {
        $codename = trim((string)($row["codename"] ?? ""));
        if ($codename != "")
            $out[$codename] = true;
    }
    return (array_keys($out));
}

function document_workflow_can_view_document_form(array $form)
{
    $schools = document_workflow_monitor_school_codenames();
    if ($schools === NULL)
        return (true);
    if (!count($schools))
        return (false);
    return (count(array_intersect($schools, document_workflow_document_form_school_codenames($form))) > 0);
}

function document_workflow_visible_pending_document_forms($limit = 1000)
{
    if (!document_workflow_can_monitor() || !in_array("user_form", db_get_tables(), true))
        return ([]);
    $limit = max(1, min(5000, (int)$limit));
    $rows = db_select_all("user_form.*, user.codename, user.first_name, user.family_name
        FROM user_form
        LEFT JOIN user ON user.id = user_form.id_user
        WHERE user_form.kind LIKE 'DOC-%'
          AND user_form.completed_at IS NULL
          AND user_form.revoked_at IS NULL
          AND user_form.expires_at >= NOW()
        ORDER BY user_form.created_at DESC, user_form.id DESC
        LIMIT $limit");
    $out = [];
    foreach ($rows as $row)
    {
        if (!document_workflow_can_view_document_form($row))
            continue ;
        $out[] = document_workflow_enrich_document_form($row);
    }
    return ($out);
}


function document_workflow_visible_completed_document_forms($limit = 1000)
{
    if (!document_workflow_can_monitor() || !in_array("user_form", db_get_tables(), true))
        return ([]);
    $limit = max(1, min(5000, (int)$limit));
    $rows = db_select_all("user_form.*, user.codename, user.first_name, user.family_name
        FROM user_form
        LEFT JOIN user ON user.id = user_form.id_user
        WHERE user_form.kind LIKE 'DOC-%'
          AND user_form.completed_at IS NOT NULL
          AND user_form.revoked_at IS NULL
        ORDER BY user_form.completed_at DESC, user_form.id DESC
        LIMIT $limit");
    $out = [];
    foreach ($rows as $row)
    {
        if (!document_workflow_can_view_document_form($row))
            continue ;
        $row = document_workflow_enrich_document_form($row);
        if ($row["document_processed_at"] != "" || $row["document_instance_id"] != "")
            continue ;
        $out[] = $row;
    }
    return ($out);
}

function document_workflow_visible_expired_document_forms($limit = 1000)
{
    if (!document_workflow_can_monitor() || !in_array("user_form", db_get_tables(), true))
        return ([]);
    $limit = max(1, min(5000, (int)$limit));
    $rows = db_select_all("user_form.*, user.codename, user.first_name, user.family_name
        FROM user_form
        LEFT JOIN user ON user.id = user_form.id_user
        WHERE user_form.kind LIKE 'DOC-%'
          AND ((user_form.completed_at IS NULL AND user_form.revoked_at IS NULL AND user_form.expires_at < NOW())
               OR (user_form.completed_at IS NOT NULL AND user_form.revoked_at IS NOT NULL))
        ORDER BY COALESCE(user_form.revoked_at, user_form.expires_at) DESC, user_form.id DESC
        LIMIT $limit");
    $out = [];
    foreach ($rows as $row)
    {
        if (!document_workflow_can_view_document_form($row))
            continue ;
        $out[] = document_workflow_enrich_document_form($row);
    }
    return ($out);
}

function document_workflow_mark_document_form_processed($id_user, $output_key, $instance_id)
{
    global $Database;

    if (!in_array("user_form", db_get_tables(), true))
        return (true);
    $id_user = (int)$id_user;
    $output_key = trim((string)$output_key);
    $instance_id = trim((string)$instance_id);
    if ($id_user <= 0 || $output_key == "" || $instance_id == "")
        return (false);

    $matched = false;
    foreach (db_select_all("id, fields FROM user_form
        WHERE id_user = $id_user AND kind LIKE 'DOC-%'
          AND completed_at IS NOT NULL AND revoked_at IS NULL
        ORDER BY completed_at DESC, id DESC") as $row)
    {
        $schema = json_decode((string)($row["fields"] ?? ""), true);
        if (!is_array($schema) || !isset($schema["document"]) || !is_array($schema["document"]))
            continue ;
        if (trim((string)($schema["document"]["output"] ?? "")) !== $output_key)
            continue ;
        if (trim((string)($schema["document"]["processed_at"] ?? "")) != "")
            continue ;
        $schema["document"]["processed_at"] = date("Y-m-d H:i:s");
        $schema["document"]["processed_by"] = document_workflow_actor_id();
        $schema["document"]["instance_id"] = $instance_id;
        $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false)
            return (false);
        $id = (int)$row["id"];
        $escaped = $Database->real_escape_string($json);
        if (!$Database->query("UPDATE user_form SET fields = '$escaped' WHERE id = $id"))
            return (false);
        $matched = true;
    }
    return (true);
}

function document_workflow_document_form_school_id(array $form)
{
    global $Database;

    foreach (document_workflow_document_form_school_codenames($form) as $codename)
    {
        $codename = $Database->real_escape_string((string)$codename);
        $school = db_select_one("id FROM school WHERE codename = '$codename' AND deleted IS NULL");
        if ($school != NULL)
            return ((int)$school["id"]);
    }
    $id_user = (int)($form["id_user"] ?? 0);
    if ($id_user <= 0)
        return (0);
    $school = db_select_one("school.id AS id FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id_user AND school.deleted IS NULL
        ORDER BY user_school.id ASC");
    return ($school == NULL ? 0 : (int)$school["id"]);
}

function document_workflow_profile_url($id_school, $id_user)
{
    $id_school = (int)$id_school;
    $id_user = (int)$id_user;
    $school = db_select_one("base_url FROM school WHERE id = $id_school AND deleted IS NULL");
    $base = trim((string)($school["base_url"] ?? ""));
    if ($base != "" && function_exists("school_base_url_normalize"))
    {
        $normalized = school_base_url_normalize($base);
        $base = ($normalized === false ? "" : $normalized);
    }
    if ($base == "")
    {
        $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
            || (isset($_SERVER["HTTP_X_FORWARDED_PROTO"])
                && strtolower((string)$_SERVER["HTTP_X_FORWARDED_PROTO"]) === "https");
        $host = trim((string)($_SERVER["HTTP_HOST"] ?? ""));
        if ($host != "")
            $base = ($https ? "https" : "http")."://".$host;
    }
    return ($base == "" ? "" : rtrim($base, "/")."/index.php?p=ProfileMenu&a=".$id_user);
}

function document_workflow_notify_document_form_recipient_completed(array $form)
{
    global $Dictionnary;

    $id_user = (int)($form["id_user"] ?? 0);
    if ($id_user <= 0)
        return (new ValueResponse(["sent" => false]));
    $mail = trim((string)($form["recipient_mail"] ?? ""));
    $recipient_name = trim((string)($form["recipient_name"] ?? ""));
    if ($mail == "")
    {
        $user = db_select_one("first_name, family_name, mail FROM user WHERE id = $id_user AND authority != -1");
        if ($user != NULL)
        {
            $mail = trim((string)($user["mail"] ?? ""));
            if ($recipient_name == "")
                $recipient_name = trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? ""));
        }
    }
    if ($mail == "")
        return (new ValueResponse(["sent" => false]));

    $document = document_workflow_document_form_metadata($form);
    $label = trim((string)($document["label"] ?? ""));
    if ($label == "")
        $label = document_reference_label((string)($document["reference"] ?? "Document"));
    $name = trim((string)preg_replace('/\s+.*/u', '', $recipient_name));
    $subject = sprintf(
        $Dictionnary["DocumentRecipientCompletedMailTitle"] ?? "Formulaire reçu : %s",
        $label
    );
    $content = sprintf(
        $Dictionnary["DocumentRecipientCompletedMailContent"] ?? "Bonjour%s,\n\nVotre formulaire « %s » a bien été enregistré comme complété. L’établissement a été averti et peut désormais poursuivre le traitement du document.\n\nVous n’avez aucune action supplémentaire à effectuer pour cette étape.",
        $name != "" ? " ".$name : "",
        $label
    );
    try
    {
        $sent = send_mail($mail, $subject, $content);
    }
    catch (Throwable $e)
    {
        return (new ErrorResponse("CannotSendMail", $e->getMessage()));
    }
    if ($sent->is_error())
        return ($sent);
    add_log(CREATIVE_OPERATION, "document completion acknowledgement sent to user $id_user", $id_user);
    return (new ValueResponse(["sent" => true, "mail" => $mail]));
}

function document_workflow_notify_document_form_completed(array $form)
{
    global $Dictionnary;

    $id_school = document_workflow_document_form_school_id($form);
    if ($id_school <= 0 || !function_exists("school_mailbox_resolve"))
        return (new ValueResponse(["sent" => false]));

    $document = document_workflow_document_form_metadata($form);
    $purpose = strtolower(trim((string)($document["mailbox"] ?? "")));
    if ($purpose == "")
    {
        $reference = trim((string)($document["reference"] ?? ""));
        $candidate = $reference != "" ? realpath(__DIR__."/../".$reference) : false;
        if ($candidate !== false && function_exists("document_model_workflow_mailbox"))
            $purpose = document_model_workflow_mailbox($candidate);
    }
    $mail = school_mailbox_resolve($id_school, $purpose, true);
    if ($mail == "")
        return (new ValueResponse(["sent" => false]));

    $id_user = (int)($form["id_user"] ?? 0);
    $name = trim((string)($form["recipient_name"] ?? ""));
    if ($name == "" && $id_user > 0)
    {
        $user = db_select_one("first_name, family_name, codename FROM user WHERE id = $id_user AND authority != -1");
        $name = trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? ""));
        if ($name == "")
            $name = (string)($user["codename"] ?? ("#".$id_user));
    }
    $label = trim((string)($document["label"] ?? ""));
    if ($label == "")
        $label = document_reference_label((string)($document["reference"] ?? "Document"));
    $profile_url = document_workflow_profile_url($id_school, $id_user);

    $subject = sprintf(
        $Dictionnary["DocumentReadyForFinalizationMailTitle"] ?? "Action requise : %s complété",
        $label
    );
    $content = sprintf(
        $Dictionnary["DocumentReadyForFinalizationMailContent"] ?? "Le formulaire « %s » de %s a été complété.\n\nInfosphere indique maintenant les parties obligatoires restant à valider avant finalisation.%s",
        $label,
        $name != "" ? $name : ("#".$id_user),
        $profile_url != "" ? "\n\nProfil : ".$profile_url : ""
    );
    $progress = ["pending" => document_workflow_pending_required_form_tasks(
        $id_user,
        (string)($document["output"] ?? "")
    )];
    $pending_labels = [];
    foreach (($progress["pending"] ?? []) as $task)
    {
        $task_label = trim((string)($task["role_label"] ?? ""));
        if ($task_label == "")
            $task_label = trim((string)($task["role"] ?? ""));
        if ($task_label != "")
            $pending_labels[] = $task_label;
    }
    if (count($pending_labels))
        $content .= "\n\n".($Dictionnary["DocumentPendingRolesMailLine"] ?? "Reste à valider : ").implode(", ", $pending_labels);
    try
    {
        $sent = send_mail($mail, $subject, $content);
    }
    catch (Throwable $e)
    {
        return (new ErrorResponse("CannotSendMail", $e->getMessage()));
    }
    if ($sent->is_error())
        return ($sent);
    add_log(CREATIVE_OPERATION, "document completion notification $purpose to $mail", $id_user);
    return (new ValueResponse(["sent" => true, "mail" => $mail, "purpose" => $purpose]));
}

function document_workflow_expire_document_form($id_form)
{
    global $Database;

    if (!document_workflow_can_monitor() || !in_array("user_form", db_get_tables(), true))
        return (new ErrorResponse("PermissionDenied"));
    $id_form = (int)$id_form;
    if ($id_form <= 0)
        return (new ErrorResponse("InvalidParameter", "form_id"));

    $form = db_select_one("user_form.* FROM user_form
        WHERE user_form.id = $id_form AND user_form.kind LIKE 'DOC-%'");
    if ($form == NULL)
        return (new ErrorResponse("InvalidParameter", "form_id"));
    if (!document_workflow_can_view_document_form($form))
        return (new ErrorResponse("PermissionDenied"));

    $schema = document_workflow_document_form_schema($form);
    $document = isset($schema["document"]) && is_array($schema["document"])
        ? $schema["document"] : [];
    $output_key = trim((string)($document["output"] ?? ""));

    if ($form["completed_at"] !== NULL &&
        (trim((string)($document["processed_at"] ?? "")) != "" ||
         trim((string)($document["instance_id"] ?? "")) != ""))
        return (new ErrorResponse("InvalidParameter", "finalized document form"));

    // Idempotent catch-up for forms expired before internship lifecycle support
    // existed: a second expiration request still deactivates generated sessions.
    if ($form["completed_at"] !== NULL && $form["revoked_at"] !== NULL)
    {
        $sync = internship_session_sync_deactivate_output($output_key, "document form expired");
        if (!$sync["ok"])
            return (new ErrorResponse($sync["error"], $sync["details"] ?? ""));
        return (new ValueResponse(["expired" => true]));
    }

    if ($Database->query("START TRANSACTION") === NULL)
        return (new ErrorResponse("CannotEdit", "document expiration transaction"));

    $ok = true;
    if ($form["completed_at"] !== NULL)
    {
        if (isset($schema["document"]) && is_array($schema["document"]))
        {
            $schema["document"]["expired_at"] = date("Y-m-d H:i:s");
            $schema["document"]["expired_by"] = document_workflow_actor_id();
            $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false)
                $ok = false;
            else
            {
                $fields = $Database->real_escape_string($json);
                $ok = $Database->query("UPDATE user_form
                    SET fields = '$fields', revoked_at = NOW(), expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)
                    WHERE id = $id_form AND revoked_at IS NULL") !== NULL;
            }
        }
        else
            $ok = $Database->query("UPDATE user_form
                SET revoked_at = NOW(), expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)
                WHERE id = $id_form AND revoked_at IS NULL") !== NULL;
    }
    else
        $ok = $Database->query("UPDATE user_form
            SET revoked_at = NOW(), expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)
            WHERE id = $id_form AND completed_at IS NULL AND revoked_at IS NULL") !== false;

    if ($ok)
    {
        $sync = internship_session_sync_deactivate_output($output_key, "document form expired");
        $ok = $sync["ok"];
    }
    if (!$ok)
    {
        $Database->query("ROLLBACK");
        return (new ErrorResponse("CannotEdit", "document expiration / internship sessions"));
    }
    if ($Database->query("COMMIT") === NULL)
    {
        $Database->query("ROLLBACK");
        return (new ErrorResponse("CannotEdit", "document expiration commit"));
    }

    document_task_expire_form($id_form);
    return (new ValueResponse(["expired" => true]));
}

function document_workflow_send_signature_request($owner_user_id, $instance_id, $slot, $initial_only = false)
{
    $owner_user_id = (int)$owner_user_id;
    $instance_id = trim((string)$instance_id);
    $slot = trim((string)$slot);
    if ($owner_user_id <= 0 || $instance_id == "" || !document_signature_slot_is_valid($slot))
        return (new ErrorResponse("InvalidParameter", "signature request"));

    $loaded = document_workflow_find_instance($owner_user_id, $instance_id);
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (!document_workflow_can_view_instance($instance))
        return (new ErrorResponse("PermissionDenied"));
    if (($instance["Status"] ?? "") !== "AwaitingSignature")
        return (new ErrorResponse("InvalidParameter", "instance status"));
    if (!isset($instance["Signatures"][$slot]) || !is_array($instance["Signatures"][$slot]))
        return (new ErrorResponse("InvalidParameter", "signature slot"));
    $signature = $instance["Signatures"][$slot];
    if (document_workflow_signature_is_external_campaign($signature))
        return (new ErrorResponse("DocumentSignatureManagedByCampaign"));
    if (($signature["Status"] ?? "Pending") === "Signed")
        return (new ErrorResponse("RegistrationFormCompleted"));
    // Creation-time dispatch must not become an accidental reminder if Albedo
    // happened to send the invitation between freezing the instance and this
    // request. Manual reminders keep the historical behaviour.
    if ($initial_only && !empty($signature["InvitedAt"]))
        return (new ValueResponse(["sent" => false, "already_invited" => true, "slot" => $slot]));

    $signatory = document_workflow_signature_assignee_user_id(
        $owner_user_id,
        $signature,
        (int)($instance["CreatedBy"] ?? 0)
    );
    if ($signatory <= 0)
        return (new ErrorResponse("InvalidParameter", "signatory"));

    require_once (__DIR__."/registration_form.php");
    $invitation = registration_form_create_document_signature_invitation(
        $owner_user_id,
        $instance_id,
        $slot,
        $signatory,
        max(1, document_workflow_actor_id())
    );
    if (!$invitation["ok"])
        return (new ErrorResponse($invitation["error"] ?? "CannotEdit", $invitation["details"] ?? ""));

    $user = $invitation["user"] ?? [];
    $mail = trim((string)($user["mail"] ?? ""));
    if ($mail == "")
    {
        registration_form_revoke_token($invitation["token"]);
        return (new ErrorResponse("MissingField", "mail"));
    }
    $role_label = document_workflow_signature_role_label($slot, $signature);
    $was_invited = !empty($signature["InvitedAt"]);
    $subject = ($was_invited ? "Rappel - " : "")."Document à signer : ".document_workflow_instance_label($instance);
    $content = "Bonjour".(trim((string)($user["first_name"] ?? "")) != "" ? " ".$user["first_name"] : "").",\n\n".
        ($was_invited ? "Rappel : un document attend toujours votre signature" : "Un document attend votre signature").
        " en qualité de ".$role_label.".\n".
        "Le lien ci-dessous permet de consulter exactement le PDF concerné puis de le signer :\n\n".
        $invitation["url"]."\n\n".
        "Ce lien est personnel et valable 14 jours.\n";
    $attempt_timestamp = time();
    $signature["LastInvitationAttemptTimestamp"] = $attempt_timestamp;
    $signature["InvitationAttemptCount"] = (int)($signature["InvitationAttemptCount"] ?? 0) + 1;
    try
    {
        $sent = send_mail($mail, $subject, $content);
    }
    catch (Throwable $e)
    {
        $sent = new ErrorResponse("CannotSendMail", $e->getMessage());
    }
    if ($sent->is_error())
    {
        registration_form_revoke_token($invitation["token"]);
        $signature["Error"] = "CannotSendMail";
        $instance["Signatures"][$slot] = $signature;
        $written = document_workflow_write_instance($loaded->value["file"], $instance);
        if ($written->is_error())
            return ($written);
        return ($sent);
    }

    $now = date("Y-m-d H:i:s");
    if (!$was_invited)
        $signature["InvitedAt"] = $now;
    else
        $signature["ReminderCount"] = (int)($signature["ReminderCount"] ?? 0) + 1;
    $signature["LastInvitationAt"] = $now;
    $signature["InvitationId"] = (int)$invitation["id"];
    $signature["SignatoryUserId"] = $signatory;
    unset($signature["Error"]);
    $instance["Signatures"][$slot] = $signature;
    $written = document_workflow_write_instance($loaded->value["file"], $instance);
    if ($written->is_error())
        return ($written);
    add_log(EDITING_OPERATION, document_workflow_signature_mail_log_message(
        $instance, $slot, $signature, $user, $signatory, $was_invited, "manual"
    ), max(1, document_workflow_actor_id()), false);
    return (new ValueResponse(["sent" => true, "slot" => $slot, "signatory_user_id" => $signatory]));
}

function document_workflow_send_initial_signature_requests($owner_user_id, $instance_id)
{
    $loaded = document_workflow_find_instance((int)$owner_user_id, $instance_id);
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") !== "AwaitingSignature")
        return (new ValueResponse(["sent" => 0]));

    $sent = 0;
    foreach (($instance["Signatures"] ?? []) as $slot => $signature)
    {
        if (!is_array($signature) || empty($signature["Required"])
            || ($signature["Status"] ?? "Pending") === "Signed"
            || document_workflow_signature_is_external_campaign($signature))
            continue ;
        $ret = document_workflow_send_signature_request(
            (int)$owner_user_id, $instance_id, $slot, true
        );
        if ($ret->is_error())
            return ($ret);
        if (!empty($ret->value["sent"]))
            ++$sent;
    }
    return (new ValueResponse(["sent" => $sent]));
}


function document_workflow_remind_pending_signatures($owner_user_id, $instance_id, $slot = "")
{
    $loaded = document_workflow_find_instance((int)$owner_user_id, $instance_id);
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (!document_workflow_can_view_instance($instance))
        return (new ErrorResponse("PermissionDenied"));
    $slots = [];
    $slot = trim((string)$slot);
    if ($slot != "")
    {
        if (!isset($instance["Signatures"][$slot]) || !is_array($instance["Signatures"][$slot]))
            return (new ErrorResponse("InvalidParameter", "signature slot"));
        if (document_workflow_signature_is_external_campaign($instance["Signatures"][$slot]))
            return (new ErrorResponse("DocumentSignatureManagedByCampaign"));
        $slots[] = $slot;
    }
    else
        foreach (($instance["Signatures"] ?? []) as $candidate => $signature)
            if (is_array($signature) && !empty($signature["Required"])
                && ($signature["Status"] ?? "Pending") !== "Signed"
                && !document_workflow_signature_is_external_campaign($signature))
                $slots[] = $candidate;
    if (!count($slots))
        return (new ValueResponse(["sent" => 0]));

    $sent = 0;
    foreach ($slots as $candidate)
    {
        $ret = document_workflow_send_signature_request((int)$owner_user_id, $instance_id, $candidate);
        if ($ret->is_error())
            return ($ret);
        ++$sent;
    }
    return (new ValueResponse(["sent" => $sent]));
}

function document_workflow_expire_signature_forms($owner_user_id, $instance_id)
{
    global $Database;

    if (!in_array("user_form", db_get_tables(), true))
        return (true);
    $owner_user_id = (int)$owner_user_id;
    $instance_id = (string)$instance_id;
    $ids = [];
    foreach (db_select_all("id, fields FROM user_form
        WHERE kind LIKE 'SIG-%' AND completed_at IS NULL AND revoked_at IS NULL") as $row)
    {
        $schema = json_decode((string)($row["fields"] ?? ""), true);
        $meta = is_array($schema) && isset($schema["document_signature"])
            && is_array($schema["document_signature"])
            ? $schema["document_signature"] : [];
        if ((int)($meta["owner_user_id"] ?? 0) != $owner_user_id
            || (string)($meta["instance_id"] ?? "") !== $instance_id)
            continue ;
        $ids[] = (int)$row["id"];
    }
    if (!count($ids))
        return (true);
    $ids = implode(",", array_map("intval", $ids));
    return ((bool)$Database->query("UPDATE user_form
        SET revoked_at = NOW(), expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)
        WHERE id IN ($ids) AND completed_at IS NULL AND revoked_at IS NULL"));
}

function document_workflow_expire_instance($owner_user_id, $instance_id)
{
    $owner_user_id = (int)$owner_user_id;
    $instance_id = trim((string)$instance_id);
    if (!document_workflow_can_monitor())
        return (new ErrorResponse("PermissionDenied"));
    if ($owner_user_id <= 0 || $instance_id == "")
        return (new ErrorResponse("InvalidParameter", "document instance"));

    $loaded = document_workflow_find_instance($owner_user_id, $instance_id);
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (!document_workflow_can_view_instance($instance))
        return (new ErrorResponse("PermissionDenied"));
    $output_key = document_workflow_output_for_instance($owner_user_id, $instance_id);
    if (($instance["Status"] ?? "") === "Expired")
    {
        $sync = internship_session_sync_deactivate_output($output_key, "document instance expired");
        if (!$sync["ok"])
            return (new ErrorResponse($sync["error"], $sync["details"] ?? ""));
        return (new ValueResponse(["expired" => true]));
    }

    $previous_instance = $instance;
    $instance["PreviousStatus"] = (string)($instance["Status"] ?? "");
    $instance["Status"] = "Expired";
    $instance["ExpiredAt"] = date("Y-m-d H:i:s");
    $instance["ExpiredBy"] = document_workflow_actor_id();
    $written = document_workflow_write_instance($loaded->value["file"], $instance);
    if ($written->is_error())
        return ($written);

    $sync = internship_session_sync_deactivate_output($output_key, "document instance expired");
    if (!$sync["ok"])
    {
        $restored = document_workflow_write_instance($loaded->value["file"], $previous_instance);
        if ($restored->is_error())
            add_log(REPORT, "Cannot restore document workflow $instance_id after internship session lifecycle failure.", $owner_user_id);
        return (new ErrorResponse($sync["error"], $sync["details"] ?? ""));
    }

    // Signature links must stop working immediately when the parent workflow is
    // expired. The workflow status already prevents a signature from being
    // recorded; revoking the invitations also prevents their stale form pages
    // from remaining usable.
    if (!document_workflow_expire_signature_forms($owner_user_id, $instance_id))
        add_log(REPORT, "Cannot revoke signature forms for expired document workflow $instance_id.");
    if (!document_task_expire_instance($instance_id))
        add_log(REPORT, "Cannot expire document tasks for workflow $instance_id.");

    $source = $instance["SourceContext"] ?? [];
    if (is_array($source) && (string)($source["Type"] ?? "") === "AttendanceRegisterCampaign")
        foreach ((array)($source["Members"] ?? []) as $member)
        {
            if (!is_array($member))
                continue ;
            $child_owner = (int)($member["OwnerUserId"] ?? ($member["StudentId"] ?? 0));
            $child_instance = trim((string)($member["LeafInstanceId"] ?? ""));
            if ($child_owner <= 0 || $child_instance == "")
                continue ;
            $child = document_workflow_expire_instance($child_owner, $child_instance);
            if ($child->is_error())
                add_log(REPORT, "Cannot expire attendance-register child workflow $child_instance with campaign $instance_id: ".strval($child));
        }
    return (new ValueResponse(["expired" => true]));
}

/**
 * Reopen a frozen document as a new contribution cycle.
 *
 * The old instance and its signatures remain available as audit history but
 * become explicitly obsolete. Stored Dabsic values are deliberately kept so
 * staff and contributors edit the previous version instead of starting over.
 */
function document_workflow_reopen_instance($owner_user_id, $instance_id)
{
    $owner_user_id = (int)$owner_user_id;
    $instance_id = trim((string)$instance_id);
    if (!document_workflow_can_monitor())
        return (new ErrorResponse("PermissionDenied"));
    if ($owner_user_id <= 0 || $instance_id == "")
        return (new ErrorResponse("InvalidParameter", "document instance"));

    $loaded_instance = document_workflow_find_instance($owner_user_id, $instance_id);
    if ($loaded_instance->is_error())
        return ($loaded_instance);
    $instance = $loaded_instance->value["data"];
    if (!document_workflow_can_view_instance($instance))
        return (new ErrorResponse("PermissionDenied"));
    if (($instance["Status"] ?? "") === "Expired")
        return (new ErrorResponse("DocumentRequestExpired"));

    $form = document_workflow_form_for_instance($owner_user_id, $instance_id);
    if ($form == NULL)
        return (new ErrorResponse("DocumentWorkspaceNotFound"));
    $document = document_workflow_document_form_metadata($form);
    $reference = trim((string)($document["reference"] ?? ""));
    $output_key = trim((string)($document["output"] ?? ""));
    $model_hash = strtolower(trim((string)($document["model_hash"] ?? "")));
    $target_year = (int)($document["target_year"] ?? 0);
    if ($reference == "" || $output_key == "" || !preg_match('/^[a-f0-9]{32}$/D', $model_hash))
        return (new ErrorResponse("DocumentWorkspaceNotFound"));

    $resolved = dabsic_editor_resolve_file($reference, false);
    if (!$resolved["ok"])
        return (new ErrorResponse($resolved["error"], $resolved["details"] ?? ""));
    $source_reference = (string)(document_reference_from_editor_path($resolved["relative"]) ?? "");
    if ($source_reference == "" || !hash_equals(md5($source_reference), $model_hash))
        return (new ErrorResponse("DabsicFormChanged"));

    $output = dabsic_form_resolve_output($output_key, true, $owner_user_id);
    if (!$output["ok"])
        return (new ErrorResponse($output["error"], $output["details"] ?? ""));
    $workspace = dabsic_form_load_workspace($output);
    if (!$workspace["ok"])
        return (new ErrorResponse($workspace["error"], $workspace["details"] ?? ""));
    if ($workspace["exists"])
        return (new ErrorResponse("DocumentWorkspaceAlreadyActive"));

    $metadata = dabsic_form_form_metadata($resolved["absolute"]);
    $staff_role = dabsic_form_staff_role($metadata);
    $workspace_data = [
        "version" => 1,
        "reference" => $resolved["relative"],
        "source_reference" => $source_reference,
        "model_hash" => $model_hash,
        "target_year" => $target_year,
        "context_bindings" => $document["context_bindings"] ?? [],
        "signature_bindings" => $document["signature_bindings"] ?? [],
        "chain" => (string)($document["chain"] ?? "[]"),
        "staff_role" => $staff_role,
        "created_by" => document_workflow_actor_id(),
        "reopened_from_instance" => $instance_id,
    ];
    $saved = dabsic_form_save_workspace($output, $workspace_data);
    if (!$saved["ok"])
        return (new ErrorResponse($saved["error"], $saved["details"] ?? ""));

    $expired = document_workflow_expire_instance($owner_user_id, $instance_id);
    if ($expired->is_error())
    {
        dabsic_form_delete_workspace($output);
        return ($expired);
    }

    // Completed contribution links must not remain usable after reopening.
    // Their rows and completed tasks stay in the audit trail; revocation only
    // prevents the old cycle from being mistaken for the new one.
    require_once (__DIR__."/registration_form.php");
    $old_form_ids = document_workflow_form_ids_for_instance($owner_user_id, $instance_id);
    if (!registration_form_revoke_rows($old_form_ids))
        add_log(REPORT, "Cannot revoke contributions for reopened document workflow $instance_id.", $owner_user_id);

    add_log(EDITING_OPERATION, "Document workflow $instance_id reopened for user $owner_user_id", $owner_user_id);
    $review_url = "";
    if ($staff_role != "")
    {
        $context_fields = [];
        if ($target_year >= 1 && $target_year <= 5)
            $context_fields = [
                "Student.ChosenClass" => "EF".$target_year,
                "Student.CurrentYear" => (string)$target_year,
            ];
        $review_url = "index.php?p=DabsicFormMenu".
            "&file=".rawurlencode($resolved["relative"]).
            "&output=".rawurlencode($output_key).
            "&mode=docbuilder".
            "&form_role=".rawurlencode($staff_role).
            "&context_bindings=".rawurlencode(document_context_bindings_json($workspace_data["context_bindings"])).
            "&context_fields=".rawurlencode(document_context_fields_json($context_fields));
    }
    return (new ValueResponse([
        "reopened" => true,
        "output" => $output_key,
        "content" => $review_url,
    ]));
}

function document_workflow_signature_progress(array $instance)
{
    $total = 0;
    $signed = 0;
    foreach (($instance["Signatures"] ?? []) as $signature)
    {
        if (!is_array($signature) || empty($signature["Required"]))
            continue ;
        $total += 1;
        if (($signature["Status"] ?? "") === "Signed")
            $signed += 1;
    }
    return (["signed" => $signed, "total" => $total]);
}

function document_workflow_instance_label(array $instance)
{
    $label = trim((string)($instance["DisplayName"] ?? ""));
    if ($label != "")
        return ($label);
    return (document_reference_label((string)($instance["Model"] ?? "Document")));
}

function document_workflow_signature_mail_log_message(array $instance, $slot, array $signature,
    array $user, $signatory, $reminder, $mode)
{
    $clean = function ($value)
    {
        $value = preg_replace('/[\r\n\t]+/', ' ', trim((string)$value));
        return (str_replace('"', "'", $value));
    };
    $event = $reminder ? "DOCUMENT_SIGNATURE_REMINDER_SENT" : "DOCUMENT_SIGNATURE_INITIAL_SENT";
    $document = $clean(document_workflow_instance_label($instance));
    $instance_id = $clean($instance["Id"] ?? "?");
    $slot = $clean($slot);
    $role = $clean(document_workflow_signature_role_label($slot, $signature));
    $semantic_role = $clean(document_workflow_signature_semantic_role($slot, $signature));
    $target_name = $clean(trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? "")));
    $target_mail = $clean($user["mail"] ?? "");
    $mode = $clean($mode);
    $message = $event.
        ' | document="'.$document.'"'.
        ' | instance="'.$instance_id.'"'.
        ' | owner_user='.(int)($instance["OwnerUserId"] ?? 0).
        ' | slot="'.$slot.'"'.
        ' | role="'.$role.'"'.
        ' | semantic_role="'.$semantic_role.'"'.
        ' | invitation_id='.(int)($signature["InvitationId"] ?? 0).
        ' | target_user='.(int)$signatory.
        ' | target_name="'.$target_name.'"'.
        ' | target_mail="'.$target_mail.'"'.
        ' | mode='.$mode;
    if ($reminder)
        $message .= ' | reminder_count='.(int)($signature["ReminderCount"] ?? 0);
    return ($message);
}

/**
 * Return workflow instances visible to the current staff member.
 * Instances remain filesystem-backed; instance.dab is always read through
 * the normal Dabsic/mergeconf configuration loader.
 */
function document_workflow_visible_instances($limit = 1000)
{
    global $Configuration;

    if (!document_workflow_can_monitor())
        return ([]);
    $limit = max(1, min(5000, (int)$limit));
    $pattern = rtrim($Configuration->UsersDir(), "/")."/*/".
        trim(document_workflow_root(), "/")."/*/instance.dab";
    $files = glob($pattern);
    if (!is_array($files))
        return ([]);

    $instances = [];
    $owner_ids = [];
    foreach ($files as $file)
    {
        $loaded = document_workflow_load_instance(dirname($file));
        if ($loaded->is_error())
            continue ;
        $instance = $loaded->value["data"];
        if (!is_array($instance) || !document_workflow_can_view_instance($instance))
            continue ;
        $owner = (int)($instance["OwnerUserId"] ?? 0);
        if ($owner > 0)
            $owner_ids[$owner] = true;
        $progress = document_workflow_signature_progress($instance);
        $materialized_plan = document_workflow_materialize_instance_task_plan($instance);
        if ($materialized_plan->is_error())
            add_log(REPORT, "Cannot refresh dynamic document tasks for workflow ".(string)($instance["Id"] ?? "?").": ".strval($materialized_plan), $owner);
        $tasks = document_task_rows_for_instance((string)($instance["Id"] ?? ""), $owner);
        $instances[] = [
            "instance" => $instance,
            "directory" => $loaded->value["directory"],
            "owner_user_id" => $owner,
            "signature_signed" => $progress["signed"],
            "signature_total" => $progress["total"],
            "document_tasks" => $tasks,
            "document_task_progress" => document_task_progress($tasks),
        ];
    }

    $owners = [];
    if (count($owner_ids))
    {
        $ids = implode(",", array_map("intval", array_keys($owner_ids)));
        foreach (db_select_all("id, codename, first_name, family_name FROM user WHERE id IN ($ids)") as $row)
            $owners[(int)$row["id"]] = $row;
    }
    foreach ($instances as &$entry)
        $entry["owner"] = $owners[$entry["owner_user_id"]] ?? [];
    unset($entry);

    usort($instances, function($a, $b) {
        $ad = (string)($a["instance"]["CreatedAt"] ?? "");
        $bd = (string)($b["instance"]["CreatedAt"] ?? "");
        if ($ad === $bd)
            return (strcmp((string)($b["instance"]["Id"] ?? ""), (string)($a["instance"]["Id"] ?? "")));
        return (strcmp($bd, $ad));
    });
    if (count($instances) > $limit)
        $instances = array_slice($instances, 0, $limit);
    return ($instances);
}

function document_workflow_archive_filename_component($label)
{
    $label = trim((string)$label);
    if (function_exists("iconv"))
    {
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $label);
        if (is_string($ascii) && $ascii != "")
            $label = $ascii;
    }
    $label = preg_replace('/[^A-Za-z0-9._-]+/', '_', $label);
    $label = trim((string)$label, "._-");
    return ($label != "" ? $label : "document");
}

function document_workflow_default_user_archive_target(array $instance)
{
    $owner = (int)($instance["OwnerUserId"] ?? 0);
    if ($owner <= 0)
        return (NULL);
    $completed = trim((string)($instance["CompletedAt"] ?? ""));
    $time = $completed != "" ? strtotime($completed) : false;
    $date = $time !== false ? date("Y-m-d", $time) : date("Y-m-d");
    $label = document_workflow_archive_filename_component(document_workflow_instance_label($instance));
    return ([
        "Kind" => "UserDocumentation",
        "OwnerUserId" => $owner,
        "Relative" => $label."-".$date.".pdf",
    ]);
}

function document_workflow_archive_collision_safe_path($destination, $hash, array $instance)
{
    if (!is_file($destination) || hash_file("sha256", $destination) === $hash)
        return ($destination);
    $info = pathinfo($destination);
    $dir = ($info["dirname"] ?? ".")."/";
    $name = $info["filename"] ?? "document";
    $ext = isset($info["extension"]) && $info["extension"] != "" ? ".".$info["extension"] : "";
    $instance_id = preg_replace('/[^A-Za-z0-9_-]+/', '', (string)($instance["Id"] ?? ""));
    if ($instance_id == "")
        $instance_id = substr(hash("sha256", $hash), 0, 12);
    else
        $instance_id = substr($instance_id, -12);
    return ($dir.$name."-".$instance_id.$ext);
}

function document_workflow_archive_relative_path($path)
{
    $path = str_replace("\\", "/", trim((string)$path));
    $path = ltrim($path, "/");
    if ($path == "" || strpos($path, "\0") !== false)
        return (NULL);
    $parts = [];
    foreach (explode("/", $path) as $part)
    {
        if ($part == "" || $part == ".")
            continue ;
        if ($part == "..")
            return (NULL);
        $parts[] = $part;
    }
    if (!count($parts))
        return (NULL);
    return (implode("/", $parts));
}

function document_workflow_archive_target_path(array $instance, array $target)
{
    global $Configuration;

    $kind = trim((string)($target["Kind"] ?? ($target["kind"] ?? "")));
    $relative = document_workflow_archive_relative_path($target["Relative"] ?? ($target["relative"] ?? ""));
    if ($relative === NULL || strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== "pdf")
        return (NULL);

    if ($kind === "UserDocumentation")
    {
        $owner = (int)($instance["OwnerUserId"] ?? 0);
        $target_owner = (int)($target["OwnerUserId"] ?? ($target["owner_user_id"] ?? 0));
        if ($owner <= 0 || $target_owner !== $owner)
            return (NULL);
        $user = db_select_one("codename FROM user WHERE id = $owner AND authority != -1");
        if ($user == NULL)
            return (NULL);
        return ($Configuration->UsersDir($user["codename"]).document_builder_documentation_file_root()."/".$relative);
    }
    if ($kind === "Cycle")
    {
        $id_cycle = (int)($target["CycleId"] ?? ($target["cycle_id"] ?? 0));
        if ($id_cycle <= 0 || !method_exists($Configuration, "CyclesDir"))
            return (NULL);
        $cycle = db_select_one("codename FROM cycle WHERE id = $id_cycle AND deleted IS NULL");
        if ($cycle == NULL)
            return (NULL);
        return ($Configuration->CyclesDir($cycle["codename"]).$relative);
    }
    return (NULL);
}


function document_workflow_instance_lock($directory)
{
    $file = rtrim((string)$directory, "/")."/.workflow.lock";
    $handle = @fopen($file, "c");
    if ($handle === false)
        return (NULL);
    if (!@flock($handle, LOCK_EX | LOCK_NB))
    {
        fclose($handle);
        return (NULL);
    }
    return ($handle);
}

function document_workflow_instance_unlock($handle)
{
    if (is_resource($handle))
    {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}


/**
 * Locate the Dabsic scope which DocBuilder will normalize into a given
 * Signatories.<Slot> entry. The persisted workflow Source is an assignee
 * identity (for example User_13), not a Dabsic scope name.
 */
function document_workflow_find_signatory_scope_path(array $data, $slot, array $path = [])
{
    foreach ($data as $key => $value)
    {
        if (!is_array($value) || !is_string($key)
            || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key))
            continue ;
        $next = array_merge($path, [$key]);
        $as = trim((string)($value["As"] ?? ""));
        if (!empty($value["Signatory"]) && $as === (string)$slot)
            return ($next);
        $found = document_workflow_find_signatory_scope_path($value, $slot, $next);
        if ($found !== NULL)
            return ($found);
    }
    return (NULL);
}

function document_workflow_nested_overlay_set(array &$data, array $path, array $values)
{
    if (!count($path))
        return ;
    $cursor = &$data;
    foreach ($path as $part)
    {
        if (!isset($cursor[$part]) || !is_array($cursor[$part]))
            $cursor[$part] = [];
        $cursor = &$cursor[$part];
    }
    foreach ($values as $key => $value)
        $cursor[$key] = $value;
}

/**
 * Write a small Dabsic overlay without key canonicalisation. generate_dabsic()
 * converts snake_case keys to PascalCase, which must not be done to exact
 * context names.
 */
function document_workflow_write_exact_dabsic_scope(array $data, $file)
{
    $write_scope = function (array $scope, $indent) use (&$write_scope) {
        $out = "";
        foreach ($scope as $key => $value)
        {
            if (!is_string($key) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key))
                continue ;
            if (is_array($value))
            {
                $out .= $indent."[".$key."\n";
                $out .= $write_scope($value, $indent."  ");
                $out .= $indent."]\n";
                continue ;
            }
            if (is_bool($value))
                $encoded = $value ? "1" : "0";
            else if (is_int($value) || is_float($value))
                $encoded = (string)$value;
            else
                $encoded = json_encode((string)$value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false)
                $encoded = '""';
            $out .= $indent.$key." = ".$encoded."\n";
        }
        return ($out);
    };

    $content = $write_scope($data, "");
    if ($content === "" || file_put_contents($file, $content, LOCK_EX) === false)
        return (new ErrorResponse("CannotWriteFile", $file));
    return (new Response);
}

/**
 * Rebuild the visible signed PDF from the exact Dabsic frozen at finalization.
 * Signature evidence remains bound to FrozenHash (the PDF each person reviewed);
 * the final PDF is a presentation copy with Signature and Initials overlaid.
 */
function document_workflow_render_signed_pdf(array $instance, $directory)
{
    $directory = rtrim((string)$directory, "/")."/";
    $dabsic_name = basename((string)($instance["FrozenDabsicFile"] ?? ""));
    $dabsic_hash = strtolower(trim((string)($instance["FrozenDabsicHash"] ?? "")));
    if ($dabsic_name == "" || !preg_match('/^[A-Za-z0-9_.-]+$/D', $dabsic_name))
        return (new ErrorResponse("MissingFile", "frozen Dabsic"));
    $frozen_dabsic = $directory.$dabsic_name;
    if (!is_file($frozen_dabsic) || $dabsic_hash == ""
        || hash_file("sha256", $frozen_dabsic) !== $dabsic_hash)
        return (new ErrorResponse("DocumentHashMismatch", "frozen Dabsic"));

    $loaded_dabsic = load_configuration($frozen_dabsic, [], false);
    if ($loaded_dabsic->is_error() || !is_array($loaded_dabsic->value))
        return ($loaded_dabsic->is_error()
            ? $loaded_dabsic : new ErrorResponse("InvalidFile", "frozen Dabsic"));

    $overrides = [];
    $visual_count = 0;
    foreach (($instance["Signatures"] ?? []) as $slot => $signature)
    {
        if (!document_signature_slot_is_valid($slot) || !is_array($signature)
            || ($signature["Status"] ?? "") !== "Signed")
            continue ;

        $signature_file = document_workflow_signature_file($instance, $directory, $slot);
        $signature_hash = strtolower(trim((string)($signature["SignatureSha256"] ?? "")));
        if ($signature_file === NULL || !is_file($signature_file) || $signature_hash == ""
            || hash_file("sha256", $signature_file) !== $signature_hash)
            return (new ErrorResponse("DocumentHashMismatch", "signature ".$slot));

        $initials_file = document_workflow_initials_file($instance, $directory, $slot);
        $initials_hash = strtolower(trim((string)($signature["InitialsSha256"] ?? "")));
        if ($initials_file === NULL || !is_file($initials_file) || $initials_hash == ""
            || hash_file("sha256", $initials_file) !== $initials_hash)
            return (new ErrorResponse("DocumentHashMismatch", "paraphe ".$slot));

        $values = [
            "Signature" => realpath($signature_file) ?: $signature_file,
            "Initials" => realpath($initials_file) ?: $initials_file,
        ];

        // Signatories is generated internally by DocBuilder and MUST NOT be
        // supplied by input Dabsic. Put the visual fields on the original
        // Signatory=1 / As=<slot> scope instead; ResolveSignatories() will copy
        // Signature and Initials into Signatories.<slot> before rendering.
        $source_path = document_workflow_find_signatory_scope_path($loaded_dabsic->value, $slot);
        if ($source_path === NULL)
            return (new ErrorResponse("DocumentGenerationFailed",
                "Aucun scope Dabsic Signatory/As n'a été trouvé pour le signataire ".$slot."."));
        document_workflow_nested_overlay_set($overrides, $source_path, $values);
        ++$visual_count;
    }
    if ($visual_count === 0)
        return (new ErrorResponse("MissingField", "document signatures"));

    $visual_file = $directory."visual-signatures.dab";
    $generated = document_workflow_write_exact_dabsic_scope($overrides, $visual_file);
    if ($generated->is_error())
        return ($generated);
    @chmod($visual_file, 0640);

    $render_dabsic = $directory."final.dab";
    $merge = "mergeconf -i ".escapeshellarg($frozen_dabsic).
        " -i ".escapeshellarg($visual_file).
        " -o ".escapeshellarg($render_dabsic)." --resolve";
    $merged = run_command($merge);
    if ((int)($merged["exit_code"] ?? -1) !== 0 || !is_file($render_dabsic))
        return (new ErrorResponse("DocumentGenerationFailed",
            trim((string)($merged["stderr"] ?? "")."\n".(string)($merged["stdout"] ?? ""))));
    @chmod($render_dabsic, 0640);

    // Never silently deliver an unsigned presentation copy again.
    $rendered_text = @file_get_contents($render_dabsic);
    if ($rendered_text === false)
        return (new ErrorResponse("CannotReadFile", $render_dabsic));
    foreach (($instance["Signatures"] ?? []) as $slot => $signature)
    {
        if (!is_array($signature) || ($signature["Status"] ?? "") !== "Signed")
            continue ;
        $signature_file = document_workflow_signature_file($instance, $directory, $slot);
        $initials_file = document_workflow_initials_file($instance, $directory, $slot);
        $signature_path = realpath((string)$signature_file) ?: (string)$signature_file;
        $initials_path = realpath((string)$initials_file) ?: (string)$initials_file;
        if ($signature_path == "" || strpos($rendered_text, $signature_path) === false
            || $initials_path == "" || strpos($rendered_text, $initials_path) === false)
            return (new ErrorResponse("DocumentGenerationFailed",
                "Les éléments visuels du signataire ".$slot." ont disparu lors de la fusion Dabsic."));
    }

    $temporary_pdf = $directory."final-rendering.pdf";
    $final_pdf = $directory."final.pdf";
    @unlink($temporary_pdf);
    $built = run_command("docbuilder -i ".escapeshellarg($render_dabsic).
        " -o ".escapeshellarg($temporary_pdf));
    $content = is_file($temporary_pdf) ? @file_get_contents($temporary_pdf) : false;
    if ((int)($built["exit_code"] ?? -1) !== 0 || $content === false || substr($content, 0, 4) !== "%PDF")
    {
        @unlink($temporary_pdf);
        return (new ErrorResponse("DocumentGenerationFailed",
            trim((string)($built["stderr"] ?? "")."\n".(string)($built["stdout"] ?? ""))));
    }
    if (!@rename($temporary_pdf, $final_pdf))
    {
        if (!@copy($temporary_pdf, $final_pdf))
        {
            @unlink($temporary_pdf);
            return (new ErrorResponse("CannotWriteFile", $final_pdf));
        }
        @unlink($temporary_pdf);
    }
    @chmod($final_pdf, 0640);
    $hash = hash_file("sha256", $final_pdf);
    if ($hash === false)
        return (new ErrorResponse("CannotReadFile", $final_pdf));

    // A visibly signed presentation cannot be byte-identical to the unsigned
    // PDF reviewed by the parties.
    $frozen_hash = strtolower(trim((string)($instance["FrozenHash"] ?? "")));
    if ($frozen_hash != "" && hash_equals($frozen_hash, strtolower($hash)))
        return (new ErrorResponse("DocumentGenerationFailed",
            "Le PDF final est identique au PDF gelé malgré les signatures recueillies."));

    return (new ValueResponse([
        "file" => "final.pdf",
        "hash" => $hash,
        "dabsic" => "final.dab",
        "visual" => "visual-signatures.dab",
    ]));
}

/**
 * Completed instances produced by the first visual-signature implementation
 * can be repaired in place because signature PNGs, initials PNGs, hashes and
 * frozen Dabsic are all still preserved in the workflow directory.
 */
function document_workflow_visual_render_needs_upgrade(array $instance)
{
    return (($instance["Status"] ?? "") === "Completed"
        && !document_workflow_require_pdf_sign($instance)
        && !empty($instance["FrozenDabsicFile"])
        && document_workflow_all_required_signed($instance)
        && (int)($instance["VisualRenderVersion"] ?? 0) < 2);
}

function document_workflow_repair_completed_visual_pdf(array $instance, $directory)
{
    $old_final_name = basename((string)($instance["FinalFile"] ?? ""));
    $old_final_hash = strtolower(trim((string)($instance["FinalHash"] ?? "")));

    $rendered = document_workflow_render_signed_pdf($instance, $directory);
    if ($rendered->is_error())
        return ($rendered);

    $new_name = (string)$rendered->value["file"];
    $new_hash = (string)$rendered->value["hash"];
    $new_file = rtrim((string)$directory, "/")."/".$new_name;

    // Update already-created archive copies in place when they still contain
    // exactly the obsolete final PDF. This avoids leaving an unsigned copy in
    // the student's documentation space.
    foreach (($instance["ArchivedFiles"] ?? []) as $archive_file)
    {
        $archive_file = (string)$archive_file;
        if ($archive_file == "" || !is_file($archive_file) || $old_final_hash == "")
            continue ;
        if (hash_file("sha256", $archive_file) !== $old_final_hash)
            continue ;
        if (!@copy($new_file, $archive_file) || hash_file("sha256", $archive_file) !== $new_hash)
            return (new ErrorResponse("CannotWriteFile", $archive_file));
        @chmod($archive_file, 0640);
    }

    $instance["FinalFile"] = $new_name;
    $instance["FinalHash"] = $new_hash;
    $instance["RenderedDabsicFile"] = (string)$rendered->value["dabsic"];
    $instance["VisualSignaturesFile"] = (string)$rendered->value["visual"];
    $instance["VisualRenderVersion"] = 2;
    $instance["VisualRepairedAt"] = date("Y-m-d H:i:s");
    $instance["CompletionMode"] = "ElectronicSignaturesRendered";

    // A wrong unsigned PDF may already have been mailed. Clear only delivery
    // state so the normal idempotent delivery pass resends the corrected file.
    if (!empty($instance["DeliveredAt"]))
        $instance["VisualRepairPreviousDeliveredAt"] = (string)$instance["DeliveredAt"];
    unset($instance["DeliveredAt"], $instance["DeliveredUsers"],
        $instance["DeliveryPendingUsers"], $instance["DeliveryError"],
        $instance["DeliveryLastAttemptAt"]);

    return (new ValueResponse($instance));
}

/**
 * PdfSign is opt-in for document workflows.  Infosphere signatures are the
 * normal completion mechanism; a model which really needs PdfSign stores
 * Workflow.RequirePdfSign = 1 in the instance at creation time.  Older
 * instances, which predate this flag, therefore remain non-blocking.
 */
function document_workflow_require_pdf_sign(array $instance)
{
    return (!empty($instance["RequirePdfSign"]));
}

/**
 * PdfSign is an optional hardening layer.  Ordinary DocBuilder documents are
 * complete once all required Infosphere signatures are collected.  A model
 * can opt into mandatory PdfSign with Workflow.RequirePdfSign = 1.
 * Legacy/non-model workflows keep the historical school-configuration rule.
 */
function document_workflow_finalize_signed_without_pdfsign($file)
{
    $loaded = document_workflow_load_instance(dirname((string)$file));
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    $status = (string)($instance["Status"] ?? "");
    $repair_completed = document_workflow_visual_render_needs_upgrade($instance);

    // Clean the stale error produced by older Albedo versions which tried
    // PdfSign even for ordinary document workflows.
    if ($status === "Completed" && !$repair_completed)
    {
        if (!document_workflow_require_pdf_sign($instance)
            && strpos((string)($instance["SealError"] ?? ""), "PdfSign") === 0)
        {
            unset($instance["SealError"], $instance["SealErrorDetail"]);
            if (empty($instance["SealSkipped"]))
                $instance["SealSkipped"] = "PdfSignNotRequired";
            document_workflow_write_instance($loaded->value["file"], $instance);
        }
        return (new ValueResponse(["completed" => true, "mode" => (string)($instance["CompletionMode"] ?? "")]));
    }
    if ($status !== "Signed" && !$repair_completed)
        return (new ValueResponse(["completed" => false, "pending" => true]));

    if ($status === "Signed" && document_workflow_require_pdf_sign($instance))
        return (new ValueResponse([
            "completed" => false,
            "pending" => true,
            "pdfsign" => "required",
        ]));

    $lock = document_workflow_instance_lock($loaded->value["directory"]);
    if ($lock === NULL)
        return (new ValueResponse(["completed" => false, "pending" => true, "locked" => true]));
    try
    {
        $loaded = document_workflow_load_instance($loaded->value["directory"]);
        if ($loaded->is_error())
            return ($loaded);
        $instance = $loaded->value["data"];

        if (($instance["Status"] ?? "") === "Completed")
        {
            if (!document_workflow_visual_render_needs_upgrade($instance))
                return (new ValueResponse(["completed" => true, "mode" => (string)($instance["CompletionMode"] ?? "")]));

            $repaired = document_workflow_repair_completed_visual_pdf(
                $instance, $loaded->value["directory"]
            );
            if ($repaired->is_error())
                return ($repaired);
            $instance = $repaired->value;
            unset($instance["SealError"], $instance["SealErrorDetail"],
                $instance["CompletionError"], $instance["Error"], $instance["ErrorAt"]);
            $written = document_workflow_write_instance($loaded->value["file"], $instance);
            if ($written->is_error())
                return ($written);
            add_log(EDITING_OPERATION, "Document workflow ".($instance["Id"] ?? "?").
                " repaired with visible signatures and initials.", (int)($instance["OwnerUserId"] ?? 0));
            return (new ValueResponse([
                "completed" => true,
                "mode" => (string)$instance["CompletionMode"],
                "repaired" => true,
            ]));
        }

        if (($instance["Status"] ?? "") !== "Signed")
            return (new ValueResponse(["completed" => false, "pending" => true]));
        if (document_workflow_require_pdf_sign($instance))
            return (new ValueResponse(["completed" => false, "pending" => true, "pdfsign" => "required"]));

        $directory = $loaded->value["directory"];
        $frozen_name = basename((string)($instance["FrozenFile"] ?? "frozen.pdf"));
        $frozen = $directory.$frozen_name;
        $hash = trim((string)($instance["FrozenHash"] ?? ""));
        if (!is_file($frozen) || $hash == "" || hash_file("sha256", $frozen) !== $hash)
            return (new ErrorResponse("DocumentHashMismatch", "frozen PDF"));

        $final_name = $frozen_name;
        $final_hash = $hash;
        $completion_mode = "ElectronicSignatures";
        if (!empty($instance["FrozenDabsicFile"]))
        {
            $rendered = document_workflow_render_signed_pdf($instance, $directory);
            if ($rendered->is_error())
                return ($rendered);
            $final_name = (string)$rendered->value["file"];
            $final_hash = (string)$rendered->value["hash"];
            $instance["RenderedDabsicFile"] = (string)$rendered->value["dabsic"];
            $instance["VisualSignaturesFile"] = (string)$rendered->value["visual"];
            $instance["VisualRenderVersion"] = 2;
            $completion_mode = "ElectronicSignaturesRendered";
        }

        $instance["Status"] = "Completed";
        $instance["CompletedAt"] = date("Y-m-d H:i:s");
        $instance["FinalFile"] = $final_name;
        $instance["FinalHash"] = $final_hash;
        $instance["CompletionMode"] = $completion_mode;
        $instance["SealSkipped"] = "PdfSignNotRequired";
        unset($instance["SealError"], $instance["SealErrorDetail"], $instance["CompletionError"],
            $instance["Error"], $instance["ErrorAt"]);
        $written = document_workflow_write_instance($loaded->value["file"], $instance);
        if ($written->is_error())
            return ($written);
        add_log(TRACE, "Document workflow ".($instance["Id"] ?? "?").
            " completed from Infosphere signatures; PdfSign not required.", 1, true);
        return (new ValueResponse(["completed" => true, "mode" => $completion_mode]));
    }
    finally
    {
        document_workflow_instance_unlock($lock);
    }
}

function document_workflow_delivery_retry_due(array $instance)
{
    $last = trim((string)($instance["DeliveryLastAttemptAt"] ?? ""));
    if ($last == "")
        return (true);
    $time = strtotime($last);
    return ($time === false || time() - $time >= 3 * 3600);
}

/**
 * Dabsic naturally collapses a one-element list to a scalar when metadata is
 * read back.  Delivery metadata is semantically a list even when there is only
 * one recipient, so normalize it at the boundary instead of assuming foreach()
 * always receives an array.
 */
function document_workflow_integer_list($value)
{
    if ($value === NULL || $value === "")
        return ([]);
    if (!is_array($value))
        $value = [$value];
    $out = [];
    foreach ($value as $item)
    {
        if (is_array($item))
            continue ;
        $id = (int)$item;
        if ($id > 0)
            $out[$id] = true;
    }
    return (array_keys($out));
}

function document_workflow_string_list($value)
{
    if ($value === NULL || $value === "")
        return ([]);
    if (!is_array($value))
        $value = [$value];
    $out = [];
    foreach ($value as $item)
    {
        if (is_array($item) || is_object($item))
            continue ;
        $item = trim((string)$item);
        if ($item != "")
            $out[] = $item;
    }
    return ($out);
}

function document_workflow_delivery_mail_records(array $instance)
{
    $records = [];
    foreach (document_workflow_string_list($instance["DeliveryMailRecords"] ?? []) as $encoded)
    {
        $record = json_decode($encoded, true);
        if (!is_array($record))
            continue ;
        $id_user = (int)($record["user"] ?? 0);
        $mail = trim((string)($record["mail"] ?? ""));
        if ($id_user <= 0 || !filter_var($mail, FILTER_VALIDATE_EMAIL))
            continue ;
        $records[$id_user] = [
            "user" => $id_user,
            "mail" => $mail,
            "message_id" => trim((string)($record["message_id"] ?? "")),
            "accepted_at" => trim((string)($record["accepted_at"] ?? "")),
            "status" => trim((string)($record["status"] ?? "accepted")),
            "status_at" => trim((string)($record["status_at"] ?? "")),
            "details" => trim((string)($record["details"] ?? "")),
        ];
    }
    return ($records);
}

function document_workflow_store_delivery_mail_records(array &$instance, array $records)
{
    ksort($records, SORT_NUMERIC);
    $encoded = [];
    foreach ($records as $record)
    {
        if (!is_array($record))
            continue ;
        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json) && $json != "")
            $encoded[] = $json;
    }
    if (count($encoded))
        $instance["DeliveryMailRecords"] = $encoded;
    else
        unset($instance["DeliveryMailRecords"]);
}

function document_workflow_delivery_addresses(array $instance)
{
    $mails = [];
    foreach (document_workflow_delivery_mail_records($instance) as $record)
    {
        $mail = trim((string)($record["mail"] ?? ""));
        if ($mail != "")
            $mails[strtolower($mail)] = $mail;
    }
    if (!count($mails))
        foreach (document_workflow_integer_list($instance["DeliveredUsers"] ?? []) as $id_user)
        {
            $user = db_select_one("mail FROM user WHERE id = ".(int)$id_user." AND authority != -1");
            $mail = trim((string)($user["mail"] ?? ""));
            if ($mail != "" && strcasecmp($mail, "nomail") != 0)
                $mails[strtolower($mail)] = $mail;
        }
    return (array_values($mails));
}

function document_workflow_delivery_recipients(array $instance)
{
    $ids = [];
    foreach (document_workflow_integer_list($instance["DeliveryUsers"] ?? []) as $delivery_user)
    {
        $id = (int)$delivery_user;
        if ($id > 0)
            $ids[$id] = true;
    }

    // Models declaring Workflow.DeliveryRecipients own their final delivery
    // policy. This lets a director sign a letter without automatically
    // receiving a copy: only the semantic recipients named by the model are
    // mailed. Old models keep the historical owner + contributors + signers
    // behaviour below.
    if (!empty($instance["DeliveryExplicit"]))
        return (array_keys($ids));

    $owner = (int)($instance["OwnerUserId"] ?? 0);
    if ($owner > 0)
        $ids[$owner] = true;
    foreach (($instance["Signatures"] ?? []) as $signature)
    {
        if (!is_array($signature) || document_workflow_signature_is_external_campaign($signature))
            continue ;
        $id = (int)($signature["SignatoryUserId"] ?? 0);
        if ($id > 0)
            $ids[$id] = true;
    }
    return (array_keys($ids));
}

function document_workflow_delivery_school_label(array $instance)
{
    $school_label = trim((string)($instance["SchoolCodename"] ?? ""));
    if ($school_label == "")
        $school_label = (string)(document_workflow_school_codename_for_user(
            (int)($instance["OwnerUserId"] ?? 0)) ?? "");
    return ($school_label != "" ? strtoupper($school_label) : "Infosphere");
}

function document_workflow_delivery_subject(array $instance)
{
    return (document_workflow_delivery_school_label($instance)." — ".document_workflow_instance_label($instance));
}

function document_workflow_delivery_filename(array $instance)
{
    $model = document_workflow_instance_label($instance);
    $ascii_model = function_exists("iconv") ? @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $model) : false;
    if (is_string($ascii_model) && $ascii_model != "")
        $model = $ascii_model;
    $model = preg_replace('/[^A-Za-z0-9._-]+/', '_', $model);
    if ($model == "")
        $model = "document";
    return ($model."-".(string)($instance["Id"] ?? "final").".pdf");
}

/**
 * Send the immutable final PDF to every workflow participant who must retain
 * it: beneficiary, contributors selected for delivery and signatories.
 */
function document_workflow_deliver_completed_instance($file, $respect_retry_delay = true)
{
    require_once (__DIR__."/send_mail.php");

    $loaded = document_workflow_load_instance(dirname((string)$file));
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") != "Completed")
        return (new ValueResponse(["delivered" => false, "pending" => true]));

    $repair_explicit_delivery = false;
    if (!empty($instance["DeliveredAt"]) && !empty($instance["DeliveryExplicit"]))
    {
        $expected_users = document_workflow_delivery_recipients($instance);
        $delivered_users = document_workflow_integer_list($instance["DeliveredUsers"] ?? []);
        $repair_explicit_delivery = count($expected_users) > 0
            && count(array_diff($expected_users, $delivered_users)) > 0;
    }
    if (!empty($instance["DeliveredAt"]) && !$repair_explicit_delivery)
        return (new ValueResponse(["delivered" => true, "pending" => false]));
    if ($respect_retry_delay && !$repair_explicit_delivery && !document_workflow_delivery_retry_due($instance))
        return (new ValueResponse(["delivered" => false, "pending" => true, "retry_later" => true]));

    $lock = document_workflow_instance_lock($loaded->value["directory"]);
    if ($lock === NULL)
        return (new ValueResponse(["delivered" => false, "pending" => true, "locked" => true]));
    try
    {
        $loaded = document_workflow_load_instance($loaded->value["directory"]);
        if ($loaded->is_error())
            return ($loaded);
        $instance = $loaded->value["data"];
        if (($instance["Status"] ?? "") != "Completed")
            return (new ValueResponse(["delivered" => false, "pending" => true]));
        if (!empty($instance["DeliveredAt"]))
        {
            $expected_users = !empty($instance["DeliveryExplicit"])
                ? document_workflow_delivery_recipients($instance) : [];
            $delivered_users = document_workflow_integer_list($instance["DeliveredUsers"] ?? []);
            if (empty($instance["DeliveryExplicit"]) || !count($expected_users)
                || !count(array_diff($expected_users, $delivered_users)))
                return (new ValueResponse(["delivered" => true, "pending" => false]));
            // A previous version could mark an explicit single-recipient document
            // DeliveredAt after treating DeliveryUsers=42 as a non-iterable value.
            // Let the normal delivery pass repair that instance automatically.
            unset($instance["DeliveredAt"]);
        }

        $directory = $loaded->value["directory"];
        $final_name = basename((string)($instance["FinalFile"] ?? ($instance["SealedFile"] ?? "sealed.pdf")));
        $final = $directory.$final_name;
        $expected = (string)($instance["FinalHash"] ?? ($instance["SealedHash"] ?? ""));
        if (!is_file($final) || $expected == "" || hash_file("sha256", $final) !== $expected)
            return (new ErrorResponse("DocumentHashMismatch", "final PDF"));

        $instance["DeliveryLastAttemptAt"] = date("Y-m-d H:i:s");
        $instance["DeliveryAttemptCount"] = (int)($instance["DeliveryAttemptCount"] ?? 0) + 1;
        $filename = document_workflow_delivery_filename($instance);
        $attachments = [$filename => file_get_contents($final)];
        if (!empty($instance["TimestampFile"]))
        {
            $timestamp = $directory.basename((string)$instance["TimestampFile"]);
            $timestamp_hash = (string)($instance["TimestampHash"] ?? "");
            if (is_file($timestamp) && $timestamp_hash != "" && hash_file("sha256", $timestamp) === $timestamp_hash)
                $attachments[$filename.".tsr"] = file_get_contents($timestamp);
        }

        $recipients = document_workflow_delivery_recipients($instance);
        if (!empty($instance["DeliveryExplicit"]) && !count($recipients))
        {
            $instance["DeliveryError"] = "NoDeliveryRecipients";
            unset($instance["DeliveredAt"]);
            document_workflow_write_instance($loaded->value["file"], $instance);
            return (new ErrorResponse("InvalidParameter", "DeliveryUsers"));
        }
        $delivered = [];
        foreach (document_workflow_integer_list($instance["DeliveredUsers"] ?? []) as $id_user)
        {
            $id_user = (int)$id_user;
            if ($id_user > 0)
                $delivered[$id_user] = true;
        }
        $mail_records = document_workflow_delivery_mail_records($instance);
        $failed = [];
        foreach ($recipients as $id_user)
        {
            $id_user = (int)$id_user;
            if (isset($delivered[$id_user]))
                continue ;
            $user = db_select_one("id, first_name, family_name, mail FROM user WHERE id = ".(int)$id_user." AND authority != -1");
            $mail = trim((string)($user["mail"] ?? ""));
            if ($user == NULL || $mail == "" || strcasecmp($mail, "nomail") == 0)
            {
                $failed[] = $id_user;
                continue ;
            }
            $document_label = document_workflow_instance_label($instance);
            $school_label = document_workflow_delivery_school_label($instance);
            $subject = document_workflow_delivery_subject($instance);
            $content = "Bonjour".(trim((string)($user["first_name"] ?? "")) != "" ? " ".$user["first_name"] : "").",\n\n";
            if ($id_user == (int)($instance["OwnerUserId"] ?? 0))
                $content .= "Vous trouverez en pièce jointe le document « ".$document_label." » qui vous est adressé par ".$school_label.".\n\n".
                    "Nous vous invitons à en prendre connaissance et à le conserver. Une copie est également disponible dans votre espace Infosphère.\n\n";
            else
                $content .= "Vous trouverez en pièce jointe la version définitive du document « ".$document_label." » auquel vous avez participé dans Infosphère.\n\n".
                    "Une copie est conservée dans Infosphère.\n\n";
            $content .= "Cordialement,\n".$school_label."\n";
            try
            {
                $sent = send_mail($mail, $subject, $content, NULL, $attachments, true);
            }
            catch (Throwable $e)
            {
                $sent = new ErrorResponse("CannotSendMail", $e->getMessage());
            }
            if ($sent->is_error())
                $failed[] = $id_user;
            else
            {
                $delivered[$id_user] = true;
                $mail_records[$id_user] = [
                    "user" => $id_user,
                    "mail" => $mail,
                    "message_id" => trim((string)($sent->value["message_id"] ?? "")),
                    "accepted_at" => date("Y-m-d H:i:s"),
                    "status" => "accepted",
                    "status_at" => "",
                    "details" => trim((string)($sent->value["message"] ?? "")),
                ];
                add_log(TRACE,
                    "Document workflow ".$instance["Id"]." final PDF accepted by Mailgun for ".$mail.
                    (($mail_records[$id_user]["message_id"] ?? "") != ""
                        ? " (message ".$mail_records[$id_user]["message_id"].")" : ""),
                    $id_user, true);
            }
        }

        $instance["DeliveredUsers"] = array_keys($delivered);
        document_workflow_store_delivery_mail_records($instance, $mail_records);
        $accepted_addresses = [];
        foreach ($mail_records as $record)
            if (trim((string)($record["mail"] ?? "")) != "")
                $accepted_addresses[] = trim((string)$record["mail"]);
        if (count($accepted_addresses))
            $instance["DeliveryAcceptedTo"] = implode(", ", array_values(array_unique($accepted_addresses)));
        if (count($failed))
        {
            $instance["DeliveryPendingUsers"] = $failed;
            $instance["DeliveryError"] = "CannotDeliverToAllRecipients";
        }
        else
        {
            unset($instance["DeliveryPendingUsers"], $instance["DeliveryError"]);
            $accepted_at = date("Y-m-d H:i:s");
            // DeliveredAt is kept for compatibility with older UI/code, but it
            // historically means "accepted by Mailgun", not confirmed inbox
            // delivery. DeliveryAcceptedAt makes that distinction explicit.
            $instance["DeliveredAt"] = $accepted_at;
            $instance["DeliveryAcceptedAt"] = $accepted_at;
            $instance["DeliveryMailStatus"] = "accepted";
            unset($instance["DeliveryConfirmedAt"], $instance["DeliveryMailError"]);
        }
        $written = document_workflow_write_instance($loaded->value["file"], $instance);
        if ($written->is_error())
            return ($written);
        return (new ValueResponse([
            // Historical key kept for callers: it means the hand-off to the
            // mail service succeeded for every recipient.
            "delivered" => !count($failed),
            "accepted" => !count($failed),
            "users" => array_keys($delivered),
            "failed" => $failed,
        ]));
    }
    finally
    {
        document_workflow_instance_unlock($lock);
    }
}

/**
 * Refresh the post-acceptance status of document e-mails from Mailgun Events.
 *
 * This deliberately does not resend anything. It only replaces the ambiguous
 * historical "sent" state by a factual accepted/delivered/failed state.
 */
function document_workflow_refresh_delivery_status($file, $respect_check_delay = true)
{
    require_once (__DIR__."/send_mail.php");

    $loaded = document_workflow_load_instance(dirname((string)$file));
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") != "Completed")
        return (new ValueResponse(["status" => "pending"]));

    $records = document_workflow_delivery_mail_records($instance);
    if (!count($records))
    {
        // Old instances predate Mailgun message-id capture.  When the document
        // has exactly one recipient, recover its message id from the Events API
        // only if the narrow recipient/subject/time query identifies one unique
        // Mailgun message.  Otherwise keep the honest "accepted, untracked"
        // state instead of guessing.
        $accepted_at = trim((string)($instance["DeliveryAcceptedAt"] ?? ($instance["DeliveredAt"] ?? "")));
        $addresses = document_workflow_delivery_addresses($instance);
        $recipient_ids = document_workflow_delivery_recipients($instance);
        if ($accepted_at != "" && count($addresses) === 1 && count($recipient_ids) === 1)
        {
            $recovered = send_mail_find_message_id(
                $addresses[0],
                document_workflow_delivery_subject($instance),
                $accepted_at
            );
            $recovered_value = is_array($recovered->value ?? NULL) ? $recovered->value : [];
            if (!empty($recovered_value["found"]) && trim((string)($recovered_value["message_id"] ?? "")) != "")
            {
                $id_user = (int)$recipient_ids[0];
                $records[$id_user] = [
                    "user" => $id_user,
                    "mail" => $addresses[0],
                    "message_id" => trim((string)$recovered_value["message_id"]),
                    "accepted_at" => $accepted_at,
                    "status" => "accepted",
                    "status_at" => "",
                    "details" => "message id recovered from Mailgun events",
                ];
                document_workflow_store_delivery_mail_records($instance, $records);
                $instance["DeliveryAcceptedAt"] = $accepted_at;
                $instance["DeliveryAcceptedTo"] = $addresses[0];
                $instance["DeliveryMailStatus"] = "accepted";
                document_workflow_write_instance($loaded->value["file"], $instance);
            }
        }
        if (!count($records))
        {
            if ($accepted_at != "")
            {
                $instance["DeliveryAcceptedAt"] = $accepted_at;
                $instance["DeliveryMailStatus"] = "accepted_untracked";
                if (count($addresses))
                    $instance["DeliveryAcceptedTo"] = implode(", ", $addresses);
                document_workflow_write_instance($loaded->value["file"], $instance);
            }
            return (new ValueResponse(["status" => (string)($instance["DeliveryMailStatus"] ?? "unknown")]));
        }
    }

    if (($instance["DeliveryMailStatus"] ?? "") === "delivered"
        && !empty($instance["DeliveryConfirmedAt"]))
        return (new ValueResponse(["status" => "delivered"]));

    if ($respect_check_delay)
    {
        $last = trim((string)($instance["DeliveryStatusCheckedAt"] ?? ""));
        $last_time = $last != "" ? strtotime($last) : false;
        if ($last_time !== false && time() - $last_time < 10 * 60)
            return (new ValueResponse(["status" => (string)($instance["DeliveryMailStatus"] ?? "accepted"), "retry_later" => true]));
    }

    $lock = document_workflow_instance_lock($loaded->value["directory"]);
    if ($lock === NULL)
        return (new ValueResponse(["status" => "pending", "locked" => true]));
    try
    {
        $loaded = document_workflow_load_instance($loaded->value["directory"]);
        if ($loaded->is_error())
            return ($loaded);
        $instance = $loaded->value["data"];
        $records = document_workflow_delivery_mail_records($instance);
        if (!count($records))
            return (new ValueResponse(["status" => "unknown"]));

        $instance["DeliveryStatusCheckedAt"] = date("Y-m-d H:i:s");
        $all_delivered = true;
        $failed = [];
        $pending = [];
        $delivered_at = [];
        $accepted_to = [];
        foreach ($records as $id_user => &$record)
        {
            $mail = trim((string)($record["mail"] ?? ""));
            $message_id = trim((string)($record["message_id"] ?? ""));
            if ($mail != "")
                $accepted_to[] = $mail;
            if ($message_id == "")
            {
                $all_delivered = false;
                $pending[] = $mail != "" ? $mail : ("#".$id_user);
                $record["status"] = "accepted_untracked";
                continue ;
            }
            if (($record["status"] ?? "") === "delivered")
            {
                if (!empty($record["status_at"]))
                    $delivered_at[] = (string)$record["status_at"];
                continue ;
            }

            $status = send_mail_delivery_status($message_id, $mail);
            $value = is_array($status->value ?? NULL) ? $status->value : [];
            $mail_status = trim((string)($value["status"] ?? "unknown"));
            if ($mail_status == "")
                $mail_status = "unknown";
            $record["status"] = $mail_status;
            $record["status_at"] = trim((string)($value["timestamp"] ?? ""));
            $record["details"] = trim((string)($value["details"] ?? ($value["error"] ?? "")));

            if ($mail_status === "delivered")
            {
                if ($record["status_at"] != "")
                    $delivered_at[] = $record["status_at"];
            }
            else if ($mail_status === "failed")
            {
                $all_delivered = false;
                $failed[] = $mail.($record["details"] != "" ? " (".$record["details"].")" : "");
            }
            else
            {
                $all_delivered = false;
                $pending[] = $mail;
            }
        }
        unset($record);

        document_workflow_store_delivery_mail_records($instance, $records);
        if (count($accepted_to))
            $instance["DeliveryAcceptedTo"] = implode(", ", array_values(array_unique($accepted_to)));

        if (count($failed))
        {
            $instance["DeliveryMailStatus"] = "failed";
            $instance["DeliveryMailError"] = implode(" ; ", $failed);
            unset($instance["DeliveryConfirmedAt"]);
        }
        else if ($all_delivered && count($records))
        {
            $instance["DeliveryMailStatus"] = "delivered";
            unset($instance["DeliveryMailError"]);
            if (count($delivered_at))
            {
                usort($delivered_at, function ($a, $b) { return (strcmp($a, $b)); });
                $instance["DeliveryConfirmedAt"] = end($delivered_at);
            }
            else if (empty($instance["DeliveryConfirmedAt"]))
                $instance["DeliveryConfirmedAt"] = date("Y-m-d H:i:s");
        }
        else
        {
            $instance["DeliveryMailStatus"] = "accepted";
            unset($instance["DeliveryMailError"], $instance["DeliveryConfirmedAt"]);
        }

        $written = document_workflow_write_instance($loaded->value["file"], $instance);
        if ($written->is_error())
            return ($written);
        return (new ValueResponse([
            "status" => (string)$instance["DeliveryMailStatus"],
            "failed" => $failed,
            "pending" => $pending,
            "to" => array_values(array_unique($accepted_to)),
        ]));
    }
    finally
    {
        document_workflow_instance_unlock($lock);
    }
}

function document_workflow_retry_delivery($owner_user_id, $instance_id)
{
    $owner_user_id = (int)$owner_user_id;
    $instance_id = trim((string)$instance_id);
    if (!document_workflow_can_monitor())
        return (new ErrorResponse("PermissionDenied"));
    if ($owner_user_id <= 0 || $instance_id == "")
        return (new ErrorResponse("InvalidParameter", "document instance"));

    $loaded = document_workflow_find_instance($owner_user_id, $instance_id);
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (!document_workflow_can_view_instance($instance))
        return (new ErrorResponse("PermissionDenied"));
    if (($instance["Status"] ?? "") !== "Completed")
        return (new ErrorResponse("InvalidParameter", "document not completed"));

    $lock = document_workflow_instance_lock($loaded->value["directory"]);
    if ($lock === NULL)
        return (new ErrorResponse("CannotEdit", "document delivery lock"));
    try
    {
        $loaded = document_workflow_load_instance($loaded->value["directory"]);
        if ($loaded->is_error())
            return ($loaded);
        $instance = $loaded->value["data"];
        $instance["DeliveryManualRetryCount"] = (int)($instance["DeliveryManualRetryCount"] ?? 0) + 1;
        $instance["DeliveryManualRetryAt"] = date("Y-m-d H:i:s");
        unset(
            $instance["DeliveredAt"],
            $instance["DeliveredUsers"],
            $instance["DeliveryPendingUsers"],
            $instance["DeliveryError"],
            $instance["DeliveryAcceptedAt"],
            $instance["DeliveryAcceptedTo"],
            $instance["DeliveryMailStatus"],
            $instance["DeliveryConfirmedAt"],
            $instance["DeliveryMailError"],
            $instance["DeliveryStatusCheckedAt"],
            $instance["DeliveryMailRecords"],
            $instance["DeliveryLastAttemptAt"]
        );
        $written = document_workflow_write_instance($loaded->value["file"], $instance);
        if ($written->is_error())
            return ($written);
    }
    finally
    {
        document_workflow_instance_unlock($lock);
    }

    $sent = document_workflow_deliver_completed_instance($loaded->value["file"], false);
    if ($sent->is_error())
        return ($sent);
    document_workflow_refresh_delivery_status($loaded->value["file"], false);
    return (new ValueResponse([
        "accepted" => !empty($sent->value["accepted"]),
        "users" => $sent->value["users"] ?? [],
        "failed" => $sent->value["failed"] ?? [],
    ]));
}

function document_workflow_model_source_file(array $instance)
{
    $reference = trim((string)($instance["Model"] ?? ""));
    if ($reference == "")
        return (NULL);

    require_once (__DIR__."/document_sources.php");
    $split = explode(":", $reference, 2);
    if (count($split) == 2)
    {
        $roots = document_source_roots();
        $source = $split[0];
        $path = document_source_normalize_path($split[1]);
        if (!isset($roots[$source]) || $path == ""
            || pathinfo($path, PATHINFO_EXTENSION) != "dab"
            || !document_source_path_is_visible($path))
            return (NULL);
        $file = rtrim($roots[$source]["root"], "/")."/".$path;
        return (is_file($file) ? $file : NULL);
    }

    // Older frozen instances stored the project-relative model path directly
    // instead of the newer <source>:<path> reference. Background maintenance
    // must tolerate that legacy value instead of aborting the whole HTTP call
    // through document_reference_to_path()/bad_request().
    $path = document_source_normalize_path($reference);
    if ($path == "" || pathinfo($path, PATHINFO_EXTENSION) != "dab"
        || !document_source_path_is_visible($path))
        return (NULL);
    $file = realpath(__DIR__."/../".$path);
    return ($file !== false && is_file($file) ? $file : NULL);
}

function document_workflow_queue_for_print_enabled(array $instance)
{
    if (array_key_exists("QueueForPrint", $instance))
        return (!empty($instance["QueueForPrint"]));

    // Compatibility for already-frozen instances created before the workflow
    // option was persisted in instance.dab: consult the current model once.
    $file = document_workflow_model_source_file($instance);
    if ($file == NULL)
        return (false);
    $content = @file_get_contents($file);
    if ($content === false)
        return (false);
    if (!preg_match('/^[ \\t]*\\[Workflow\\s*$([\\s\\S]*?)^[ \\t]*\\][ \\t]*$/m', $content, $scope))
        return (false);
    return (preg_match('/^[ \\t]*QueueForPrint[ \\t]*=[ \\t]*(?:1|true|yes|on|"1"|"true"|"yes"|"on")[ \\t]*$/mi', $scope[1]) === 1);
}

function document_workflow_print_recipient_source(array $instance)
{
    $source = trim((string)($instance["PrintRecipient"] ?? ""));
    if ($source != "")
        return ($source);

    // Compatibility for already-frozen instances: read the Workflow option
    // from the model which produced the document.
    $file = document_workflow_model_source_file($instance);
    if ($file == NULL)
        return ("");
    $content = @file_get_contents($file);
    if ($content === false)
        return ("");
    if (!preg_match('/^[ \\t]*\\[Workflow\\s*$([\\s\\S]*?)^[ \\t]*\\][ \\t]*$/m', $content, $scope))
        return ("");
    if (!preg_match('/^[ \\t]*PrintRecipient[ \\t]*=[ \\t]*"?([A-Za-z0-9_]+)"?[ \\t]*$/mi', $scope[1], $match))
        return ("");
    return ((string)$match[1]);
}

function document_workflow_print_recipient(array $instance)
{
    $owner_user_id = (int)($instance["OwnerUserId"] ?? 0);
    $source = document_workflow_print_recipient_source($instance);
    $id_user = $source == "" ? $owner_user_id : document_workflow_source_user_id(
        $owner_user_id,
        $source,
        (int)($instance["CreatedBy"] ?? 0)
    );
    if ($id_user === NULL || (int)$id_user <= 0)
        $id_user = $owner_user_id;
    $user = db_select_one("id, first_name, family_name, codename FROM user WHERE id = ".(int)$id_user." AND authority != -1");
    if (!is_array($user))
        return (["id" => $owner_user_id, "label" => document_workflow_instance_label($instance)]);
    $label = trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? ""));
    if ($label == "")
        $label = trim((string)($user["codename"] ?? ""));
    if ($label == "")
        $label = document_workflow_instance_label($instance);
    return (["id" => (int)$user["id"], "label" => $label]);
}

function document_workflow_queue_completed_instance_for_print($file)
{
    $loaded = document_workflow_load_instance(dirname((string)$file));
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") !== "Completed" || !document_workflow_queue_for_print_enabled($instance))
        return (new ValueResponse(["queued" => false, "pending" => true]));
    if (!empty($instance["PrintQueuedAt"]))
        return (new ValueResponse(["queued" => true, "task_id" => (int)($instance["PrintTaskId"] ?? 0)]));

    $lock = document_workflow_instance_lock($loaded->value["directory"]);
    if ($lock === NULL)
        return (new ValueResponse(["queued" => false, "pending" => true, "locked" => true]));
    try
    {
        $loaded = document_workflow_load_instance($loaded->value["directory"]);
        if ($loaded->is_error())
            return ($loaded);
        $instance = $loaded->value["data"];
        if (($instance["Status"] ?? "") !== "Completed" || !document_workflow_queue_for_print_enabled($instance))
            return (new ValueResponse(["queued" => false, "pending" => true]));
        if (!empty($instance["PrintQueuedAt"]))
            return (new ValueResponse(["queued" => true, "task_id" => (int)($instance["PrintTaskId"] ?? 0)]));

        $directory = $loaded->value["directory"];
        $final_name = basename((string)($instance["FinalFile"] ?? ($instance["SealedFile"] ?? "sealed.pdf")));
        $final = $directory.$final_name;
        $expected = (string)($instance["FinalHash"] ?? ($instance["SealedHash"] ?? ""));
        if (!is_file($final) || $expected == "" || hash_file("sha256", $final) !== $expected)
            return (new ErrorResponse("DocumentHashMismatch", "print PDF"));

        require_once (__DIR__."/document_print.php");
        $owner_user_id = (int)($instance["OwnerUserId"] ?? 0);
        $actor_user_id = (int)($instance["CreatedBy"] ?? 0);
        $school_id = document_print_school_id_for_user($owner_user_id);
        $recipient = document_workflow_print_recipient($instance);
        $context = [
            "type" => "user",
            "owner_user_id" => $owner_user_id,
            "school_id" => $school_id,
            "source_key" => "document-workflow:".(string)($instance["Id"] ?? ""),
            "recipient_user_id" => (int)$recipient["id"],
            "recipient_label" => (string)$recipient["label"],
        ];
        $queued = document_print_queue_content_for_actor(
            $actor_user_id,
            file_get_contents($final),
            document_workflow_delivery_filename($instance),
            document_workflow_instance_label($instance),
            $context
        );
        if ($queued->is_error())
        {
            $instance["PrintQueueError"] = (string)($queued->label ?? "CannotQueuePrint");
            $instance["PrintQueueLastAttemptAt"] = date("Y-m-d H:i:s");
            document_workflow_write_instance($loaded->value["file"], $instance);
            return ($queued);
        }
        $instance["PrintQueuedAt"] = date("Y-m-d H:i:s");
        $instance["PrintTaskId"] = (int)($queued->value["id"] ?? 0);
        unset($instance["PrintQueueError"], $instance["PrintQueueLastAttemptAt"]);
        $written = document_workflow_write_instance($loaded->value["file"], $instance);
        if ($written->is_error())
            return ($written);
        add_log(TRACE, "Document workflow ".($instance["Id"] ?? "?")." added to print queue.", 1, true);
        return (new ValueResponse(["queued" => true, "task_id" => (int)($queued->value["id"] ?? 0)]));
    }
    finally
    {
        document_workflow_instance_unlock($lock);
    }
}

/**
 * Copy a completed immutable PDF to its business archives. The workflow
 * instance remains the authoritative source; these copies exist for normal
 * file browsing on the student and cycle pages.
 */
function document_workflow_archive_completed_instance($file)
{
    $loaded = document_workflow_load_instance(dirname((string)$file));
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") !== "Completed")
        return (new ValueResponse(["archived" => false, "pending" => true]));
    if (!empty($instance["ArchivedAt"]))
        return (new ValueResponse(["archived" => true, "files" => $instance["ArchivedFiles"] ?? []]));

    $targets = $instance["ArchiveTargets"] ?? [];
    if (!is_array($targets) || !count($targets))
    {
        // Generic finalized user documents are visible directly at the root of
        // the profile's documentation browser. Specialized workflows (such as
        // attendance registers) keep their explicit archive destinations.
        $default_target = document_workflow_default_user_archive_target($instance);
        $targets = $default_target === NULL ? [] : ["UserDocument" => $default_target];
    }
    if (!count($targets))
        return (new ValueResponse(["archived" => false, "targets" => 0]));
    $directory = $loaded->value["directory"];
    $final = $directory.($instance["FinalFile"] ?? ($instance["SealedFile"] ?? ""));
    $hash = trim((string)($instance["FinalHash"] ?? ($instance["SealedHash"] ?? "")));
    if (!is_file($final) || $hash == "" || hash_file("sha256", $final) !== $hash)
        return (new ErrorResponse("InvalidFile", "final document"));

    $archived = [];
    foreach ($targets as $name => $target)
    {
        if (!is_array($target))
            return (new ErrorResponse("InvalidParameter", "archive target"));
        $destination = document_workflow_archive_target_path($instance, $target);
        if ($destination === NULL)
            return (new ErrorResponse("InvalidParameter", "archive target ".$name));
        $destination = document_workflow_archive_collision_safe_path($destination, $hash, $instance);
        new_directory(dirname($destination)."/index.php");
        if (!is_file($destination) || hash_file("sha256", $destination) !== $hash)
        {
            if (!@copy($final, $destination))
                return (new ErrorResponse("CannotWriteFile", $destination));
            @chmod($destination, 0640);
        }
        if (hash_file("sha256", $destination) !== $hash)
            return (new ErrorResponse("CannotWriteFile", $destination));
        $archived[(string)$name] = $destination;
    }

    $instance["ArchivedAt"] = date("Y-m-d H:i:s");
    $instance["ArchivedFiles"] = $archived;
    unset($instance["ArchiveError"]);
    $written = document_workflow_write_instance($loaded->value["file"], $instance);
    if ($written->is_error())
        return ($written);
    return (new ValueResponse(["archived" => true, "files" => $archived]));
}

function document_workflow_pdf_file_for_view(array $instance, $directory)
{
    $directory = rtrim((string)$directory, "/")."/";
    $candidates = [
        ["file" => $instance["FinalFile"] ?? "", "hash" => $instance["FinalHash"] ?? ""],
        ["file" => $instance["SealedFile"] ?? "", "hash" => $instance["SealedHash"] ?? ""],
        ["file" => $instance["FrozenFile"] ?? "frozen.pdf", "hash" => $instance["FrozenHash"] ?? ""],
    ];
    foreach ($candidates as $candidate)
    {
        $name = basename((string)$candidate["file"]);
        if ($name == "" || !preg_match('/^[A-Za-z0-9_.-]+$/D', $name))
            continue ;
        $file = $directory.$name;
        if (!is_file($file))
            continue ;
        $expected = strtolower(trim((string)$candidate["hash"]));
        if ($expected != "" && hash_file("sha256", $file) !== $expected)
            return (new ErrorResponse("DocumentHashMismatch"));
        return (new ValueResponse($file));
    }
    return (new ErrorResponse("MissingFile", "document workflow PDF"));
}
