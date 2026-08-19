<?php

require_once (__DIR__."/document_signatures.php");
require_once (__DIR__."/generate_dabsic.php");
require_once (__DIR__."/document_tasks.php");

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

function document_workflow_create_frozen_instance($id_user, $model_reference, $model_file, $target_year, $signature_bindings, $pdf_content, array $task_plan = [], array $extra_metadata = [])
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
    // source context, display label). Core integrity/state fields above cannot
    // be overridden through this extension point.
    foreach (["display_name", "archive_targets", "source_context"] as $extra_key)
        if (array_key_exists($extra_key, $extra_metadata))
            $metadata[$extra_key] = $extra_metadata[$extra_key];

    $metadata_file = $directory."instance.dab";
    $generated = generate_dabsic($metadata, $metadata_file);
    if ($generated->is_error())
    {
        @unlink($frozen_pdf);
        return ($generated);
    }
    @chmod($metadata_file, 0640);

    if (count($obligation_plan))
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
        "metadata" => $metadata_file,
    ]));
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

function document_workflow_source_user_id($owner_user_id, $source, $created_by = 0)
{
    $owner_user_id = (int)$owner_user_id;
    $source = trim((string)$source);
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

function document_workflow_signature_file(array $instance, $directory, $role)
{
    if (!document_signature_slot_is_valid($role))
        return (NULL);
    return (rtrim((string)$directory, "/")."/signatures/".$role."/signature.png");
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

function document_workflow_required_model_roles($model_file)
{
    $metadata = dabsic_form_form_metadata($model_file);
    $out = [];
    foreach (($metadata["roles"] ?? []) as $role => $definition)
        if (!empty($definition["required"]))
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
    foreach (document_workflow_form_role_definitions($form) as $role => $definition)
    {
        if (empty($definition["required"]) || !document_task_role_is_valid($role))
            continue ;
        $assignee = $role === $recipient_role ? $id_user : 0;
        $key = document_task_key("form", $id_form, "fill", $role, $assignee);
        $created = document_task_create(
            $key,
            $id_user,
            $id_form,
            "",
            "fill",
            $role,
            (string)($definition["label"] ?? $role),
            $assignee,
            true,
            ["document_label" => (string)($document["label"] ?? "")]
        );
        if ($created->is_error())
            add_log(REPORT, "Cannot create document fill task $role for form $id_form: ".strval($created), $id_user);
    }

    // Old completed invitations predate document_task.  Their recipient role
    // has nevertheless been explicitly validated through the public form.
    if (($form["completed_at"] ?? NULL) !== NULL && $recipient_role != "")
        document_task_complete_form_role($id_form, $recipient_role, $id_user, $id_user);
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
    foreach (db_select_all("* FROM user_form
        WHERE id_user = $id_user AND kind LIKE 'DOC-%'
          AND completed_at IS NOT NULL AND revoked_at IS NULL
        ORDER BY completed_at DESC, id DESC") as $form)
    {
        $document = document_workflow_document_form_metadata($form);
        if (trim((string)($document["output"] ?? "")) !== $output_key
            || trim((string)($document["processed_at"] ?? "")) != "")
            continue ;
        return (document_workflow_form_task_progress($form)["pending"] ?? []);
    }
    return ([]);
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
    $user = db_select_one("first_name, family_name, mail FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL || trim((string)($user["mail"] ?? "")) == "")
        return (new ValueResponse(["sent" => false]));

    $document = document_workflow_document_form_metadata($form);
    $label = trim((string)($document["label"] ?? ""));
    if ($label == "")
        $label = document_reference_label((string)($document["reference"] ?? "Document"));
    $name = trim((string)($user["first_name"] ?? ""));
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
        $sent = send_mail((string)$user["mail"], $subject, $content);
    }
    catch (Throwable $e)
    {
        return (new ErrorResponse("CannotSendMail", $e->getMessage()));
    }
    if ($sent->is_error())
        return ($sent);
    add_log(CREATIVE_OPERATION, "document completion acknowledgement sent to user $id_user", $id_user);
    return (new ValueResponse(["sent" => true, "mail" => (string)$user["mail"]]));
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
    $progress = document_workflow_form_task_progress($form);
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

    if ($form["completed_at"] !== NULL)
    {
        $schema = document_workflow_document_form_schema($form);
        $document = isset($schema["document"]) && is_array($schema["document"])
            ? $schema["document"] : [];
        if (trim((string)($document["processed_at"] ?? "")) != ""
            || trim((string)($document["instance_id"] ?? "")) != "")
            return (new ErrorResponse("InvalidParameter", "finalized document form"));
        if ($form["revoked_at"] !== NULL)
            return (new ValueResponse(["expired" => true]));
        if (isset($schema["document"]) && is_array($schema["document"]))
        {
            $schema["document"]["expired_at"] = date("Y-m-d H:i:s");
            $schema["document"]["expired_by"] = document_workflow_actor_id();
            $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false)
                return (new ErrorResponse("CannotEdit"));
            $fields = $Database->real_escape_string($json);
            if (!$Database->query("UPDATE user_form
                SET fields = '$fields', revoked_at = NOW(), expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)
                WHERE id = $id_form AND revoked_at IS NULL"))
                return (new ErrorResponse("CannotEdit"));
        }
        else if (!$Database->query("UPDATE user_form
            SET revoked_at = NOW(), expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)
            WHERE id = $id_form AND revoked_at IS NULL"))
            return (new ErrorResponse("CannotEdit"));
        document_task_expire_form($id_form);
        return (new ValueResponse(["expired" => true]));
    }

    // Before completion, expiration invalidates the public link but keeps
    // revocation available for the distinct replacement/cancellation semantic.
    if (!$Database->query("UPDATE user_form
        SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)
        WHERE id = $id_form AND completed_at IS NULL"))
        return (new ErrorResponse("CannotEdit"));
    document_task_expire_form($id_form);
    return (new ValueResponse(["expired" => true]));
}

function document_workflow_send_signature_request($owner_user_id, $instance_id, $slot)
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
    add_log(TRACE, "Document workflow $instance_id signature request manually sent for slot $slot to user $signatory.", 1, true);
    return (new ValueResponse(["sent" => true, "slot" => $slot, "signatory_user_id" => $signatory]));
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
    if (($instance["Status"] ?? "") === "Expired")
        return (new ValueResponse(["expired" => true]));

    $instance["PreviousStatus"] = (string)($instance["Status"] ?? "");
    $instance["Status"] = "Expired";
    $instance["ExpiredAt"] = date("Y-m-d H:i:s");
    $instance["ExpiredBy"] = document_workflow_actor_id();
    $written = document_workflow_write_instance($loaded->value["file"], $instance);
    if ($written->is_error())
        return ($written);

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
