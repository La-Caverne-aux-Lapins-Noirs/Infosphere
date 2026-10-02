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

function BillingOrganizationDocument($data, $field = "document")
{
    $name = "";
    $content = NULL;

    if (isset($_FILES[$field]))
    {
        $upload = $_FILES[$field];
        if (is_array($upload["error"] ?? NULL))
            return (["error" => "InvalidBillingOrganizationDocument"]);
        $error = $upload["error"] ?? UPLOAD_ERR_NO_FILE;
        if ($error == UPLOAD_ERR_NO_FILE)
            return (["content" => NULL]);
        if ($error != UPLOAD_ERR_OK || empty($upload["tmp_name"]))
            return (["error" => "InvalidBillingOrganizationDocument"]);
        if ((int)($upload["size"] ?? 0) > 20 * 1024 * 1024)
            return (["error" => "BillingOrganizationDocumentTooLarge"]);
        $name = basename((string)($upload["name"] ?? "document"));
        $content = @file_get_contents($upload["tmp_name"]);
    }
    else if (!empty($data[$field]) && is_array($data[$field]))
    {
        $file = $data[$field][0] ?? $data[$field];
        if (is_array($file) && isset($file["content"]))
        {
            $name = basename((string)($file["name"] ?? "document"));
            $content = base64_decode((string)$file["content"], true);
        }
    }

    if ($content === NULL || $content === "")
        return (["content" => NULL]);
    if ($content === false || strlen($content) > 20 * 1024 * 1024)
        return (["error" => "InvalidBillingOrganizationDocument"]);

    $extension = NULL;
    if (strlen($content) >= 5 && substr($content, 0, 5) === "%PDF-")
        $extension = "pdf";
    else if (strlen($content) >= 8 && substr($content, 0, 8) === "\x89PNG\r\n\x1a\n")
        $extension = "png";
    else if (strlen($content) >= 3 && substr($content, 0, 3) === "\xFF\xD8\xFF")
        $extension = "jpg";
    if ($extension === NULL)
        return (["error" => "InvalidBillingOrganizationDocument"]);

    if ($name == "")
        $name = "document.".$extension;
    return ([
        "content" => $content,
        "name" => $name,
        "extension" => $extension,
    ]);
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
    $id_organization = (int)($data["id_organization"] ?? 0);
    if ($id_organization > 0 && !billing_organization_exists($id_organization))
        return (new ErrorResponse("NotFound"));
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
    $invoice_type = billing_invoice_type_key($data["invoice_type"] ?? "school");
    if ($invoice_type === NULL || !billing_invoice_type_is_allowed_for_school($school, $invoice_type))
        return (new ErrorResponse("BillingInvoiceTypeUnavailable"));

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
    $id_organization_sql = $id_organization > 0 ? (string)$id_organization : "NULL";
    $stored_entry = NULL;

    if ($Database->query("START TRANSACTION") === NULL)
        return (new ErrorResponse("CannotRegister"));
    if ($Database->query("
        INSERT INTO billing_entry
        (id_user, id_organization, id_school, id_template, label, amount, amount_ht, entry_type, invoice_type, vat_rate, due_date, invoice_reference, sent_date, id_actor)
        VALUES
        ($id_user, $id_organization_sql, {$school["id_school"]}, NULL, '$elabel', $amount, $amount, 'external_invoice', '$etype', 0, '$edue_date', '$eref', '$einvoice_date', $actor)
    ") === NULL)
    {
        $Database->query("ROLLBACK");
        return (new ErrorResponse("BillingInvoiceReferenceAlreadyUsed"));
    }

    $id_entry = (int)$Database->insert_id;
    $stored_entry = billing_entry_with_user($id_entry);
    if ($stored_entry == NULL)
    {
        $Database->query("ROLLBACK");
        return (new ErrorResponse("CannotRegister"));
    }

    if ($pdf["content"] !== NULL)
    {
        $filename = billing_store_external_invoice_pdf($stored_entry, $pdf["content"]);
        if ($filename === false || db_update_one("billing_entry", $id_entry, ["invoice_filename" => $filename]) === NULL)
        {
            billing_unlink_invoice_files($stored_entry);
            $Database->query("ROLLBACK");
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
            (id_user, id_organization, id_school, amount, payment_date, transfer_reference, comment, id_actor)
            VALUES
            (".($id_organization > 0 ? "NULL" : $id_user).", $id_organization_sql, {$school["id_school"]}, $amount, '$epayment_date', '$transfer_reference', '$comment', $actor)
        ") === NULL)
        {
            billing_unlink_invoice_files($stored_entry);
            $Database->query("ROLLBACK");
            return (new ErrorResponse("CannotRegister"));
        }
        $id_payment = (int)$Database->insert_id;
        if (function_exists("billing_einvoice_track_payment"))
            billing_einvoice_track_payment($id_payment);
    }

    if ($Database->query("COMMIT") === NULL)
    {
        billing_unlink_invoice_files($stored_entry);
        $Database->query("ROLLBACK");
        return (new ErrorResponse("CannotRegister"));
    }

    billing_reconcile_payment_account([
        "id_user" => $id_organization > 0 ? 0 : $id_user,
        "id_organization" => $id_organization,
        "id_school" => (int)$school["id_school"],
    ]);
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

    $id_user = isset($data["id_user"]) ? (int)$data["id_user"] : 0;
    $id_organization = isset($data["id_organization"]) ? (int)$data["id_organization"] : 0;
    $id_school = isset($data["id_school"]) ? (int)$data["id_school"] : 0;
    $requested_school = $id_school;
    if ($id_user > 0)
    {
        if (!billing_user_is_managed($id_user))
            forbidden();
        $school = billing_user_main_school($id_user);
        if ($school == NULL)
            return (new ErrorResponse("NotFound"));
        if ($requested_school > 0 && $requested_school !== (int)$school["id_school"])
            return (new ErrorResponse("NotFound"));
        $id_school = (int)$school["id_school"];
    }
    else
    {
        if ($id_organization <= 0 || $id_school <= 0)
            bad_request();
        if (!is_billing_manager_for_school($id_school))
            forbidden();
        if (!billing_organization_exists($id_organization))
            return (new ErrorResponse("NotFound"));
    }

    $amount = BillingPositiveAmount($data);
    if (!isset($data["label"]) || trim($data["label"]) == "")
        bad_request();
    $due_date = $data["due_date"] ?? dbnow();
    if (date_to_timestamp(db_form_date($due_date)) === NULL)
        bad_request();
    $invoice_type = billing_invoice_type_key($data["invoice_type"] ?? "school");
    $school = billing_school_context($id_school);
    if ($invoice_type === NULL || $school == NULL || !billing_invoice_type_is_allowed_for_school($school, $invoice_type))
        return (new ErrorResponse("BillingInvoiceTypeUnavailable"));
    $vat_rate = billing_normalize_vat_rate($data["vat_rate"] ?? 0);
    $entry_type = $id_user > 0 ? "tuition" : "service";
    if (!billing_add_entry(
        $id_user, $data["label"], $amount, $due_date, NULL, $entry_type,
        $invoice_type, $vat_rate, $id_organization, $id_school
    ))
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
    $id_organization = (int)($data["id_organization"] ?? 0);
    if ($id_organization > 0 && !billing_organization_exists($id_organization))
        return (new ErrorResponse("NotFound"));
    $first_due_date = $data["first_due_date"] ?? dbnow();
    $count = billing_apply_template($id_user, $data["id_template"], $first_due_date, $data["schedule_type"], $id_organization);
    if ($count == 0)
        return (new ErrorResponse("CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["Added"]]));
}


function ApplyBillingTemplateRemaining($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_user = BillingRequireManagedUser($data);
    if (!isset($data["id_template"]) || !is_number($data["id_template"]) ||
        !isset($data["schedule_type"]))
        bad_request();

    $first_due_date = $data["first_due_date"] ?? dbnow();
    if (date_to_timestamp(db_form_date($first_due_date)) === NULL)
        bad_request();

    $deduct_entry_ids = [];
    foreach ($data as $key => $value)
        if (!empty($value) && preg_match('/^deduct_entry_([0-9]+)$/', (string)$key, $match))
            $deduct_entry_ids[] = (int)$match[1];
    $deduct_entry_ids = array_values(array_unique($deduct_entry_ids));
    if (!count($deduct_entry_ids))
        return (new ErrorResponse("BillingRemainingScheduleSelectInvoice"));

    $id_organization = (int)($data["id_organization"] ?? 0);
    if ($id_organization > 0 && !billing_organization_exists($id_organization))
        return (new ErrorResponse("NotFound"));

    $result = billing_apply_template_remaining(
        $id_user,
        (int)$data["id_template"],
        $first_due_date,
        (string)$data["schedule_type"],
        $deduct_entry_ids,
        $id_organization
    );
    if (empty($result["ok"]))
        return (new ErrorResponse($result["error"] ?? "CannotRegister"));

    return (new ValueResponse([
        "msg" => $Dictionnary[$result["remaining"] > 0
            ? "BillingRemainingScheduleApplied"
            : "BillingRemainingScheduleNothingLeft"],
        "deducted" => (int)$result["deducted"],
        "remaining" => (int)$result["remaining"],
        "created" => (int)$result["created"],
    ]));
}

function AddBillingCreditNote($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $amount = BillingPositiveAmount($data);
    if (!isset($data["related_entry_id"]) || !is_number($data["related_entry_id"]))
        bad_request();
    $related_entry_id = (int)$data["related_entry_id"];
    $related = billing_entry_with_user($related_entry_id);
    if ($related == NULL || !billing_entry_is_managed($related) || empty($related["sent_date"]) ||
        billing_is_credit_note($related) || (int)$related["amount"] <= 0)
        return (new ErrorResponse("NotFound"));
    $available = max(0, (int)$related["amount"] - billing_credit_note_total_for_entry($related_entry_id, false));
    if ($amount > $available)
        return (new ErrorResponse("BillingCreditNoteExceedsInvoice"));
    $date = $data["credit_date"] ?? dbnow();
    if (date_to_timestamp(db_form_date($date)) === NULL)
        bad_request();
    $label = trim((string)($data["label"] ?? ""));

    if (!billing_add_credit_note($related_entry_id, $label, $amount, $date))
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
    $id_payment = (int)$Database->insert_id;
    if (function_exists("billing_einvoice_track_payment"))
        billing_einvoice_track_payment($id_payment);
    billing_reconcile_paid_invoices($id_user);
    billing_archive_paid_invoices($id_user);
    return (new ValueResponse(["msg" => $Dictionnary["BillingRefundAdded"] ?? $Dictionnary["Added"]]));
}

function UpdateBillingPayment($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_payment = (int)$id;
    if ($id_payment <= 0)
        bad_request();
    $payment = db_select_one("* FROM billing_payment WHERE id = $id_payment AND deleted IS NULL");
    if ($payment == NULL)
        return (new ErrorResponse("NotFound"));

    $reference = trim((string)($data["transfer_reference"] ?? ""));
    if (db_update_one("billing_payment", $id_payment, ["transfer_reference" => $reference]) === NULL)
        return (new ErrorResponse("CannotRegister"));
    if (function_exists("billing_einvoice_track_payment"))
        billing_einvoice_track_payment($id_payment);

    return (new ValueResponse([
        "msg" => $Dictionnary["Modified"] ?? "Modified",
    ]));
}

function AddBillingPayment($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $User;

    $id_user = isset($data["id_user"]) ? (int)$data["id_user"] : 0;
    $id_organization = isset($data["id_organization"]) ? (int)$data["id_organization"] : 0;
    $id_school = isset($data["id_school"]) ? (int)$data["id_school"] : 0;
    if (($id_user > 0) === ($id_organization > 0))
        bad_request();

    if ($id_organization > 0)
    {
        if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
            forbidden();
        if (!billing_organization_exists($id_organization))
            return (new ErrorResponse("NotFound"));
    }
    else
    {
        if (!billing_user_is_managed($id_user))
            forbidden();
        $school = billing_user_main_school($id_user);
        if ($school == NULL)
            return (new ErrorResponse("NotFound"));
        if ($id_school > 0 && $id_school !== (int)$school["id_school"])
            return (new ErrorResponse("NotFound"));
        $id_school = (int)$school["id_school"];
    }

    $amount = BillingPositiveAmount($data);
    $reference = $Database->real_escape_string(trim($data["transfer_reference"] ?? ""));
    $comment = $Database->real_escape_string(trim($data["comment"] ?? ""));
    $payment_date = db_form_date($data["payment_date"] ?? dbnow());
    if (date_to_timestamp($payment_date) === NULL)
        bad_request();
    $payment_date = $Database->real_escape_string($payment_date);
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    $id_user_sql = $id_user > 0 ? (string)$id_user : "NULL";
    $id_organization_sql = $id_organization > 0 ? (string)$id_organization : "NULL";

    if ($Database->query("
        INSERT INTO billing_payment
        (id_user, id_organization, id_school, amount, payment_date, transfer_reference, comment, id_actor)
        VALUES
        ($id_user_sql, $id_organization_sql, $id_school, $amount, '$payment_date', '$reference', '$comment', $actor)
    ") == NULL)
        return (new ErrorResponse("CannotRegister"));
    $id_payment = (int)$Database->insert_id;
    if (function_exists("billing_einvoice_track_payment"))
        billing_einvoice_track_payment($id_payment);
    billing_reconcile_payment_account([
        "id_user" => $id_user,
        "id_organization" => $id_organization,
        "id_school" => $id_school,
    ]);
    return (new ValueResponse(["msg" => $Dictionnary["Added"]]));
}

function UpdateBillingEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id <= 0)
        bad_request();
    $result = billing_update_draft_invoice($id, $data);
    if (empty($result["ok"]))
        return (new ErrorResponse($result["error"] ?? "CannotRegister"));
    $entry = $result["entry"];
    $id_user = (int)($entry["id_user"] ?? 0);
    $account = $id_user > 0 ? billing_account_for_user($id_user) : ["to_invoice" => 0];
    $amount_display = billing_euros((int)$entry["amount"]);
    $due_date = billing_document_date_label($entry["due_date"] ?? "");
    $invoice_type_label = billing_invoice_type_label($entry["invoice_type"] ?? "school");
    $state = [
        "id" => (int)$entry["id"],
        "id_user" => (int)$entry["id_user"],
        "label" => (string)$entry["label"],
        "amount" => (int)$entry["amount"],
        "amount_ht" => billing_entry_amount_ht($entry),
        "amount_ht_value" => number_format(billing_entry_amount_ht($entry) / 100, 2, '.', ''),
        "amount_display" => $amount_display,
        "vat_rate" => billing_normalize_vat_rate($entry["vat_rate"] ?? 0),
        "due_date" => date("Y-m-d", date_to_timestamp($entry["due_date"])),
        "due_date_label" => $due_date,
        "invoice_type" => billing_normalize_invoice_type($entry["invoice_type"] ?? "school"),
        "invoice_type_label" => $invoice_type_label,
        "id_organization" => (int)($entry["id_organization"] ?? 0),
        "buyer_routing_code" => (string)($entry["buyer_routing_code"] ?? ""),
        "tooltip" =>
            $Dictionnary["BillingInvoice"]."\n".
            $entry["label"]."\n".
            $amount_display."\n".
            $Dictionnary["DueDate"]." : ".$due_date."\n".
            $Dictionnary["InvoicePending"]."\n".
            $Dictionnary["Reference"]." : ".$Dictionnary["BillingInvoiceDraft"]."\n".
            $Dictionnary["BillingInvoiceType"]." : ".$invoice_type_label,
        "confirm" =>
            $Dictionnary["ConfirmSendBillingInvoice"]."\n".
            $entry["label"]."\n".$amount_display,
        "delete_confirm" =>
            $Dictionnary["ConfirmDeleteBillingEntry"]."\n".
            $entry["label"]."\n".$amount_display,
        "to_invoice" => (int)$account["to_invoice"],
        "to_invoice_text" => $Dictionnary["BillingToInvoice"]." : ".billing_euros($account["to_invoice"]),
    ];
    return (new ValueResponse([
        "msg" => $Dictionnary["BillingDraftSaved"] ?? $Dictionnary["Modified"],
        "content" => $state,
    ]));
}


function MarkBillingEntrySent($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id <= 0)
        bad_request();
    $reference = trim((string)($data["invoice_reference"] ?? ""));
    $invoice = billing_mark_invoice_sent($id, $reference);
    if (!is_array($invoice) || isset($invoice["error"]))
        return (new ErrorResponse($invoice["error"] ?? "CannotRegister"));
    return (new ValueResponse([
        "msg" => $Dictionnary["BillingInvoiceMarkedSent"] ?? $Dictionnary["Modified"],
        "invoice" => $invoice["relative_path"],
    ]));
}

function SendBillingEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    $entry = billing_entry_with_user((int)$id);
    if ($entry == NULL)
        return (new ErrorResponse("NotFound"));
    if (empty($entry["sent_date"]) && !billing_is_credit_note($entry))
    {
        $school = fetch_school((int)$entry["id_school"]);
        if ($school instanceof ErrorResponse || !is_array($school) ||
            !billing_invoice_type_is_allowed_for_school($school, $entry["invoice_type"] ?? "school"))
            return (new ErrorResponse("BillingInvoiceTypeUnavailable"));
    }
    $reference = trim((string)($data["invoice_reference"] ?? ""));
    $message = trim((string)($data["message"] ?? ""));
    if (strlen($message) > 8000)
        return (new ErrorResponse("BillingInvoiceMailMessageTooLong"));
    $invoice = billing_send_invoice_placeholder($id, $reference, $message);
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

function PreviewBillingInvoice($id, $data, $method, $output, $module)
{
    if ($id <= 0)
        bad_request();
    $invoice = billing_invoice_preview_pdf_response($id, $data);
    if (!is_array($invoice) || isset($invoice["error"]))
        return (new ErrorResponse($invoice["error"] ?? "CannotBuildInvoice"));
    return (new ValueResponse($invoice));
}


function QueueBillingInvoicePrint($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $entry = billing_entry_with_user($id, true);
    if (!is_array($entry) || !billing_entry_is_managed($entry))
        forbidden();
    $invoice = billing_invoice_pdf_response($id);
    if ($invoice === false)
        return (new ErrorResponse("CannotBuildInvoice"));
    $reference = billing_invoice_document_reference($entry);
    $recipient = billing_entry_client_name($entry);
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

    foreach (["id_school", "name", "tariff_year", "amount_once", "amount_twice", "amount_four", "amount_twelve", "registration_fee", "vat_rate"] as $field)
        if (!isset($data[$field]))
            bad_request();
    $id_school = (int)$data["id_school"];
    if (!is_billing_manager_for_school($id_school))
        forbidden();
    $school = fetch_school($id_school);
    if ($school instanceof ErrorResponse || !is_array($school))
        return (new ErrorResponse("NotFound"));
    $invoice_type = billing_invoice_type_key($data["invoice_type"] ?? "school");
    if ($invoice_type === NULL || !billing_invoice_type_is_allowed_for_school($school, $invoice_type))
        return (new ErrorResponse("BillingInvoiceTypeUnavailable"));
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
    $invoice_type = $Database->real_escape_string($invoice_type);
    $vat_rate = billing_normalize_vat_rate($data["vat_rate"] ?? 0);
    $tariff_year = max(0, (int)$data["tariff_year"]);
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";

    if ($Database->query("
        INSERT INTO billing_template
        (id_school, tariff_year, name, invoice_type, vat_rate, amount_once, amount_twice, amount_four, amount_twelve, registration_fee, id_actor)
        VALUES
        ($id_school, $tariff_year, '$name', '$invoice_type', $vat_rate, $amount_once, $amount_twice, $amount_four, $amount_twelve, $registration, $actor)
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


function AddOrganizationAccountEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    foreach (["id_school", "id_organization", "movement_type", "amount", "movement_date", "label"] as $field)
        if (!isset($data[$field]))
            bad_request();

    $id_school = (int)$data["id_school"];
    $id_organization = (int)$data["id_organization"];
    $movement_type = trim((string)$data["movement_type"]);
    $amount = BillingPositiveAmount($data);
    if (!in_array($movement_type, ["debit", "credit"], true))
        bad_request();
    if (!is_billing_manager_for_school($id_school))
        forbidden();
    if (!billing_organization_exists($id_organization))
        return (new ErrorResponse("NotFound"));
    if (trim((string)$data["label"]) == "")
        bad_request();

    $document = BillingOrganizationDocument($data);
    if (isset($document["error"]))
        return (new ErrorResponse($document["error"]));

    $id_entry = billing_add_organization_entry(
        $id_school,
        $id_organization,
        $movement_type,
        $amount,
        $data["movement_date"],
        $data["label"],
        $data["reference"] ?? "",
        $data["comment"] ?? ""
    );
    if (!$id_entry)
        return (new ErrorResponse("CannotRegister"));

    if ($document["content"] !== NULL && !billing_store_organization_entry_document($id_entry, $document))
    {
        billing_delete_organization_entry($id_entry);
        return (new ErrorResponse("CannotWriteFile"));
    }

    return (new ValueResponse(["msg" => $Dictionnary["Added"]]));
}

function UpdateOrganizationAccountEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $before = billing_fetch_organization_account_entry($id);
    if ($before == NULL)
        return (new ErrorResponse("NotFound"));
    if (!is_billing_manager_for_organization_account_entry($id))
        forbidden();

    foreach (["id_school", "id_organization", "movement_type", "amount", "movement_date", "label"] as $field)
        if (!isset($data[$field]))
            bad_request();
    $id_school = (int)$data["id_school"];
    $id_organization = (int)$data["id_organization"];
    $movement_type = trim((string)$data["movement_type"]);
    $amount = BillingPositiveAmount($data);
    if (!in_array($movement_type, ["debit", "credit"], true))
        bad_request();
    if (!is_billing_manager_for_school($id_school))
        forbidden();
    if (!billing_organization_exists($id_organization))
        return (new ErrorResponse("NotFound"));
    if (trim((string)$data["label"]) == "")
        bad_request();

    $document = BillingOrganizationDocument($data);
    if (isset($document["error"]))
        return (new ErrorResponse($document["error"]));

    if (!billing_update_organization_entry(
        $id, $id_school, $id_organization, $movement_type, $amount,
        $data["movement_date"], $data["label"], $data["reference"] ?? "", $data["comment"] ?? ""
    ))
        return (new ErrorResponse("CannotRegister"));

    $after = billing_fetch_organization_account_entry($id);
    if ($after == NULL)
        return (new ErrorResponse("CannotRegister"));

    if (!empty($data["delete_document"]))
    {
        if (!billing_remove_organization_entry_document($after))
            return (new ErrorResponse("CannotWriteFile"));
        $after = billing_fetch_organization_account_entry($id);
    }
    else if ($document["content"] === NULL &&
        ((int)$before["id_school"] !== (int)$after["id_school"] ||
         (int)$before["id_organization"] !== (int)$after["id_organization"]))
    {
        if (!billing_move_organization_entry_document($before, $after))
            return (new ErrorResponse("CannotWriteFile"));
        $after = billing_fetch_organization_account_entry($id);
    }

    if ($document["content"] !== NULL && !billing_store_organization_entry_document($id, $document))
        return (new ErrorResponse("CannotWriteFile"));

    return (new ValueResponse(["msg" => $Dictionnary["Saved"] ?? $Dictionnary["Added"]]));
}




function SyncBillingElectronicDocuments($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $ret = billing_einvoice_sync_managed();
    return (new ValueResponse([
        "msg" => sprintf(
            ($Dictionnary["BillingElectronicSyncResult"] ?? "%d créée(s), %d mise(s) à jour, %d inchangée(s), %d erreur(s)")." · encaissements : %d préparé(s), %d mis à jour",
            (int)$ret["created"], (int)$ret["updated"], (int)$ret["unchanged"], (int)$ret["errors"],
            (int)($ret["payments_created"] ?? 0), (int)($ret["payments_updated"] ?? 0)
        ),
        "content" => $ret,
    ]));
}

function SaveBillingElectronicConnector($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_school = (int)$id;
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
        forbidden();
    if (!billing_einvoice_save_connector_config($id_school, $data))
        return (new ErrorResponse("CannotRegister"));
    return (new ValueResponse([
        "msg" => $Dictionnary["Saved"] ?? "Saved",
    ]));
}

function ExportBillingElectronicUbl($id, $data, $method, $output, $module)
{
    $ret = billing_einvoice_export_ubl((int)$id);
    if (empty($ret["ok"]))
    {
        $error = $ret["error"] ?? "CannotCreateFile";
        if (!empty($ret["missing"]))
            $error .= " : ".implode(", ", $ret["missing"]);
        return (new ErrorResponse($error));
    }
    return (new ValueResponse([
        "filename" => $ret["filename"],
        "content_type" => $ret["content_type"],
        "content" => $ret["content"],
    ]));
}


function ExportBillingElectronicCdar($id, $data, $method, $output, $module)
{
    $ret = billing_einvoice_export_cdar((int)$id);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "CannotCreateFile"));
    return (new ValueResponse([
        "filename" => $ret["filename"],
        "content_type" => $ret["content_type"],
        "content" => $ret["content"],
    ]));
}

function RetryBillingElectronicDocuments($id, $data, $method, $output, $module)
{
    $id_school = (int)$id;
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
        forbidden();
    $ret = billing_einvoice_retry_for_school($id_school, true);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "BillingElectronicConnectorError"));
    return (new ValueResponse([
        "msg" => sprintf("%d tentative(s), %d transmise(s), %d erreur(s)", (int)$ret["retried"], (int)$ret["sent"], (int)$ret["errors"]),
        "content" => $ret,
    ]));
}

function ReviewBillingElectronicDocument($id, $data, $method, $output, $module)
{
    $document = billing_einvoice_document((int)$id);
    if ($document == NULL)
        return (new ErrorResponse("NotFound"));
    if (!is_billing_manager_for_school((int)$document["id_school"]))
        forbidden();
    $ret = billing_einvoice_mark_reviewed((int)$id, $data["note"] ?? "");
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "CannotRegister"));
    return (new ValueResponse(["msg" => "Document marqué comme contrôlé"]));
}

