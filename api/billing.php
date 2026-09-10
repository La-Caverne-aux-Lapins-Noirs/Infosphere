<?php

require_once ("tools/document_print.php");

function BillingRequireManagedUser($data)
{
    if (!isset($data["id_user"]) || !is_number($data["id_user"]))
        bad_request();
    $id_user = (int)$data["id_user"];
    if (!billing_user_is_managed($id_user))
        forbidden();
    return ($id_user);
}

function BillingPositiveAmount($data, $field = "amount")
{
    if (!isset($data[$field]))
        bad_request();
    $amount = billing_amount_to_cents($data[$field]);
    if ($amount === NULL || $amount <= 0)
        bad_request();
    return ($amount);
}

function BillingExternalInvoicePdfContent($data)
{
    $content = NULL;

    if (isset($_FILES["invoice_pdf"]))
    {
        $upload = $_FILES["invoice_pdf"];
        if (is_array($upload["error"] ?? NULL))
            return (["error" => "InvalidBillingInvoicePdf"]);
        $error = $upload["error"] ?? UPLOAD_ERR_NO_FILE;
        if ($error == UPLOAD_ERR_NO_FILE)
            return (["content" => NULL]);
        if ($error != UPLOAD_ERR_OK || empty($upload["tmp_name"]))
            return (["error" => "InvalidBillingInvoicePdf"]);
        if ((int)($upload["size"] ?? 0) > 20 * 1024 * 1024)
            return (["error" => "BillingInvoicePdfTooLarge"]);
        $content = @file_get_contents($upload["tmp_name"]);
    }
    else if (!empty($data["invoice_pdf"]) && is_array($data["invoice_pdf"]))
    {
        $file = $data["invoice_pdf"][0] ?? $data["invoice_pdf"];
        if (is_array($file) && isset($file["content"]))
            $content = base64_decode((string)$file["content"], true);
    }

    if ($content === NULL || $content === "")
        return (["content" => NULL]);
    if ($content === false)
        return (["error" => "InvalidBillingInvoicePdf"]);
    if (strlen($content) > 20 * 1024 * 1024)
        return (["error" => "BillingInvoicePdfTooLarge"]);
    if (strlen($content) < 5 || substr($content, 0, 5) !== "%PDF-")
        return (["error" => "InvalidBillingInvoicePdf"]);
    return (["content" => $content]);
}

