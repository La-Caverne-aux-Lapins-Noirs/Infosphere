<?php
if (!isset($albedo) || $albedo != 1)
    return ;

require_once ("tools/document_workflow.php");
require_once ("tools/registration_form.php");
require_once ("tools/pdfsign.php");
require_once ("tools/attendance_register.php");

function document_workflow_albedo_mail($invitation, $instance, $role_label, $reminder = false)
{
    $user = $invitation["user"] ?? [];
    $mail = trim((string)($user["mail"] ?? ""));
    if ($mail == "")
        return (new ErrorResponse("MissingField", "mail"));
    $model = document_workflow_instance_label($instance);
    $subject = ($reminder ? "Rappel - " : "")."Document à signer : ".$model;
    $content = "Bonjour".(trim((string)($user["first_name"] ?? "")) != "" ? " ".$user["first_name"] : "").",\n\n".
        "Un document attend votre signature en qualité de ".$role_label.".\n".
        "Le lien ci-dessous permet de consulter exactement le PDF concerné puis de le signer :\n\n".
        $invitation["url"]."\n\n".
        "Ce lien est personnel et valable 14 jours.\n";
    return (send_mail($mail, $subject, $content));
}

function document_workflow_albedo_invitation_retry_due(array $signature)
{
    $last_attempt = (int)($signature["LastInvitationAttemptTimestamp"] ?? 0);
    if ($last_attempt <= 0)
        return (true);
    return (time() - $last_attempt >= 60 * 60);
}

function document_workflow_albedo_process_instance($file)
{
    $loaded = document_workflow_load_instance(dirname($file));
    if ($loaded->is_error())
        return ;
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") != "AwaitingSignature")
        return ;
    $pdf = $loaded->value["directory"].($instance["FrozenFile"] ?? "frozen.pdf");
    if (!is_file($pdf) || hash_file("sha256", $pdf) !== (string)($instance["FrozenHash"] ?? ""))
    {
        $instance["Status"] = "Error";
        $instance["Error"] = "FrozenHashMismatch";
        document_workflow_write_instance($loaded->value["file"], $instance);
        add_log(TRACE, "Document workflow ".($instance["Id"] ?? "?")." frozen hash mismatch.", 1, true);
        return ;
    }

    $changed = false;
    if (!isset($instance["Signatures"]) || !is_array($instance["Signatures"]))
        return ;

    // Iterate the persisted array itself.  Iterating ($instance["Signatures"] ?? [])
    // by reference only mutates a temporary copy; invitation timestamps were then
    // lost and Albedo created a fresh token/mail on every pass.
    foreach ($instance["Signatures"] as $slot => &$signature)
    {
        if (!is_array($signature) || ($signature["Status"] ?? "Pending") == "Signed")
            continue ;
        if (document_workflow_signature_is_external_campaign($signature))
            continue ;
        $role_label = document_workflow_signature_role_label($slot, $signature);
        $signatory = document_workflow_signature_assignee_user_id(
            (int)($instance["OwnerUserId"] ?? 0),
            $signature,
            (int)($instance["CreatedBy"] ?? 0)
        );
        if ($signatory <= 0)
        {
            $signature["Error"] = "CannotResolveSignatory";
            $changed = true;
            continue ;
        }
        $last = $signature["LastInvitationAt"] ?? ($signature["InvitedAt"] ?? "");
        $last_ts = $last != "" ? strtotime($last) : false;
        $reminder = $last_ts !== false && time() - $last_ts >= 3 * 24 * 3600;
        if ($last != "" && !$reminder)
            continue ;
        if (!document_workflow_albedo_invitation_retry_due($signature))
            continue ;

        $attempt_timestamp = time();
        $signature["LastInvitationAttemptTimestamp"] = $attempt_timestamp;
        $signature["InvitationAttemptCount"] = (int)($signature["InvitationAttemptCount"] ?? 0) + 1;
        $changed = true;

        $invitation = registration_form_create_document_signature_invitation(
            (int)$instance["OwnerUserId"], (string)$instance["Id"], $slot, $signatory, 1
        );
        if (!$invitation["ok"])
        {
            $signature["Error"] = $invitation["error"] ?? "CannotCreateInvitation";
            $changed = true;
            continue ;
        }
        try
        {
            $sent = document_workflow_albedo_mail($invitation, $instance, $role_label, $reminder);
        }
        catch (Throwable $e)
        {
            $sent = new ErrorResponse("CannotSendMail", $e->getMessage());
        }
        if ($sent->is_error())
        {
            registration_form_revoke_token($invitation["token"]);
            $signature["Error"] = "CannotSendMail";
            $changed = true;
            continue ;
        }
        unset($signature["Error"]);
        $now = date("Y-m-d H:i:s");
        if (empty($signature["InvitedAt"]))
            $signature["InvitedAt"] = $now;
        else
            $signature["ReminderCount"] = (int)($signature["ReminderCount"] ?? 0) + 1;
        $signature["LastInvitationAt"] = $now;
        $signature["InvitationId"] = (int)$invitation["id"];
        $signature["SignatoryUserId"] = (int)$signatory;
        $changed = true;
        add_log(EDITING_OPERATION, document_workflow_signature_mail_log_message(
            $instance, $slot, $signature, $invitation["user"] ?? [], $signatory, $reminder, "automatic"
        ), 1, false);
    }
    unset($signature);
    if ($changed)
        document_workflow_write_instance($loaded->value["file"], $instance);
}



