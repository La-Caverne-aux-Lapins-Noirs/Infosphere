<?php
/*
** Run only document-workflow finalization maintenance from Configuration.
** This deliberately does not execute the complete Albedo pass: signature
** reminders and attendance-register synchronization keep their normal
** schedules. Completed documents are archived, delivered and queued for
** printing immediately so this operation completes the whole finalization.
*/

global $Configuration;

define("DOCUMENT_WORKFLOW_FORCE_SEAL_RETRY", true);
define("DOCUMENT_WORKFLOW_ALBEDO_NO_AUTORUN", true);
$albedo = 1;
require_once (__DIR__."/../documentation/albedo.php");

$seen = 0;
$signed = 0;
$pdfsign = 0;
$completed = 0;
$cleaned = 0;
$archived = 0;
$delivered = 0;
$queued = 0;
$errors = [];

foreach (db_select_all("id, codename FROM user WHERE authority != -1") as $document_user)
{
    $root = $Configuration->UsersDir($document_user["codename"]).document_workflow_root();
    foreach (glob($root."/*/instance.dab") ?: [] as $document_instance_file)
    {
        ++$seen;
        $loaded = document_workflow_load_instance(dirname($document_instance_file));
        if ($loaded->is_error())
            continue ;
        $before = $loaded->value["data"];
        $before_status = (string)($before["Status"] ?? "");
        $before_seal_error = (string)($before["SealError"] ?? "");

        // Also repair old instances on which previous Albedo versions left a
        // PdfSign error although this workflow never required PdfSign.
        if ($before_status === "Signed" || $before_status === "Completed")
        {
            $fallback = document_workflow_finalize_signed_without_pdfsign($document_instance_file);
            if ($fallback->is_error())
            {
                $errors[] = (string)($before["Id"] ?? basename(dirname($document_instance_file))).
                    ": ".strval($fallback);
                continue ;
            }
        }

        $loaded = document_workflow_load_instance(dirname($document_instance_file));
        if ($loaded->is_error())
            continue ;
        $state = $loaded->value["data"];

        if ($before_seal_error != "" && (string)($state["SealError"] ?? "") == "")
            ++$cleaned;
        if ($before_status === "Signed")
        {
            ++$signed;
            if ((string)($state["Status"] ?? "") === "Completed")
                ++$completed;
            else if (document_workflow_require_pdf_sign($state))
            {
                ++$pdfsign;
                document_workflow_albedo_seal_instance($document_instance_file);
                document_workflow_albedo_complete_sealed_instance($document_instance_file);

                $after = document_workflow_load_instance(dirname($document_instance_file));
                if ($after->is_error())
                    continue ;
                $state = $after->value["data"];
                if (($state["Status"] ?? "") == "Completed")
                    ++$completed;
                else if (($state["SealError"] ?? "") != "")
                    $errors[] = (string)($state["Id"] ?? basename(dirname($document_instance_file))).
                        ": ".(string)$state["SealError"];
                else if (($state["CompletionError"] ?? "") != "")
                    $errors[] = (string)($state["Id"] ?? basename(dirname($document_instance_file))).
                        ": ".(string)$state["CompletionError"];
            }
        }

        // A manual maintenance run is expected to finish the downstream work
        // immediately. These operations are idempotent: already archived,
        // delivered or queued documents are not duplicated.
        $loaded = document_workflow_load_instance(dirname($document_instance_file));
        if ($loaded->is_error() || ($loaded->value["data"]["Status"] ?? "") !== "Completed")
            continue ;

        $downstream_before = $loaded->value["data"];
        $archive = document_workflow_archive_completed_instance($document_instance_file);
        if ($archive->is_error())
            $errors[] = (string)($downstream_before["Id"] ?? basename(dirname($document_instance_file))).
                ": archive: ".strval($archive);

        $delivery = document_workflow_deliver_completed_instance($document_instance_file, false);
        if ($delivery->is_error())
            $errors[] = (string)($downstream_before["Id"] ?? basename(dirname($document_instance_file))).
                ": delivery: ".strval($delivery);

        $print = document_workflow_queue_completed_instance_for_print($document_instance_file);
        if ($print->is_error())
            $errors[] = (string)($downstream_before["Id"] ?? basename(dirname($document_instance_file))).
                ": print: ".strval($print);

        $downstream_after = document_workflow_load_instance(dirname($document_instance_file));
        if (!$downstream_after->is_error())
        {
            $after_state = $downstream_after->value["data"];
            if (empty($downstream_before["ArchivedAt"]) && !empty($after_state["ArchivedAt"]))
                ++$archived;
            if (empty($downstream_before["DeliveredAt"]) && !empty($after_state["DeliveredAt"]))
                ++$delivered;
            if (empty($downstream_before["PrintQueuedAt"]) && !empty($after_state["PrintQueuedAt"]))
                ++$queued;
        }
    }
}

echo "Document workflow finalization maintenance executed.\n";
echo "Instances inspected: $seen; signed instances processed: $signed; PdfSign retries: $pdfsign; completed now: $completed; stale PdfSign errors cleared: $cleaned.\n";
echo "Completed downstream now: archived: $archived; delivered by mail: $delivered; queued for print: $queued.\n";
if (count($errors))
    echo "Errors:\n- ".implode("\n- ", $errors)."\n";