function AddExternalBillingInvoice($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $User;

    $id_user = BillingRequireManagedUser($data);
    $amount = BillingPositiveAmount($data);
    $reference = trim((string)($data["invoice_reference"] ?? ""));
    if ($reference == "")
        return (new ErrorResponse("BillingInvoiceReferenceRequired"));
    if (strlen($reference) > 128)
        return (new ErrorResponse("BillingInvoiceReferenceTooLong"));
    if (!billing_invoice_reference_is_available($reference, 0))
        return (new ErrorResponse("BillingInvoiceReferenceAlreadyUsed"));

    $pdf = BillingExternalInvoicePdfContent($data);
    if (isset($pdf["error"]))
        return (new ErrorResponse($pdf["error"]));

    $school = billing_user_main_school($id_user);
    if ($school == NULL)
        return (new ErrorResponse("NotFound"));

    $invoice_date = db_form_date($data["invoice_date"] ?? dbnow());
    if (date_to_timestamp($invoice_date) === NULL)
        bad_request();
    $due_input = trim((string)($data["due_date"] ?? ""));
    $due_date = db_form_date($due_input == "" ? $invoice_date : $due_input);
    if (date_to_timestamp($due_date) === NULL)
        bad_request();

    $label = trim((string)($data["label"] ?? ""));
    if ($label == "")
        $label = ($Dictionnary["BillingExternalInvoice"] ?? "Facture externe")." ".$reference;
    $invoice_type = billing_normalize_invoice_type($data["invoice_type"] ?? "school");
    $register_payment = !empty($data["register_payment"]);
    $payment_date = db_form_date($data["payment_date"] ?? $invoice_date);
    if ($register_payment && date_to_timestamp($payment_date) === NULL)
        bad_request();

    $eref = $Database->real_escape_string($reference);
    $elabel = $Database->real_escape_string($label);
    $etype = $Database->real_escape_string($invoice_type);
    $einvoice_date = $Database->real_escape_string($invoice_date);
    $edue_date = $Database->real_escape_string($due_date);
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    $stored_entry = NULL;

    $Database->begin_transaction();
    if ($Database->query("
        INSERT INTO billing_entry
        (id_user, id_school, id_template, label, amount, entry_type, invoice_type, due_date, invoice_reference, sent_date, id_actor)
        VALUES
        ($id_user, {$school["id_school"]}, NULL, '$elabel', $amount, 'external_invoice', '$etype', '$edue_date', '$eref', '$einvoice_date', $actor)
    ") === false)
    {
        $Database->rollback();
        return (new ErrorResponse("BillingInvoiceReferenceAlreadyUsed"));
    }

    $id_entry = (int)$Database->insert_id;
    $stored_entry = billing_entry_with_user($id_entry);
    if ($stored_entry == NULL)
    {
        $Database->rollback();
        return (new ErrorResponse("CannotRegister"));
    }

    if ($pdf["content"] !== NULL)
    {
        $filename = billing_store_external_invoice_pdf($stored_entry, $pdf["content"]);
        if ($filename === false || db_update_one("billing_entry", $id_entry, ["invoice_filename" => $filename]) === NULL)
        {
            billing_unlink_invoice_files($stored_entry);
            $Database->rollback();
            return (new ErrorResponse("CannotRegister"));
        }
    }

    if ($register_payment)
    {
        $epayment_date = $Database->real_escape_string($payment_date);
        $transfer_reference = $Database->real_escape_string(trim((string)($data["transfer_reference"] ?? "")));
        $comment = $Database->real_escape_string(
            ($Dictionnary["BillingExternalInvoicePaymentComment"] ?? "Règlement facture externe")." ".$reference
        );
        if ($Database->query("
            INSERT INTO billing_payment
            (id_user, id_school, amount, payment_date, transfer_reference, comment, id_actor)
            VALUES
            ($id_user, {$school["id_school"]}, $amount, '$epayment_date', '$transfer_reference', '$comment', $actor)
        ") === false)
        {
            billing_unlink_invoice_files($stored_entry);
            $Database->rollback();
            return (new ErrorResponse("CannotRegister"));
        }
    }

    if (!$Database->commit())
    {
        billing_unlink_invoice_files($stored_entry);
        $Database->rollback();
        return (new ErrorResponse("CannotRegister"));
    }

    billing_archive_paid_invoices($id_user);
    return (new ValueResponse(["msg" => $Dictionnary["BillingExternalInvoiceImported"] ?? $Dictionnary["Added"]]));
}

function SetBillingUserVisibility($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    $id_user = (int)$id;
    if ($id_user <= 0 || !billing_user_is_managed($id_user))
        forbidden();
    $hidden = !empty($data["hidden"]) ? 1 : 0;

    if ($Database->query("UPDATE user SET billing_hidden = $hidden WHERE id = $id_user") === false)
        return (new ErrorResponse("CannotRegister"));

    return (new ValueResponse([
        "msg" => $hidden
            ? ($Dictionnary["BillingUserHidden"] ?? $Dictionnary["Modified"])
            : ($Dictionnary["BillingUserShown"] ?? $Dictionnary["Modified"])
    ]));
}

function AddBillingEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_user = BillingRequireManagedUser($data);
    $amount = BillingPositiveAmount($data);
    if (!isset($data["label"]) || trim($data["label"]) == "")
        bad_request();
    $due_date = $data["due_date"] ?? dbnow();
    $invoice_type = billing_normalize_invoice_type($data["invoice_type"] ?? "school");
    if (!billing_add_entry($id_user, $data["label"], $amount, $due_date, NULL, "tuition", $invoice_type))
        return (new ErrorResponse("CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["Added"]]));
}

function ApplyBillingTemplate($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_user = BillingRequireManagedUser($data);
    if (!isset($data["id_template"]) || !is_number($data["id_template"]))
        bad_request();
    if (!isset($data["schedule_type"]) || !count(billing_schedule_month_offsets($data["schedule_type"])))
        bad_request();
    $first_due_date = $data["first_due_date"] ?? dbnow();
    $count = billing_apply_template($id_user, $data["id_template"], $first_due_date, $data["schedule_type"]);
    if ($count == 0)
        return (new ErrorResponse("CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["Added"]]));
}


function AddBillingCreditNote($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_user = BillingRequireManagedUser($data);
    $amount = BillingPositiveAmount($data);
    if (!isset($data["related_entry_id"]) || !is_number($data["related_entry_id"]))
        bad_request();
    $related_entry_id = (int)$data["related_entry_id"];
    $related = billing_entry_with_user($related_entry_id);
    if ($related == NULL || (int)$related["id_user"] != $id_user || empty($related["sent_date"]) ||
        billing_is_credit_note($related) || (int)$related["amount"] <= 0)
        return (new ErrorResponse("NotFound"));
    $available = max(0, (int)$related["amount"] - billing_credit_note_total_for_entry($related_entry_id, false));
    if ($amount > $available)
        return (new ErrorResponse("BillingCreditNoteExceedsInvoice"));
    $date = $data["credit_date"] ?? dbnow();
    if (date_to_timestamp(db_form_date($date)) === NULL)
        bad_request();
    $label = trim((string)($data["label"] ?? ""));

    if (!billing_add_credit_note($id_user, $related_entry_id, $label, $amount, $date))
        return (new ErrorResponse("CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["BillingCreditNoteDraftAdded"] ?? $Dictionnary["Added"]]));
}

function AddBillingRefund($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $User;

    $id_user = BillingRequireManagedUser($data);
    $amount = BillingPositiveAmount($data);
    $account = billing_account_for_user($id_user);
    $refundable = max(0, -(int)($account["balance"] ?? 0));
    if ($amount > $refundable)
        return (new ErrorResponse("BillingRefundExceedsCredit"));
    $school = billing_user_main_school($id_user);
    if ($school == NULL)
        return (new ErrorResponse("NotFound"));
    $reference = $Database->real_escape_string(trim($data["transfer_reference"] ?? ""));
    $comment = trim((string)($data["comment"] ?? ""));
    if ($comment == "")
        $comment = $Dictionnary["BillingRefundDefaultComment"] ?? "Remboursement";
    $comment = $Database->real_escape_string($comment);
    $payment_date = $Database->real_escape_string(db_form_date($data["payment_date"] ?? dbnow()));
    if (date_to_timestamp($payment_date) === NULL)
        bad_request();
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    $negative = -$amount;

    if ($Database->query("
        INSERT INTO billing_payment
        (id_user, id_school, amount, payment_date, transfer_reference, comment, id_actor)
        VALUES
        ($id_user, {$school["id_school"]}, $negative, '$payment_date', '$reference', '$comment', $actor)
    ") == NULL)
        return (new ErrorResponse("CannotRegister"));
    billing_reconcile_paid_invoices($id_user);
    billing_archive_paid_invoices($id_user);
    return (new ValueResponse(["msg" => $Dictionnary["BillingRefundAdded"] ?? $Dictionnary["Added"]]));
}

function AddBillingPayment($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $User;

    $id_user = BillingRequireManagedUser($data);
    $amount = BillingPositiveAmount($data);
    $school = billing_user_main_school($id_user);
    if ($school == NULL)
        return (new ErrorResponse("NotFound"));
    $reference = $Database->real_escape_string(trim($data["transfer_reference"] ?? ""));
    $comment = $Database->real_escape_string(trim($data["comment"] ?? ""));
    $payment_date = $Database->real_escape_string(db_form_date($data["payment_date"] ?? dbnow()));
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";

    if ($Database->query("
        INSERT INTO billing_payment
        (id_user, id_school, amount, payment_date, transfer_reference, comment, id_actor)
        VALUES
        ($id_user, {$school["id_school"]}, $amount, '$payment_date', '$reference', '$comment', $actor)
    ") == NULL)
        return (new ErrorResponse("CannotRegister"));
    billing_archive_paid_invoices($id_user);
    return (new ValueResponse(["msg" => $Dictionnary["Added"]]));
}

function SendBillingEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    $reference = trim((string)($data["invoice_reference"] ?? ""));
    $invoice = billing_send_invoice_placeholder($id, $reference);
    if (!is_array($invoice) || isset($invoice["error"]))
        return (new ErrorResponse($invoice["error"] ?? "CannotSendMail"));
    return (new ValueResponse([
        "msg" => $Dictionnary["Sent"],
        "invoice" => $invoice["relative_path"],
    ]));
}

function ViewBillingInvoice($id, $data, $method, $output, $module)
{
    if ($id <= 0)
        bad_request();
    $invoice = billing_invoice_pdf_response($id);
    if ($invoice === false)
        return (new ErrorResponse("CannotBuildInvoice"));
    return (new ValueResponse($invoice));
}

function QueueBillingInvoicePrint($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $entry = billing_entry_with_user($id, true);
    if (!is_array($entry) || !billing_user_is_managed((int)$entry["id_user"]))
        forbidden();
    $invoice = billing_invoice_pdf_response($id);
    if ($invoice === false)
        return (new ErrorResponse("CannotBuildInvoice"));
    $reference = billing_invoice_document_reference($entry);
    $recipient = trim((string)($entry["first_name"] ?? "")." ".(string)($entry["family_name"] ?? ""));
    if ($recipient == "")
        $recipient = (string)($entry["codename"] ?? "");
    $piece = billing_entry_document_label($entry);
    $fallback_prefix = billing_is_credit_note($entry) ? "avoir-" : "facture-";
    $queued = document_print_queue_content(
        (string)$invoice["content"],
        (string)($invoice["filename"] ?? ($fallback_prefix.$reference.".pdf")),
        $piece." ".$reference,
        [
            "type" => "finance",
            "owner_user_id" => (int)$entry["id_user"],
            "school_id" => (int)($entry["id_school"] ?? 0),
            "billing_entry_id" => $id,
            "source_key" => (billing_is_credit_note($entry) ? "billing-credit-note:" : "billing-invoice:").$id,
            "recipient_label" => $recipient,
        ]
    );
    if ($queued->is_error())
        return ($queued);
    return (new ValueResponse([
        "msg" => $Dictionnary["DocumentQueuedForPrint"] ?? ($piece." ajouté aux documents à imprimer."),
        "task_id" => (int)($queued->value["id"] ?? 0),
    ]));
}

function ArchivePaidBillingInvoices($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id <= 0)
        bad_request();
    if (!billing_user_is_managed($id))
        forbidden();
    $count = billing_archive_paid_invoices($id);
    if ($count <= 0)
        return (new ErrorResponse("NoPaidInvoiceToArchive"));
    return (new ValueResponse(["msg" => $Dictionnary["ArchivedPaidInvoices"]." : ".$count]));
}


function DeleteBillingEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id <= 0)
        bad_request();
    if (!billing_delete_entry($id))
        return (new ErrorResponse("CannotDelete"));
    return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
}

function DeleteBillingPayment($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id <= 0)
        bad_request();
    if (!billing_delete_payment($id))
        return (new ErrorResponse("CannotDelete"));
    return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
}

function AddBillingTemplate($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $User;

    foreach (["id_school", "name", "tariff_year", "amount_once", "amount_twice", "amount_four", "amount_twelve", "registration_fee"] as $field)
        if (!isset($data[$field]))
            bad_request();
    $id_school = (int)$data["id_school"];
    if (!is_billing_manager_for_school($id_school))
        forbidden();
    if (trim($data["name"]) == "" || !is_number($data["tariff_year"]))
        bad_request();

    $amount_once = billing_amount_to_cents($data["amount_once"]);
    $amount_twice = billing_amount_to_cents($data["amount_twice"]);
    $amount_four = billing_amount_to_cents($data["amount_four"]);
    $amount_twelve = billing_amount_to_cents($data["amount_twelve"]);
    $registration = billing_amount_to_cents($data["registration_fee"]);
    if ($amount_once === NULL || $amount_twice === NULL || $amount_four === NULL || $amount_twelve === NULL || $registration === NULL)
        bad_request();

    $name = $Database->real_escape_string(trim($data["name"]));
    $invoice_type = $Database->real_escape_string(billing_normalize_invoice_type($data["invoice_type"] ?? "school"));
    $tariff_year = max(0, (int)$data["tariff_year"]);
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";

    if ($Database->query("
        INSERT INTO billing_template
        (id_school, tariff_year, name, invoice_type, amount_once, amount_twice, amount_four, amount_twelve, registration_fee, id_actor)
        VALUES
        ($id_school, $tariff_year, '$name', '$invoice_type', $amount_once, $amount_twice, $amount_four, $amount_twelve, $registration, $actor)
    ") == NULL)
        return (new ErrorResponse("CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["Added"]]));
}

function DeleteBillingTemplate($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Dictionnary;

    $id_template = abs((int)$SUBID);
    if ($id_template <= 0)
        bad_request();
    $template = db_select_one("* FROM billing_template WHERE id = $id_template AND deleted IS NULL");
    if ($template == NULL)
        return (new ErrorResponse("NotFound"));
    if (!is_billing_manager_for_school($template["id_school"]))
        forbidden();
    db_update_one("billing_template", $id_template, ["deleted" => dbnow()]);
    return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
}

$Tab = [
    "GET" => [
        "invoice" => ["is_billing_manager_for_billing_entry", "ViewBillingInvoice"],
    ],
    "POST" => [
        "entry" => ["am_i_billing_manager", "AddBillingEntry"],
        "external_invoice" => ["am_i_billing_manager", "AddExternalBillingInvoice"],
        "credit_note" => ["am_i_billing_manager", "AddBillingCreditNote"],
        "payment" => ["am_i_billing_manager", "AddBillingPayment"],
        "refund" => ["am_i_billing_manager", "AddBillingRefund"],
        "template" => ["am_i_billing_manager", "AddBillingTemplate"],
        "apply_template" => ["am_i_billing_manager", "ApplyBillingTemplate"],
    ],
    "PUT" => [
        "send" => ["is_billing_manager_for_billing_entry", "SendBillingEntry"],
        "print" => ["is_billing_manager_for_billing_entry", "QueueBillingInvoicePrint"],
        "archive_paid" => ["is_billing_manager_for_user", "ArchivePaidBillingInvoices"],
        "visibility" => ["is_billing_manager_for_user", "SetBillingUserVisibility"],
    ],
    "DELETE" => [
        "entry" => ["is_billing_manager_for_billing_entry", "DeleteBillingEntry"],
        "payment" => ["is_billing_manager_for_billing_payment", "DeleteBillingPayment"],
        "template" => ["am_i_billing_manager", "DeleteBillingTemplate"],
    ],
];
