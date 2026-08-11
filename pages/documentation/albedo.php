<?php
if (!isset($albedo) || $albedo != 1)
    return ;

require_once ("tools/document_workflow.php");
require_once ("tools/registration_form.php");
require_once ("tools/pdfsign.php");

function document_workflow_albedo_mail($invitation, $instance, $role, $reminder = false)
{
    $user = $invitation["user"] ?? [];
    $mail = trim((string)($user["mail"] ?? ""));
    if ($mail == "")
        return (new ErrorResponse("MissingField", "mail"));
    $model = basename((string)($instance["Model"] ?? "document"));
    $subject = ($reminder ? "Rappel - " : "")."Document à signer : ".$model;
    $content = "Bonjour".(trim((string)($user["first_name"] ?? "")) != "" ? " ".$user["first_name"] : "").",\n\n".
        "Un document attend votre signature en qualité de ".$role.".\n".
        "Le lien ci-dessous permet de consulter exactement le PDF concerné puis de le signer :\n\n".
        $invitation["url"]."\n\n".
        "Ce lien est personnel et valable 14 jours.\n";
    return (send_mail($mail, $subject, $content));
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
    foreach (($instance["Signatures"] ?? []) as $role => &$signature)
    {
        if (!is_array($signature) || ($signature["Status"] ?? "Pending") == "Signed")
            continue ;
        $source = (string)($signature["Source"] ?? "");
        $signatory = document_workflow_source_user_id((int)($instance["OwnerUserId"] ?? 0), $source, (int)($instance["CreatedBy"] ?? 0));
        if ($signatory == NULL)
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

        $invitation = registration_form_create_document_signature_invitation(
            (int)$instance["OwnerUserId"], (string)$instance["Id"], $role, $signatory, 1
        );
        if (!$invitation["ok"])
        {
            $signature["Error"] = $invitation["error"] ?? "CannotCreateInvitation";
            $changed = true;
            continue ;
        }
        try
        {
            $sent = document_workflow_albedo_mail($invitation, $instance, $role, $reminder);
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
        add_log(TRACE, "Document workflow ".$instance["Id"]." signature request sent for $role to user $signatory.", 1, true);
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
        $sealed = pdfsign_seal($school_codename, $frozen, $tmp, $tmp_timestamp);
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
        if (!is_array($signature))
            continue ;
        $id = (int)($signature["SignatoryUserId"] ?? 0);
        if ($id > 0)
            $ids[$id] = true;
    }
    return (array_keys($ids));
}

function document_workflow_albedo_delivery_filename(array $instance)
{
    $model = basename((string)($instance["Model"] ?? "document.dab"));
    $model = preg_replace('/\.dab$/i', '', $model);
    $model = preg_replace('/[^A-Za-z0-9._-]+/', '_', $model);
    if ($model == "")
        $model = "document";
    return ($model."-".(string)($instance["Id"] ?? "final").".pdf");
}

function document_workflow_albedo_deliver_completed_instance($file)
{
    $loaded = document_workflow_load_instance(dirname($file));
    if ($loaded->is_error())
        return ;
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") != "Completed" || !empty($instance["DeliveredAt"]))
        return ;
    if (!document_workflow_albedo_delivery_retry_due($instance))
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
        if (($instance["Status"] ?? "") != "Completed" || !empty($instance["DeliveredAt"]))
            return ;

        $directory = $loaded->value["directory"];
        $final = $directory.($instance["FinalFile"] ?? ($instance["SealedFile"] ?? "sealed.pdf"));
        $expected = (string)($instance["FinalHash"] ?? ($instance["SealedHash"] ?? ""));
        if (!is_file($final) || $expected == "" || hash_file("sha256", $final) !== $expected)
        {
            $instance["Status"] = "Error";
            $instance["Error"] = "FinalHashMismatch";
            $instance["ErrorAt"] = date("Y-m-d H:i:s");
            document_workflow_write_instance($loaded->value["file"], $instance);
            return ;
        }

        $instance["DeliveryLastAttemptAt"] = date("Y-m-d H:i:s");
        $instance["DeliveryAttemptCount"] = (int)($instance["DeliveryAttemptCount"] ?? 0) + 1;
        $filename = document_workflow_albedo_delivery_filename($instance);
        $attachments = [$filename => file_get_contents($final)];
        if (!empty($instance["TimestampFile"]))
        {
            $timestamp = $directory.$instance["TimestampFile"];
            $timestamp_hash = (string)($instance["TimestampHash"] ?? "");
            if (!is_file($timestamp) || $timestamp_hash == "" || hash_file("sha256", $timestamp) !== $timestamp_hash)
            {
                $instance["Status"] = "Error";
                $instance["Error"] = "TimestampHashMismatch";
                $instance["ErrorAt"] = date("Y-m-d H:i:s");
                document_workflow_write_instance($loaded->value["file"], $instance);
                return ;
            }
            $attachments[$filename.".tsr"] = file_get_contents($timestamp);
        }

        $recipients = document_workflow_albedo_delivery_recipients($instance);
        $delivered = [];
        foreach (($instance["DeliveredUsers"] ?? []) as $id_user)
        {
            $id_user = (int)$id_user;
            if ($id_user > 0)
                $delivered[$id_user] = true;
        }
        $failed = [];
        foreach ($recipients as $id_user)
        {
            $id_user = (int)$id_user;
            if (isset($delivered[$id_user]))
                continue ;
            $user = db_select_one("id, first_name, family_name, mail FROM user WHERE id = ".(int)$id_user." AND authority != -1");
            $mail = trim((string)($user["mail"] ?? ""));
            if ($user == NULL || $mail == "")
            {
                $failed[] = (int)$id_user;
                continue ;
            }
            $subject = "Document finalisé : ".basename((string)($instance["Model"] ?? "document"));
            $content = "Bonjour".(trim((string)($user["first_name"] ?? "")) != "" ? " ".$user["first_name"] : "").",\n\n".
                "Le document est désormais finalisé. Vous trouverez en pièce jointe la version définitive qui a été conservée par Infosphere.\n\n".
                "Référence : ".(string)($instance["Id"] ?? "")."\n".
                "Empreinte SHA-256 : ".$expected."\n";
            try
            {
                $sent = send_mail($mail, $subject, $content, NULL, $attachments, true);
            }
            catch (Throwable $e)
            {
                $sent = new ErrorResponse("CannotSendMail", $e->getMessage());
            }
            if ($sent->is_error())
                $failed[] = (int)$id_user;
            else
                $delivered[$id_user] = true;
        }

        $instance["DeliveredUsers"] = array_keys($delivered);
        if (count($failed))
        {
            $instance["DeliveryPendingUsers"] = $failed;
            $instance["DeliveryError"] = "CannotDeliverToAllRecipients";
        }
        else
        {
            unset($instance["DeliveryPendingUsers"], $instance["DeliveryError"]);
            $instance["DeliveredAt"] = date("Y-m-d H:i:s");
        }
        document_workflow_write_instance($loaded->value["file"], $instance);
        if (!count($failed))
            add_log(TRACE, "Document workflow ".$instance["Id"]." final PDF delivered to recipients.", 1, true);
    }
    finally
    {
        document_workflow_albedo_unlock($lock);
    }
}

foreach (db_select_all("id, codename FROM user WHERE authority != -1") as $document_user)
{
    $root = $Configuration->UsersDir($document_user["codename"]).document_workflow_root();
    foreach (glob($root."/*/instance.dab") ?: [] as $document_instance_file)
    {
        document_workflow_albedo_process_instance($document_instance_file);
        document_workflow_albedo_seal_instance($document_instance_file);
        document_workflow_albedo_complete_sealed_instance($document_instance_file);
        document_workflow_albedo_deliver_completed_instance($document_instance_file);
    }
}
