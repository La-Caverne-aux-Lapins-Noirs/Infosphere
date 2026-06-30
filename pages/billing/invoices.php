<?php

$invoices = billing_fetch_issued_invoices();

function billing_invoice_page_e($str)
{
    return (htmlspecialchars((string)$str, ENT_QUOTES));
}

function billing_invoice_page_date_label($date)
{
    $stamp = date_to_timestamp($date);

    if ($stamp == NULL)
        return (billing_invoice_page_e($date));
    return (datex("d/m/Y", $stamp));
}

function billing_invoice_page_datetime_label($date)
{
    $stamp = date_to_timestamp($date);

    if ($stamp == NULL)
        return (billing_invoice_page_e($date));
    return (datex("d/m/Y H:i", $stamp));
}

function billing_invoice_page_student_name($invoice)
{
    $name = trim(($invoice["first_name"] ?? "")." ".($invoice["family_name"] ?? ""));

    if ($name == "")
        $name = $invoice["codename"] ?? "";
    return ($name);
}

function billing_invoice_page_status_label($invoice)
{
    global $Dictionnary;

    switch ($invoice["status_key"] ?? "issued")
    {
    case "deleted":
        return ($Dictionnary["BillingInvoiceDeleted"]);
    case "paid":
        return ($Dictionnary["BillingInvoicePaid"]);
    case "covered":
        return ($Dictionnary["BillingInvoiceCovered"]);
    case "partial":
        return ($Dictionnary["BillingInvoicePartial"]);
    default:
        return ($Dictionnary["BillingInvoiceIssuedOnly"]);
    }
}

function billing_invoice_page_status_class($invoice)
{
    switch ($invoice["status_key"] ?? "issued")
    {
    case "deleted":
        return ("billing_invoice_status_deleted");
    case "paid":
        return ("billing_invoice_status_paid");
    case "covered":
        return ("billing_invoice_status_covered");
    case "partial":
        return ("billing_invoice_status_partial");
    default:
        return ("billing_invoice_status_issued");
    }
}

function billing_invoice_page_render_status($invoice)
{
    $status = billing_invoice_page_status_label($invoice);
    $covered = (int)($invoice["covered_amount"] ?? 0);
    $amount = (int)$invoice["amount"];

    $details = "";
    if ($covered > 0 && $covered < $amount)
        $details = "<small>".billing_euros($covered)." / ".billing_euros($amount)."</small>";
    else if ($covered >= $amount && empty($invoice["paid_date"]) && empty($invoice["deleted"]))
        $details = "<small>".billing_euros($covered)."</small>";
    if (!empty($invoice["deleted"]))
        $details .= "<small>".billing_invoice_page_datetime_label($invoice["deleted"])."</small>";

    return ("<span class='billing_invoice_status ".billing_invoice_page_status_class($invoice)."'>".
        billing_invoice_page_e($status).$details."</span>");
}

function billing_invoice_page_render_student($invoice)
{
    return ("<a href='index.php?p=ProfileMenu&amp;a=".(int)$invoice["id_user"]."'>".
        billing_invoice_page_e(billing_invoice_page_student_name($invoice))."</a><br />".
        "<small>".billing_invoice_page_e($invoice["codename"])."</small>");
}

function billing_invoice_page_render_reference($invoice)
{
    $ref = trim((string)($invoice["invoice_reference"] ?? ""));

    if ($ref == "")
        $ref = billing_invoice_default_reference($invoice["id"]);
    return (billing_invoice_page_e($ref));
}

function billing_invoice_page_render_copy($invoice)
{
    global $Dictionnary;

    $path = billing_invoice_relative_path($invoice);
    if ($path == "")
        return ("<span class='billing_invoice_no_copy'>—</span>");
    return ("<span class='billing_invoice_copy_path' title='".billing_invoice_page_e($Dictionnary["InvoiceCopySavedIn"])."'>".
        billing_invoice_page_e($invoice["codename"]."/".$path)."</span>");
}