function ValidateBillingElectronicDocument($id, $data, $method, $output, $module)
{
    $document = billing_einvoice_document((int)$id);
    if ($document == NULL)
        return (new ErrorResponse("NotFound"));
    if (!is_billing_manager_for_school((int)$document["id_school"]))
        forbidden();
    $ret = billing_einvoice_run_regulatory_validation((int)$id);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "BillingElectronicValidationError"));
    return (new ValueResponse([
        "msg" => "Validation réglementaire : ".(string)$ret["status"],
        "content" => $ret,
    ]));
}

function TestBillingElectronicConnector($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_school = (int)$id;
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
        forbidden();
    $ret = billing_einvoice_test_connector($id_school);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "BillingElectronicConnectorError"));
    return (new ValueResponse([
        "msg" => $ret["message"] ?? ($Dictionnary["BillingElectronicConnectorReachable"] ?? "Connecteur accessible"),
        "content" => $ret,
    ]));
}

function SendBillingElectronicDocument($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $document = billing_einvoice_document((int)$id);
    if ($document == NULL)
        return (new ErrorResponse("NotFound"));
    if (!is_billing_manager_for_school((int)$document["id_school"]))
        forbidden();
    $ret = billing_einvoice_send_document((int)$id);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "BillingElectronicConnectorError"));
    return (new ValueResponse([
        "msg" => $Dictionnary["BillingElectronicSent"] ?? "Document transmis",
        "content" => $ret,
    ]));
}