function document_workflow_albedo_lock($directory)
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

function document_workflow_albedo_unlock($handle)
{
    if (is_resource($handle))
    {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function document_workflow_albedo_seal_retry_due(array $instance)
{
    if (defined("DOCUMENT_WORKFLOW_FORCE_SEAL_RETRY") && DOCUMENT_WORKFLOW_FORCE_SEAL_RETRY)
        return (true);
    $last = trim((string)($instance["SealLastAttemptAt"] ?? ""));
    if ($last == "")
        return (true);
    $time = strtotime($last);
    return ($time === false || time() - $time >= 3600);
}

function document_workflow_albedo_instance_school(array $instance)
{
    $school = trim((string)($instance["SchoolCodename"] ?? ""));
    if ($school != "")
        return ($school);
    return (document_workflow_school_codename_for_user((int)($instance["OwnerUserId"] ?? 0)));
}

function document_workflow_albedo_seal_instance($file)
{
    $loaded = document_workflow_load_instance(dirname($file));
    if ($loaded->is_error())
        return ;
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") != "Signed")
        return ;

    // PdfSign is explicitly opt-in.  Never let the sealing worker turn a
    // perfectly valid Infosphere-signed document into a PdfSign configuration
    // error merely because the school has no pdfsign.dab.
    if (!document_workflow_require_pdf_sign($instance))
        return ;

    if (!document_workflow_albedo_seal_retry_due($instance))
        return ;

    $lock = document_workflow_albedo_lock($loaded->value["directory"]);
    if ($lock === NULL)
        return ;
    try
    {
        $loaded = document_workflow_load_instance($loaded->value["directory"]);
        if ($loaded->is_error())
            return ;
        $instance = $loaded->value["data"];
        if (($instance["Status"] ?? "") != "Signed")
            return ;

        $directory = $loaded->value["directory"];
        $frozen = $directory.($instance["FrozenFile"] ?? "frozen.pdf");
        if (!is_file($frozen) || hash_file("sha256", $frozen) !== (string)($instance["FrozenHash"] ?? ""))
        {
            $instance["Status"] = "Error";
            $instance["Error"] = "FrozenHashMismatch";
            $instance["ErrorAt"] = date("Y-m-d H:i:s");
            document_workflow_write_instance($loaded->value["file"], $instance);
            add_log(TRACE, "Document workflow ".($instance["Id"] ?? "?")." cannot be sealed: frozen hash mismatch.", 1, true);
            return ;
        }

        $instance["SealLastAttemptAt"] = date("Y-m-d H:i:s");
        $instance["SealAttemptCount"] = (int)($instance["SealAttemptCount"] ?? 0) + 1;
        document_workflow_write_instance($loaded->value["file"], $instance);

        $tmp = $directory.".sealed.".getmypid().".".bin2hex(random_bytes(4)).".pdf";
        $tmp_timestamp = $tmp.".tsr";
        @unlink($tmp);
        @unlink($tmp_timestamp);
        $school_codename = document_workflow_albedo_instance_school($instance);
        if ($school_codename === NULL)
        {
            $instance["SealError"] = "CannotResolveSchool";
            document_workflow_write_instance($loaded->value["file"], $instance);
            return ;
        }
        $seal_source = $frozen;
        if (!empty($instance["FrozenDabsicFile"]))
        {
            $rendered = document_workflow_render_signed_pdf($instance, $directory);
            if ($rendered->is_error())
            {
                $instance["SealError"] = "SignedRenderFailed";
                $instance["SealErrorDetail"] = substr(strval($rendered), 0, 4000);
                document_workflow_write_instance($loaded->value["file"], $instance);
                return ;
            }
            $seal_source = $directory.(string)$rendered->value["file"];
        }
        $sealed = pdfsign_seal($school_codename, $seal_source, $tmp, $tmp_timestamp);
        if (!$sealed["ok"])
        {
            @unlink($tmp);
            @unlink($tmp_timestamp);
            $instance["SealError"] = (string)($sealed["error"] ?? "PdfSignSealFailed");
            if (!empty($sealed["detail"]))
                $instance["SealErrorDetail"] = substr((string)$sealed["detail"], 0, 4000);
            else
                unset($instance["SealErrorDetail"]);
            document_workflow_write_instance($loaded->value["file"], $instance);
            add_log(TRACE, "Document workflow ".$instance["Id"]." seal failed: ".$instance["SealError"].".", 1, true);
            return ;
        }

        $final = $directory."sealed.pdf";
        if (!@rename($tmp, $final))
        {
            @unlink($tmp);
            @unlink($tmp_timestamp);
            $instance["SealError"] = "CannotStoreSealedPdf";
            document_workflow_write_instance($loaded->value["file"], $instance);
            return ;
        }
        @chmod($final, 0640);

        $timestamp_file = NULL;
        if (!empty($sealed["timestamp_file"]) && is_file($tmp_timestamp))
        {
            $timestamp_file = $directory."sealed.pdf.tsr";
            if (!@rename($tmp_timestamp, $timestamp_file))
            {
                @unlink($final);
                @unlink($tmp_timestamp);
                $instance["SealError"] = "CannotStoreTimestamp";
                document_workflow_write_instance($loaded->value["file"], $instance);
                return ;
            }
            @chmod($timestamp_file, 0640);
        }
        else
            @unlink($tmp_timestamp);

        unset($instance["SealError"], $instance["SealErrorDetail"], $instance["Error"], $instance["ErrorAt"]);
        $instance["Status"] = "Sealed";
        $instance["SealedAt"] = date("Y-m-d H:i:s");
        $instance["SealedFile"] = "sealed.pdf";
        $instance["SealedHash"] = (string)$sealed["output_hash"];
        $instance["SealInputHash"] = (string)$sealed["input_hash"];
        $instance["SealSignatureCount"] = (int)$sealed["signatures"];
        $instance["SealCertificatesTrusted"] = !empty($sealed["certificates_trusted"]) ? 1 : 0;
        if ($timestamp_file !== NULL)
        {
            $instance["TimestampFile"] = "sealed.pdf.tsr";
            $instance["TimestampHash"] = (string)$sealed["timestamp_hash"];
        }
        else
        {
            unset($instance["TimestampFile"], $instance["TimestampHash"]);
        }
        document_workflow_write_instance($loaded->value["file"], $instance);
        add_log(TRACE, "Document workflow ".$instance["Id"]." sealed by PdfSign.", 1, true);
    }
    catch (Throwable $e)
    {
        add_log(TRACE, "Document workflow sealing exception: ".$e->getMessage(), 1, true);
    }
    finally
    {
        document_workflow_albedo_unlock($lock);
    }
}

function document_workflow_albedo_complete_sealed_instance($file)
{
    $loaded = document_workflow_load_instance(dirname($file));
    if ($loaded->is_error())
        return ;
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") != "Sealed")
        return ;

    $lock = document_workflow_albedo_lock($loaded->value["directory"]);
    if ($lock === NULL)
        return ;
    try
    {
        $loaded = document_workflow_load_instance($loaded->value["directory"]);
        if ($loaded->is_error())
            return ;
        $instance = $loaded->value["data"];
        if (($instance["Status"] ?? "") != "Sealed")
            return ;
        $directory = $loaded->value["directory"];
        $sealed = $directory.($instance["SealedFile"] ?? "sealed.pdf");
        if (!is_file($sealed) || hash_file("sha256", $sealed) !== (string)($instance["SealedHash"] ?? ""))
        {
            $instance["Status"] = "Error";
            $instance["Error"] = "SealedHashMismatch";
            $instance["ErrorAt"] = date("Y-m-d H:i:s");
            document_workflow_write_instance($loaded->value["file"], $instance);
            return ;
        }
        $timestamp = NULL;
        if (!empty($instance["TimestampFile"]))
        {
            $timestamp = $directory.$instance["TimestampFile"];
            if (!is_file($timestamp) || hash_file("sha256", $timestamp) !== (string)($instance["TimestampHash"] ?? ""))
            {
                $instance["Status"] = "Error";
                $instance["Error"] = "TimestampHashMismatch";
                $instance["ErrorAt"] = date("Y-m-d H:i:s");
                document_workflow_write_instance($loaded->value["file"], $instance);
                return ;
            }
        }
        $school_codename = document_workflow_albedo_instance_school($instance);
        if ($school_codename === NULL)
        {
            $instance["CompletionError"] = "CannotResolveSchool";
            document_workflow_write_instance($loaded->value["file"], $instance);
            return ;
        }
        $verified = pdfsign_verify($school_codename, $sealed, $timestamp);
        if (!$verified["ok"])
        {
            $instance["CompletionError"] = (string)($verified["error"] ?? "PdfSignVerifyFailed");
            if (!empty($verified["detail"]))
                $instance["CompletionErrorDetail"] = substr((string)$verified["detail"], 0, 4000);
            document_workflow_write_instance($loaded->value["file"], $instance);
            return ;
        }
        unset($instance["CompletionError"], $instance["CompletionErrorDetail"]);
        $instance["Status"] = "Completed";
        $instance["CompletedAt"] = date("Y-m-d H:i:s");
        $instance["FinalFile"] = (string)($instance["SealedFile"] ?? "sealed.pdf");
        $instance["FinalHash"] = (string)$instance["SealedHash"];
        document_workflow_write_instance($loaded->value["file"], $instance);
        add_log(TRACE, "Document workflow ".$instance["Id"]." completed after PdfSign verification.", 1, true);
    }
    finally
    {
        document_workflow_albedo_unlock($lock);
    }
}


function document_workflow_albedo_recover_unsigned_completed_instance($file)
{
    $loaded = document_workflow_load_instance(dirname((string)$file));
    if ($loaded->is_error())
        return ;
    $instance = $loaded->value["data"];
    if (count($instance["Signatures"] ?? []))
        return ;

    $status = (string)($instance["Status"] ?? "");
    $error = (string)($instance["Error"] ?? "");
    if ($status !== "Completed" && !($status === "Error" && $error === "FinalHashMismatch"))
        return ;

    $directory = $loaded->value["directory"];
    $frozen_name = basename((string)($instance["FrozenFile"] ?? "frozen.pdf"));
    $frozen = $directory.$frozen_name;
    $hash = trim((string)($instance["FrozenHash"] ?? ""));
    if (!is_file($frozen) || $hash == "" || hash_file("sha256", $frozen) !== $hash)
        return ;

    $instance["Status"] = "Completed";
    if (empty($instance["CompletedAt"]))
        $instance["CompletedAt"] = (string)($instance["CreatedAt"] ?? date("Y-m-d H:i:s"));
    $instance["FinalFile"] = $frozen_name;
    $instance["FinalHash"] = $hash;
    $instance["CompletionMode"] = "NoSignature";
    unset($instance["Error"], $instance["ErrorAt"]);
    document_workflow_write_instance($loaded->value["file"], $instance);
}

function document_workflow_albedo_delivery_retry_due(array $instance)
{
    $last = trim((string)($instance["DeliveryLastAttemptAt"] ?? ""));
    if ($last == "")
        return (true);
    $time = strtotime($last);
    return ($time === false || time() - $time >= 3 * 3600);
}

function document_workflow_albedo_delivery_recipients(array $instance)
{
    $ids = [];
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

function document_workflow_albedo_delivery_filename(array $instance)
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

function document_workflow_albedo_deliver_completed_instance($file)
{
    return (document_workflow_deliver_completed_instance($file, true));
}


function document_workflow_albedo_finalize_ready_workspaces($id_user, $codename)
{
    global $Configuration;

    $id_user = (int)$id_user;
    $directory = $Configuration->UsersDir($codename)."admin/documentation/forms/";
    foreach (glob($directory.".workspace-*.json") ?: [] as $workspace_file)
    {
        $raw = @file_get_contents($workspace_file);
        $workspace = $raw !== false ? json_decode($raw, true) : NULL;
        if (!is_array($workspace) || empty($workspace["released_at"]) || empty($workspace["auto_finalize"]))
            continue ;
        $model_hash = strtolower(trim((string)($workspace["model_hash"] ?? "")));
        $target_year = (int)($workspace["target_year"] ?? -1);
        $reference = trim((string)($workspace["reference"] ?? ""));
        $source_reference = trim((string)($workspace["source_reference"] ?? ""));
        if (!preg_match('/^[a-f0-9]{32}$/D', $model_hash) || $target_year < 0 || $target_year > 5
            || $reference == "" || $source_reference == "")
            continue ;
        $output_key = "user-document:".$id_user.":".$model_hash.":".$target_year;
        if (document_workflow_processed_instance_for_output($id_user, $output_key) != "")
            continue ;
        $resolved = dabsic_editor_resolve_file($reference, false);
        if (!$resolved["ok"])
        {
            add_log(REPORT, "Cannot resolve released document workspace $workspace_file", $id_user, true);
            continue ;
        }
        $metadata = dabsic_form_form_metadata($resolved["absolute"]);
        $semantic = document_workflow_workspace_semantic_bindings($workspace);
        if (!document_workflow_workspace_required_roles_complete(
            $id_user, $output_key, $metadata["roles"] ?? [],
            (int)($workspace["created_by"] ?? 0), $semantic
        ))
            continue ;

        require_once (__DIR__."/../../api/doc.php");
        $key = "albedo_".$model_hash;
        $request = _GenerateDoc(0, [
            "doc_".$key => 1,
            "docref_".$key => $source_reference,
            "form_output" => $output_key,
            "save_user_document" => $id_user,
            "finalize_document" => 1,
            "target_year" => $target_year,
            "context_bindings" => document_context_bindings_json($workspace["context_bindings"] ?? []),
        ], "POST", NULL, NULL);
        if ($request instanceof ErrorResponse)
            add_log(REPORT, "Automatic document finalization failed for $output_key: ".strval($request), $id_user, true);
        else
            add_log(EDITING_OPERATION, "Document workspace finalized automatically after all contributions: $output_key", $id_user, false);
    }
}

if (!defined("DOCUMENT_WORKFLOW_ALBEDO_NO_AUTORUN") || !DOCUMENT_WORKFLOW_ALBEDO_NO_AUTORUN)
{
    foreach (db_select_all("id, codename FROM user WHERE authority != -1") as $document_user)
    {
        document_workflow_albedo_finalize_ready_workspaces((int)$document_user["id"], (string)$document_user["codename"]);
        $root = $Configuration->UsersDir($document_user["codename"]).document_workflow_root();
        foreach (glob($root."/*/instance.dab") ?: [] as $document_instance_file)
        {
            document_workflow_albedo_recover_unsigned_completed_instance($document_instance_file);
            document_workflow_albedo_process_instance($document_instance_file);
            $fallback = document_workflow_finalize_signed_without_pdfsign($document_instance_file);
            if ($fallback->is_error())
                add_log(REPORT, "Cannot complete signed workflow without PdfSign ".$document_instance_file.": ".strval($fallback), 1, true);
            document_workflow_albedo_seal_instance($document_instance_file);
            document_workflow_albedo_complete_sealed_instance($document_instance_file);
            $sync = attendance_register_sync_campaign_instance($document_instance_file);
            if ($sync->is_error())
                add_log(REPORT, "Cannot synchronize attendance-register campaign ".$document_instance_file.": ".strval($sync), 1, true);
            $archive = document_workflow_archive_completed_instance($document_instance_file);
            if ($archive->is_error())
                add_log(REPORT, "Cannot archive completed document workflow ".$document_instance_file.": ".strval($archive), 1, true);
            document_workflow_albedo_deliver_completed_instance($document_instance_file);
            $delivery_status = document_workflow_refresh_delivery_status($document_instance_file, true);
            if ($delivery_status->is_error())
                add_log(REPORT, "Cannot refresh Mailgun delivery status for ".$document_instance_file.": ".strval($delivery_status), 1, true);
            $print = document_workflow_queue_completed_instance_for_print($document_instance_file);
            if ($print->is_error())
                add_log(REPORT, "Cannot queue completed document workflow for print ".$document_instance_file.": ".strval($print), 1, true);
        }
    }
}