$fields = [
    [
        "name" => "id",
        "label" => "#",
        "type" => "number",
        "width" => "45px",
        "raw" => fn($i) => $i["id"],
        "render" => fn($i) => $i["id"],
        "copyable" => true,
    ],
    [
        "name" => "invoice_reference",
        "label" => $Dictionnary["Reference"],
        "type" => "text",
        "width" => "125px",
        "raw" => fn($i) => trim((string)$i["invoice_reference"]) ?: billing_invoice_default_reference($i["id"]),
        "render" => fn($i) => billing_invoice_page_render_reference($i),
        "copyable" => true,
    ],
    [
        "name" => "student",
        "label" => $Dictionnary["Student"],
        "type" => "text",
        "width" => "170px",
        "raw" => fn($i) => billing_invoice_page_student_name($i)." ".$i["codename"]." ".$i["mail"],
        "render" => fn($i) => billing_invoice_page_render_student($i),
        "cell_class" => "billing_invoice_student",
    ],
    [
        "name" => "school",
        "label" => $Dictionnary["School"],
        "type" => "text",
        "width" => "75px",
        "raw" => fn($i) => $i["school_name"] ?: $i["school_codename"],
        "render" => fn($i) => billing_invoice_page_e($i["school_name"] ?: $i["school_codename"]),
    ],
    [
        "name" => "label",
        "label" => $Dictionnary["BillingLabel"],
        "type" => "text",
        "width" => "340px",
        "raw" => fn($i) => $i["label"]." ".($i["template_name"] ?? ""),
        "render" => fn($i) => billing_invoice_page_e($i["label"]),
        "cell_class" => "billing_invoice_label",
    ],
    [
        "name" => "amount",
        "label" => $Dictionnary["Amount"],
        "type" => "number",
        "width" => "90px",
        "raw" => fn($i) => $i["amount"],
        "render" => fn($i) => billing_euros($i["amount"]),
    ],
    [
        "name" => "sent_date",
        "label" => $Dictionnary["InvoiceIssued"],
        "type" => "text",
        "width" => "110px",
        "raw" => fn($i) => $i["sent_date"],
        "render" => fn($i) => billing_invoice_page_datetime_label($i["sent_date"]),
    ],
    [
        "name" => "due_date",
        "label" => $Dictionnary["DueDate"],
        "type" => "text",
        "width" => "95px",
        "raw" => fn($i) => $i["due_date"],
        "render" => fn($i) => billing_invoice_page_date_label($i["due_date"]),
    ],
    [
        "name" => "status",
        "label" => $Dictionnary["Status"],
        "type" => "select",
        "width" => "145px",
        "options" => [
            "issued" => $Dictionnary["BillingInvoiceIssuedOnly"],
            "partial" => $Dictionnary["BillingInvoicePartial"],
            "covered" => $Dictionnary["BillingInvoiceCovered"],
            "paid" => $Dictionnary["BillingInvoicePaid"],
            "deleted" => $Dictionnary["BillingInvoiceDeleted"],
        ],
        "raw" => fn($i) => $i["status_key"],
        "render" => fn($i) => billing_invoice_page_render_status($i),
        "cell_class" => fn($i) => billing_invoice_page_status_class($i),
    ],
    [
        "name" => "copy",
        "label" => $Dictionnary["InvoiceCopySavedIn"],
        "type" => "text",
        "width" => "310px",
        "raw" => fn($i) => $i["codename"]."/".billing_invoice_relative_path($i),
        "render" => fn($i) => billing_invoice_page_render_copy($i),
        "copyable" => true,
        "cell_class" => "billing_invoice_copy",
    ],
];
?>

<h2 class="alignable_blocks billing_title"><?=$Dictionnary["BillingInvoices"]; ?></h2>
<input
    type="button"
    class="alignable_blocks"
    value="<?=$Dictionnary["Billing"]; ?>"
    onclick="document.location='index.php?p=BillingMenu';"
/>
<input
    type="button"
    class="alignable_blocks"
    value="<?=$Dictionnary["BillingTemplates"]; ?>"
    onclick="document.location='index.php?p=BillingTemplateMenu';"
/>

<style><?php require (__DIR__."/style.css"); ?></style>

<div class="fullscreen scrollable" id="billing_invoice_panel">
    <p class="billing_invoice_hint"><?=$Dictionnary["BillingInvoicesHint"]; ?></p>
    <?php render_dynamic_table("billing_invoice_table", $fields, $invoices); ?>
</div>