function RefreshBillingElectronicDocument($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $document = billing_einvoice_document((int)$id);
    if ($document == NULL)
        return (new ErrorResponse("NotFound"));
    if (!is_billing_manager_for_school((int)$document["id_school"]))
        forbidden();
    $ret = billing_einvoice_refresh_document((int)$id);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "BillingElectronicConnectorError"));
    return (new ValueResponse([
        "msg" => $Dictionnary["BillingElectronicRefreshed"] ?? "Statut actualisé",
        "content" => $ret,
    ]));
}

function ReceiveBillingElectronicDocuments($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_school = (int)$id;
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
        forbidden();
    $ret = billing_einvoice_receive_for_school($id_school);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "BillingElectronicConnectorError"));
    $message = sprintf(
        $Dictionnary["BillingElectronicReceiveResult"] ?? "%d reçue(s), %d importée(s), %d à contrôler, %d non interprétée(s), %d déjà connue(s)",
        (int)($ret["received"] ?? 0),
        (int)($ret["imported"] ?? 0),
        (int)($ret["review"] ?? 0),
        (int)($ret["unparsed"] ?? 0),
        (int)($ret["known"] ?? 0)
    );
    if (!empty($ret["lifecycle"]) || !empty($ret["lifecycle_unmatched"]))
        $message .= " · ".(int)($ret["lifecycle"] ?? 0)." statut(s) métier mis à jour".(!empty($ret["lifecycle_unmatched"]) ? ", ".(int)$ret["lifecycle_unmatched"]." non rattaché(s)" : "");
    if (!empty($ret["errors"]))
        $message .= " · ".(int)$ret["errors"]." erreur(s), curseur non avancé";
    return (new ValueResponse([
        "msg" => $message,
        "content" => $ret,
    ]));
}

