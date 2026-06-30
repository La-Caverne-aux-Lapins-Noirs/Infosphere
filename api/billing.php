<?php

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

function AddBillingEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id_user = BillingRequireManagedUser($data);
    $amount = BillingPositiveAmount($data);
    if (!isset($data["label"]) || trim($data["label"]) == "")
        bad_request();
    $due_date = $data["due_date"] ?? dbnow();
    if (!billing_add_entry($id_user, $data["label"], $amount, $due_date))
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
    $invoice = billing_send_invoice_placeholder($id);
    if ($invoice === false)
        return (new ErrorResponse("CannotSendMail"));
    return (new ValueResponse([
        "msg" => $Dictionnary["Sent"],
        "invoice" => $invoice["relative_path"],
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
    if (!is_billing_manager($id_school))
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
    $tariff_year = max(0, (int)$data["tariff_year"]);
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";

    if ($Database->query("
        INSERT INTO billing_template
        (id_school, tariff_year, name, amount_once, amount_twice, amount_four, amount_twelve, registration_fee, id_actor)
        VALUES
        ($id_school, $tariff_year, '$name', $amount_once, $amount_twice, $amount_four, $amount_twelve, $registration, $actor)
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
    if (!is_billing_manager($template["id_school"]))
        forbidden();
    db_update_one("billing_template", $id_template, ["deleted" => dbnow()]);
    return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
}

$Tab = [
    "POST" => [
        "entry" => ["is_billing_manager", "AddBillingEntry"],
        "payment" => ["is_billing_manager", "AddBillingPayment"],
        "template" => ["is_billing_manager", "AddBillingTemplate"],
        "apply_template" => ["is_billing_manager", "ApplyBillingTemplate"],
    ],
    "PUT" => [
        "send" => ["is_billing_manager_for_billing_entry", "SendBillingEntry"],
        "archive_paid" => ["is_billing_manager_for_user", "ArchivePaidBillingInvoices"],
    ],
    "DELETE" => [
        "entry" => ["is_billing_manager_for_billing_entry", "DeleteBillingEntry"],
        "payment" => ["is_billing_manager_for_billing_payment", "DeleteBillingPayment"],
        "template" => ["is_billing_manager", "DeleteBillingTemplate"],
    ],
];
