<?php

// Point d'entrée non listé pour les PDF du workflow documentaire.
// Les contrôles d'accès restent propres à chaque type de document :
// - signature : le jeton public identifie l'invitation ;
// - workflow : une session authentifiée et les droits école sont requis.

require_once ("tools/document_workflow.php");
require_once ("tools/registration_form.php");
require_once ("tools/document_print.php");

function document_pdf_not_found($message = "Document indisponible.", $status = 404)
{
    http_response_code($status);
    header("Content-Type: text/plain; charset=utf-8");
    header("Cache-Control: private, no-store, max-age=0");
    echo $message;
    exit ;
}

if ($Position == "DocumentSignaturePdf")
{
    $token = $_GET["token"] ?? "";
    $result = registration_form_document_signature_pdf($token);
    if (!$result["ok"])
        document_pdf_not_found();

    header("Content-Type: application/pdf");
    header("Content-Disposition: inline; filename=\"document-a-signer.pdf\"");
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: private, no-store, max-age=0");
    readfile($result["file"]);
    exit ;
}

if ($Position == "DocumentPrintPdf")
{
    if (!logged_in())
        document_pdf_not_found("Accès interdit.", 403);
    $id_task = (int)($_GET["task"] ?? 0);
    $task = document_task_row($id_task);
    if (!is_array($task) || !document_print_current_user_can_manage_task($task))
        document_pdf_not_found();
    $pdf = document_print_file_for_task($task);
    if ($pdf->is_error())
        document_pdf_not_found("Document indisponible ou empreinte invalide.", 409);
    $metadata = document_task_metadata($task);
    $filename = document_print_safe_filename($metadata["original_filename"] ?? "document.pdf");
    header("Content-Type: application/pdf");
    header("Content-Disposition: inline; filename=\"".$filename."\"");
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: private, no-store, max-age=0");
    readfile($pdf->value);
    exit ;
}

if ($Position == "DocumentWorkflowPdf")
{
    if (!logged_in())
        document_pdf_not_found("Accès interdit.", 403);

    $owner = (int)($_GET["owner"] ?? 0);
    $instance_id = trim((string)($_GET["instance"] ?? ""));
    if ($owner <= 0 || !preg_match('/^[A-Za-z0-9_-]+$/D', $instance_id))
        document_pdf_not_found("Paramètres invalides.", 400);

    $loaded = document_workflow_find_instance($owner, $instance_id);
    if ($loaded->is_error() || !document_workflow_can_view_instance($loaded->value["data"]))
        document_pdf_not_found();

    $pdf = document_workflow_pdf_file_for_view($loaded->value["data"], $loaded->value["directory"]);
    if ($pdf->is_error())
        document_pdf_not_found("Document indisponible ou empreinte invalide.", 409);

    header("Content-Type: application/pdf");
    header("Content-Disposition: inline; filename=\"document.pdf\"");
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: private, no-store, max-age=0");
    readfile($pdf->value);
    exit ;
}

document_pdf_not_found();