function GenerateAccountingExport($id, $data, $method, $output, $module)
{
    foreach (["start_date", "end_date"] as $field)
        if (!isset($data[$field]))
            bad_request();
    $ret = billing_build_accounting_export($data["start_date"], $data["end_date"]);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "CannotCreateArchive"));
    return (new ValueResponse([
        "filename" => $ret["filename"],
        "content_type" => "application/zip",
        "content" => $ret["content"],
    ]));
}

function DeleteOrganizationAccountEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    if (!billing_delete_organization_entry($id))
        return (new ErrorResponse("CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
}


function ImportBillingBankCsv($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_school = (int)($data["id_school"] ?? 0);
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
        forbidden();
    if (!isset($_FILES["bank_csv"]))
        bad_request();
    $upload = $_FILES["bank_csv"];
    if (($upload["error"] ?? UPLOAD_ERR_NO_FILE) != UPLOAD_ERR_OK || empty($upload["tmp_name"]))
        return (new ErrorResponse("CannotReadFile"));
    if ((int)($upload["size"] ?? 0) > 10 * 1024 * 1024)
        return (new ErrorResponse("FileTooLarge"));
    $name = strtolower((string)($upload["name"] ?? ""));
    if ($name != "" && pathinfo($name, PATHINFO_EXTENSION) != "csv")
        return (new ErrorResponse("BillingBankCsvOnly"));

    $ret = billing_bank_import_csv($upload["tmp_name"], $id_school);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "CannotReadFile"));
    return (new ValueResponse([
        "msg" => sprintf(
            $Dictionnary["BillingBankImportResult"] ?? "%d added, %d already known, %d ignored",
            (int)$ret["inserted"], (int)$ret["known"], (int)$ret["ignored"]
        ),
        "content" => $ret,
    ]));
}

function ReconcileBillingBankTransaction($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $kind = trim((string)($data["association_kind"] ?? ""));
    $target = (int)($data["association_id"] ?? 0);
    if ($kind == "" || $target <= 0)
        return (new ErrorResponse("BillingBankAssociationRequired"));
    $document = BillingOrganizationDocument($data, "document");
    if (isset($document["error"]))
        return (new ErrorResponse($document["error"]));
    $ret = billing_bank_reconcile($id, $kind, $target, $document);
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["BillingBankReconciled"] ?? $Dictionnary["Saved"]]));
}

function IgnoreBillingBankTransaction($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (!billing_bank_ignore((int)$id))
        return (new ErrorResponse("CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["BillingBankIgnored"] ?? $Dictionnary["Saved"]]));
}

function ReopenBillingBankTransaction($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (!billing_bank_reopen((int)$id))
        return (new ErrorResponse("CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["BillingBankReopened"] ?? $Dictionnary["Saved"]]));
}

function CreateBillingBankOrganization($id, $data, $method, $output, $module)
{
    $name = trim((string)($data["name"] ?? ""));
    if ($name == "")
        bad_request();
    $base = convert_to_codename($name);
    if ($base == "")
        $base = "entreprise";
    $codename = $base;
    $suffix = 2;
    while (($resolved = resolve_codename("organization", $codename))->is_error() == false)
        $codename = $base."-".$suffix++;

    $ret = add_enterprise([
        "codename" => $codename,
        "name" => $name,
        "legal_name" => $name,
    ]);
    if ($ret->is_error())
        return ($ret);
    $organization = $ret->value;
    return (new ValueResponse([
        "msg" => "Organisation créée",
        "organization" => [
            "id" => (int)$organization["id"],
            "codename" => (string)$organization["codename"],
            "name" => (string)($organization["name"] ?: $organization["legal_name"] ?: $organization["codename"]),
        ],
    ]));
}

function AddBillingSupplierInvoice($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    foreach (["id_school", "id_organization", "amount", "invoice_date"] as $field)
        if (!isset($data[$field]))
            bad_request();
    $id_school = (int)$data["id_school"];
    if (!is_billing_manager_for_school($id_school))
        forbidden();
    $amount = BillingPositiveAmount($data);
    $document = BillingOrganizationDocument($data, "document");
    if (isset($document["error"]))
        return (new ErrorResponse($document["error"]));
    $ret = billing_bank_create_supplier_invoice(
        $id_school,
        (int)$data["id_organization"],
        $amount,
        $data["invoice_date"],
        $data["reference"] ?? "",
        $data["label"] ?? "",
        $data["comment"] ?? "",
        $document,
        (int)($data["id_bank_transaction"] ?? 0)
    );
    if (empty($ret["ok"]))
        return (new ErrorResponse($ret["error"] ?? "CannotRegister"));
    return (new ValueResponse(["msg" => $Dictionnary["BillingSupplierInvoiceAdded"] ?? $Dictionnary["Added"]]));
}


function ConfigureBillingElectronicMock($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_school = (int)$id;
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
        forbidden();
    $config = billing_einvoice_connector_config($id_school);
    if ($config == NULL || ($config["connector_key"] ?? "") !== "mock")
        return (new ErrorResponse("BillingElectronicMockNotConfigured"));

    $action = trim((string)($data["mock_action"] ?? ""));
    if ($action == "next_send")
    {
        $mode = trim((string)($data["mode"] ?? "normal"));
        if (!billing_einvoice_mock_set_next_send_mode($id_school, $mode))
            return (new ErrorResponse("CannotRegister"));
        return (new ValueResponse(["msg" => "Comportement de la prochaine transmission enregistré"]));
    }
    if ($action == "status")
    {
        $flow_id = trim((string)($data["flow_id"] ?? ""));
        $status_code = trim((string)($data["status_code"] ?? ""));
        if ($flow_id == "" || !billing_einvoice_mock_set_flow_status($id_school, $flow_id, $status_code))
            return (new ErrorResponse("CannotRegister"));
        return (new ValueResponse(["msg" => "Statut distant simulé mis à jour"]));
    }
    if ($action == "lifecycle")
    {
        /* A CDAR must reflect the status already registered by the remote
         * platform.  Do not trust the current value of the browser select:
         * it may be stale or have been changed without submitting the status. */
        $flow_id = trim((string)($data["flow_id"] ?? ""));
        if ($flow_id == "" || !billing_einvoice_mock_queue_lifecycle_for_flow($id_school, $flow_id))
            return (new ErrorResponse("CannotRegister"));
        return (new ValueResponse(["msg" => "Retour CDAR ajouté à la boîte de réception du Mock"]));
    }
    if ($action == "supplier")
    {
        $gross = BillingPositiveAmount($data, "amount");
        $pdf = BillingExternalInvoicePdfContent($data);
        if (isset($pdf["error"]))
            return (new ErrorResponse($pdf["error"]));
        $ret = billing_einvoice_mock_queue_supplier_invoice($id_school, [
            "supplier_name" => $data["supplier_name"] ?? "",
            "supplier_siret" => $data["supplier_siret"] ?? "",
            "reference" => $data["reference"] ?? "",
            "issue_date" => $data["issue_date"] ?? "",
            "gross" => $gross,
            "vat_rate" => $data["vat_rate"] ?? 20,
        ], $pdf["content"] ?? NULL);
        if (empty($ret["ok"]))
            return (new ErrorResponse($ret["error"] ?? "CannotRegister"));
        return (new ValueResponse(["msg" => "Facture fournisseur fictive placée dans la boîte de réception"]));
    }
    if ($action == "reset")
    {
        if (!billing_einvoice_mock_reset($id_school))
            return (new ErrorResponse("CannotRegister"));
        return (new ValueResponse(["msg" => "Plateforme Mock vidée"]));
    }
    bad_request();
}

$Tab = [
    "GET" => [
        "invoice" => ["is_billing_manager_for_billing_entry", "ViewBillingInvoice"],
        "electronic_ubl" => ["am_i_billing_manager", "ExportBillingElectronicUbl"],
        "electronic_cdar" => ["am_i_billing_manager", "ExportBillingElectronicCdar"],
    ],
    "POST" => [
        "entry" => ["am_i_billing_manager", "AddBillingEntry"],
        "organization_entry" => ["am_i_billing_manager", "AddOrganizationAccountEntry"],
        "organization_entry_update" => ["is_billing_manager_for_organization_account_entry", "UpdateOrganizationAccountEntry"],
        "accounting_export" => ["am_i_billing_manager", "GenerateAccountingExport"],
        "electronic_sync" => ["am_i_billing_manager", "SyncBillingElectronicDocuments"],
        "electronic_connector" => ["am_i_billing_manager", "SaveBillingElectronicConnector"],
        "electronic_connector_test" => ["am_i_billing_manager", "TestBillingElectronicConnector"],
        "electronic_send" => ["am_i_billing_manager", "SendBillingElectronicDocument"],
        "electronic_refresh" => ["am_i_billing_manager", "RefreshBillingElectronicDocument"],
        "electronic_receive" => ["am_i_billing_manager", "ReceiveBillingElectronicDocuments"],
        "electronic_retry" => ["am_i_billing_manager", "RetryBillingElectronicDocuments"],
        "electronic_review" => ["am_i_billing_manager", "ReviewBillingElectronicDocument"],
        "electronic_validate" => ["am_i_billing_manager", "ValidateBillingElectronicDocument"],
        "electronic_mock" => ["am_i_billing_manager", "ConfigureBillingElectronicMock"],
        "bank_import" => ["am_i_billing_manager", "ImportBillingBankCsv"],
        "bank_reconcile" => ["am_i_billing_manager", "ReconcileBillingBankTransaction"],
        "bank_ignore" => ["am_i_billing_manager", "IgnoreBillingBankTransaction"],
        "bank_reopen" => ["am_i_billing_manager", "ReopenBillingBankTransaction"],
        "bank_organization" => ["am_i_billing_manager", "CreateBillingBankOrganization"],
        "supplier_invoice" => ["am_i_billing_manager", "AddBillingSupplierInvoice"],
        "external_invoice" => ["am_i_billing_manager", "AddExternalBillingInvoice"],
        "credit_note" => ["am_i_billing_manager", "AddBillingCreditNote"],
        "payment" => ["am_i_billing_manager", "AddBillingPayment"],
        "refund" => ["am_i_billing_manager", "AddBillingRefund"],
        "template" => ["am_i_billing_manager", "AddBillingTemplate"],
        "apply_template" => ["am_i_billing_manager", "ApplyBillingTemplate"],
        "apply_template_remaining" => ["am_i_billing_manager", "ApplyBillingTemplateRemaining"],
        "preview" => ["is_billing_manager_for_billing_entry", "PreviewBillingInvoice"],
    ],
    "PUT" => [
	"entry" => ["is_billing_manager_for_billing_entry", "UpdateBillingEntry"],
        "send" => ["is_billing_manager_for_billing_entry", "SendBillingEntry"],
        "mark_sent" => ["is_billing_manager_for_billing_entry", "MarkBillingEntrySent"],
        "payment" => ["is_billing_manager_for_billing_payment", "UpdateBillingPayment"],
        "print" => ["is_billing_manager_for_billing_entry", "QueueBillingInvoicePrint"],
        "archive_paid" => ["is_billing_manager_for_user", "ArchivePaidBillingInvoices"],
        "visibility" => ["is_billing_manager_for_user", "SetBillingUserVisibility"],
    ],
    "DELETE" => [
        "entry" => ["is_billing_manager_for_billing_entry", "DeleteBillingEntry"],
        "organization_entry" => ["is_billing_manager_for_organization_account_entry", "DeleteOrganizationAccountEntry"],
        "payment" => ["is_billing_manager_for_billing_payment", "DeleteBillingPayment"],
        "template" => ["am_i_billing_manager", "DeleteBillingTemplate"],
    ],
];
