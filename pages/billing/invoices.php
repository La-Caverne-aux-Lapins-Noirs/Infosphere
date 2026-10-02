<?php

require_once (__DIR__."/../../tools/document_print.php");

$invoices = billing_fetch_issued_invoices();
$billing_invoice_schools = billing_fetch_managed_schools();
$billing_invoice_organizations = billing_fetch_organizations();
$billing_invoice_students = billing_fetch_students(true);
$billing_invoice_vat_rates = billing_vat_rates();
$billing_invoice_types = billing_invoice_types();

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
    case "draft":
        return ($Dictionnary["BillingInvoiceDraft"] ?? "Brouillon");
    case "credit_note":
        return ($Dictionnary["BillingCreditNoteIssued"]);
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
    case "draft":
        return ("billing_invoice_status_draft");
    case "credit_note":
        return ("billing_invoice_status_credit_note");
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
    if (!billing_is_credit_note($invoice) && $covered > 0 && $covered < $amount)
        $details = "<small>".billing_euros($covered)." / ".billing_euros($amount)."</small>";
    else if (!billing_is_credit_note($invoice) && $covered >= $amount && empty($invoice["paid_date"]) && empty($invoice["deleted"]))
        $details = "<small>".billing_euros($covered)."</small>";
    if (!empty($invoice["deleted"]))
        $details .= "<small>".billing_invoice_page_datetime_label($invoice["deleted"])."</small>";

    return ("<span class='billing_invoice_status ".billing_invoice_page_status_class($invoice)."'>".
        billing_invoice_page_e($status).$details."</span>");
}

function billing_invoice_page_render_party($invoice)
{
    global $Dictionnary;

    $student = billing_entry_student_name($invoice);
    $organization = billing_entry_organization_name($invoice);
    $html = "";
    if ($organization != "")
    {
        $html .= "<strong>".billing_invoice_page_e($organization)."</strong>";
        if (!empty($invoice["organization_codename"]))
            $html .= "<br /><small>".billing_invoice_page_e($invoice["organization_codename"])."</small>";
    }
    if ($student != "")
    {
        if ($html != "")
            $html .= "<br /><small>".billing_invoice_page_e($Dictionnary["Student"] ?? "Bénéficiaire")." : ";
        else
            $html .= "<small>";
        $html .= "<a href='index.php?p=ProfileMenu&amp;a=".(int)$invoice["id_user"]."'>".
            billing_invoice_page_e($student)."</a></small>";
    }
    return ($html == "" ? "—" : $html);
}

function billing_invoice_page_party_raw($invoice)
{
    return (trim(
        billing_entry_organization_name($invoice)." ".
        billing_entry_student_name($invoice)." ".
        (string)($invoice["organization_codename"] ?? "")." ".
        (string)($invoice["codename"] ?? "")
    ));
}


function billing_invoice_page_render_reference($invoice)
{
    return (billing_invoice_page_e(billing_invoice_document_reference($invoice)));
}

function billing_invoice_page_render_copy($invoice)
{
    global $Dictionnary;

    $path = billing_invoice_relative_path($invoice);
    if ($path == "")
        return ("<span class='billing_invoice_no_copy'>—</span>");
    $owner = billing_entry_document_owner_codename($invoice);
    return ("<span class='billing_invoice_copy_path' title='".billing_invoice_page_e($Dictionnary["InvoiceCopySavedIn"])."'>".
        billing_invoice_page_e(($owner != "" ? $owner."/" : "").$path)."</span>");
}

function billing_invoice_page_render_draft_actions($invoice)
{
    global $Dictionnary;

    $id = (int)$invoice["id"];
    $reference = billing_next_invoice_reference();
    return (
        "<div class='billing_invoice_action_stack'>".
        "<a class='billing_invoice_view_link' target='_blank' href='/api/billing/$id/invoice'>".
            billing_invoice_page_e($Dictionnary["ViewDraftInvoice"] ?? "Voir brouillon")."</a>".
        "<form class='billing_company_draft_issue' method='put' action='/api/billing/$id/send' onsubmit='return false;'>".
            "<input type='text' name='invoice_reference' maxlength='128' value='".billing_invoice_page_e($reference)."' />".
            "<button type='button' data-issue-action='send' onclick='return billing_invoice_page_issue_company_draft(this);'>".
                billing_invoice_page_e($Dictionnary["SendInvoice"] ?? "Émettre et envoyer")."</button>".
            "<button type='button' data-issue-action='mark_sent' onclick='return billing_invoice_page_issue_company_draft(this);'>".
                billing_invoice_page_e($Dictionnary["BillingMarkInvoiceSent"] ?? "Marquer émise")."</button>".
        "</form>".
        "<form method='delete' action='/api/billing/$id/entry' onsubmit='return false;'>".
            "<button type='button' onclick='return billing_invoice_page_delete_company_draft(this);'>".
                billing_invoice_page_e($Dictionnary["Delete"] ?? "Supprimer")."</button>".
        "</form>".
        "</div>"
    );
}

function billing_invoice_page_render_actions($invoice)
{
    global $Dictionnary;

    $is_external = billing_is_external_invoice($invoice);
    $is_credit_note = billing_is_credit_note($invoice);
    $has_pdf = billing_invoice_existing_file_path($invoice) != "";
    if (empty($invoice["sent_date"]) && !$is_credit_note && !$is_external)
        return (billing_invoice_page_render_draft_actions($invoice));

    $html = "<div class='billing_invoice_action_stack'>";

    if ($is_external && !$has_pdf)
        $html .= "<span class='billing_invoice_no_copy'>".billing_invoice_page_e($Dictionnary["BillingExternalInvoiceNoPdf"])."</span>";
    else
    {
        $view_label = $is_credit_note ? $Dictionnary["BillingOpenCreditNote"] : $Dictionnary["ViewInvoice"];
        $html .= "<a class='billing_invoice_view_link' target='_blank' href='/api/billing/".
            ((int)$invoice["id"])."/invoice'>".billing_invoice_page_e($view_label)."</a>";
        $context = [
            "type" => "finance",
            "owner_user_id" => (int)($invoice["id_user"] ?? 0),
            "school_id" => (int)($invoice["id_school"] ?? 0),
        ];
        if (document_print_current_user_can_manage_context($context))
            $html .= "<form class='billing_invoice_print_form' method='put' action='/api/billing/".
                ((int)$invoice["id"])."/print' onsubmit='return silent_submitf(this);'>".
                "<input type='button' value='À imprimer' onclick='return silent_submitf(this.form);' />".
                "</form>";
    }

    $outstanding = max(0, (int)$invoice["amount"] - (int)($invoice["covered_amount"] ?? 0));
    if (!$is_credit_note && empty($invoice["deleted"]) && !empty($invoice["sent_date"]) &&
        (int)($invoice["id_organization"] ?? 0) > 0 && $outstanding > 0)
    {
        $reference = billing_invoice_document_reference($invoice);
        $html .= "<button type='button' class='billing_invoice_payment_button'".
            " data-id-organization='".(int)$invoice["id_organization"]."'".
            " data-id-school='".(int)$invoice["id_school"]."'".
            " data-reference='".billing_invoice_page_e($reference)."'".
            " data-amount='".billing_invoice_page_e(number_format($outstanding / 100, 2, '.', ''))."'".
            " onclick='return billing_invoice_page_open_payment(this);'>".
            billing_invoice_page_e($Dictionnary["RegisterPayment"] ?? "Paiement reçu")."</button>";
    }

    $capacity = max(0, (int)($invoice["credit_capacity"] ?? 0));
    if (!$is_credit_note && empty($invoice["deleted"]) && $capacity > 0)
    {
        $reference = billing_invoice_document_reference($invoice);
        $capacity_input = number_format($capacity / 100, 2, '.', '');
        $html .= "<button type='button' class='billing_invoice_credit_note_button'".
            " data-id-user='".(int)($invoice["id_user"] ?? 0)."'".
            " data-id-organization='".(int)($invoice["id_organization"] ?? 0)."'".
            " data-related-entry-id='".(int)$invoice["id"]."'".
            " data-reference='".billing_invoice_page_e($reference)."'".
            " data-credit-capacity='".billing_invoice_page_e($capacity_input)."'".
            " data-credit-capacity-label='".billing_invoice_page_e(billing_euros($capacity))."'".
            " data-billing-hidden='".(!empty($invoice["billing_hidden"]) ? "1" : "0")."'".
            " onclick='return billing_invoice_page_open_credit_note(this);'>".
            billing_invoice_page_e($Dictionnary["BillingPrepareCreditNote"])."</button>";
    }

    $html .= "</div>";
    return ($html);
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
        "raw" => fn($i) => billing_invoice_document_reference($i),
        "render" => fn($i) => billing_invoice_page_render_reference($i),
        "copyable" => true,
    ],
    [
        "name" => "piece_type",
        "label" => $Dictionnary["BillingDocumentType"],
        "type" => "select",
        "width" => "80px",
        "options" => [
            "invoice" => $Dictionnary["BillingInvoice"],
            "credit_note" => $Dictionnary["BillingCreditNote"],
        ],
        "raw" => fn($i) => billing_is_credit_note($i) ? "credit_note" : "invoice",
        "render" => fn($i) => billing_invoice_page_e(billing_is_credit_note($i) ? $Dictionnary["BillingCreditNote"] : $Dictionnary["BillingInvoice"]),
    ],
    [
        "name" => "origin",
        "label" => $Dictionnary["BillingInvoiceOrigin"],
        "type" => "select",
        "width" => "90px",
        "options" => [
            "infosphere" => $Dictionnary["BillingInvoiceOriginInfosphere"],
            "external" => $Dictionnary["BillingInvoiceOriginExternal"],
        ],
        "raw" => fn($i) => billing_is_external_invoice($i) ? "external" : "infosphere",
        "render" => fn($i) => billing_invoice_page_e(
            billing_is_external_invoice($i)
                ? $Dictionnary["BillingInvoiceOriginExternal"]
                : $Dictionnary["BillingInvoiceOriginInfosphere"]
        ),
    ],
    [
        "name" => "party",
        "label" => $Dictionnary["BillingClientBeneficiary"] ?? "Client / bénéficiaire",
        "type" => "text",
        "width" => "190px",
        "raw" => fn($i) => billing_invoice_page_party_raw($i),
        "render" => fn($i) => billing_invoice_page_render_party($i),
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
        "name" => "invoice_type",
        "label" => $Dictionnary["BillingInvoiceType"],
        "type" => "select",
        "width" => "85px",
        "options" => billing_invoice_types(),
        "raw" => fn($i) => billing_normalize_invoice_type($i["invoice_type"] ?? "school"),
        "render" => fn($i) => billing_invoice_page_e(billing_invoice_type_label($i["invoice_type"] ?? "school")),
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
        "render" => fn($i) => empty($i["sent_date"]) ? "—" : billing_invoice_page_datetime_label($i["sent_date"]),
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
            "draft" => $Dictionnary["BillingInvoiceDraft"] ?? "Brouillon",
            "issued" => $Dictionnary["BillingInvoiceIssuedOnly"],
            "credit_note" => $Dictionnary["BillingCreditNoteIssued"],
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
        "width" => "280px",
        "raw" => fn($i) => billing_entry_document_owner_codename($i)."/".billing_invoice_relative_path($i),
        "render" => fn($i) => billing_invoice_page_render_copy($i),
        "copyable" => true,
        "cell_class" => "billing_invoice_copy",
    ],
    [
        "name" => "actions",
        "label" => $Dictionnary["Actions"],
        "type" => "misc",
        "width" => "150px",
        "render" => fn($i) => billing_invoice_page_render_actions($i),
        "cell_class" => "billing_invoice_actions",
    ],
];
?>

<div id="billing_invoice_panel" class="billing_embedded_panel">
    <section class="billing_company_invoice_creator">
        <h3><?=$Dictionnary["BillingNewOrganizationInvoice"] ?? "Nouvelle facture entreprise"; ?></h3>
        <form method="post" action="/api/billing/-1/entry" onsubmit="return false;">
            <label>
                <span><?=$Dictionnary["School"] ?? "École"; ?></span>
                <select name="id_school" required>
                    <?php foreach ($billing_invoice_schools as $school) { ?>
                        <option value="<?=(int)$school["id"]; ?>"><?=billing_invoice_page_e($school["name"] ?: $school["codename"]); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span><?=$Dictionnary["BillingCustomerOrganization"] ?? "Client / organisation"; ?></span>
                <select name="id_organization" required>
                    <option value="">—</option>
                    <?php foreach ($billing_invoice_organizations as $organization) {
                        $organization_name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"]));
                        if (billing_organization_payer_kind($organization) !== "direct")
                            $organization_name .= " — ".billing_organization_payer_kind_label($organization); ?>
                        <option value="<?=(int)$organization["id"]; ?>"><?=billing_invoice_page_e($organization_name); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span><?=$Dictionnary["BillingOptionalBeneficiary"] ?? "Bénéficiaire étudiant (facultatif)"; ?></span>
                <select name="id_user">
                    <option value="0">—</option>
                    <?php foreach ($billing_invoice_students as $student) { ?>
                        <?php $student_name = trim(($student["first_name"] ?? "")." ".($student["family_name"] ?? "")); ?>
                        <option value="<?=(int)$student["id"]; ?>"><?=billing_invoice_page_e(($student_name != "" ? $student_name : $student["codename"])." — ".$student["codename"]); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="billing_company_invoice_label">
                <span><?=$Dictionnary["BillingLabel"] ?? "Libellé"; ?></span>
                <input type="text" name="label" maxlength="255" required placeholder="Prestation d'enseignement" />
            </label>
            <label>
                <span><?=$Dictionnary["BillingAmountHT"] ?? "Montant HT"; ?></span>
                <input type="text" name="amount" required placeholder="0.00" />
            </label>
            <label>
                <span><?=$Dictionnary["BillingVAT"] ?? "TVA"; ?></span>
                <select name="vat_rate">
                    <?php foreach ($billing_invoice_vat_rates as $rate => $label) { ?>
                        <option value="<?=$rate; ?>"><?=billing_invoice_page_e($label); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span><?=$Dictionnary["BillingInvoiceType"] ?? "Facturation"; ?></span>
                <select name="invoice_type">
                    <?php foreach ($billing_invoice_types as $key => $label) { ?>
                        <option value="<?=billing_invoice_page_e($key); ?>"><?=billing_invoice_page_e($label); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span><?=$Dictionnary["DueDate"] ?? "Échéance"; ?></span>
                <input type="date" name="due_date" value="<?=date('Y-m-d'); ?>" required />
            </label>
            <button type="button" onclick="return billing_invoice_page_create_company_invoice(this);">
                <?=$Dictionnary["BillingCreateDraft"] ?? "Créer le brouillon"; ?>
            </button>
        </form>
    </section>
    <p class="billing_invoice_hint"><?=$Dictionnary["BillingInvoicesHint"]; ?></p>
    <?php render_dynamic_table("billing_invoice_table", $fields, $invoices); ?>
</div>

<dialog id="billing_invoice_credit_note_dialog" class="billing_invoice_credit_note_dialog">
    <form method="post" action="/api/billing/-1/credit_note" onsubmit="return false;">
        <input type="hidden" name="id_user" />
        <input type="hidden" name="id_organization" />
        <input type="hidden" name="related_entry_id" />
        <h3><?=$Dictionnary["BillingPrepareCreditNote"]; ?></h3>
        <p>
            <?=$Dictionnary["BillingRelatedInvoice"]; ?> : <strong data-credit-note-reference></strong><br />
            <small data-credit-note-maximum></small>
        </p>
        <label>
            <span><?=$Dictionnary["BillingCreditNoteAmount"]; ?></span>
            <input type="text" name="amount" required />
        </label>
        <label>
            <span><?=$Dictionnary["BillingMovementDate"]; ?></span>
            <input type="date" name="credit_date" value="<?=date('Y-m-d'); ?>" required />
        </label>
        <label>
            <span><?=$Dictionnary["BillingCreditNoteReason"]; ?></span>
            <input type="text" name="label" />
        </label>
        <div class="billing_invoice_credit_note_dialog_actions">
            <button type="button" onclick="this.closest('dialog').close();"><?=$Dictionnary["Cancel"]; ?></button>
            <button type="button" onclick="return billing_invoice_page_submit_credit_note(this);">
                <?=$Dictionnary["BillingPrepareCreditNote"]; ?>
            </button>
        </div>
    </form>
</dialog>

<dialog id="billing_invoice_payment_dialog" class="billing_invoice_credit_note_dialog">
    <form method="post" action="/api/billing/-1/payment" onsubmit="return false;">
        <input type="hidden" name="id_organization" />
        <input type="hidden" name="id_school" />
        <h3><?=$Dictionnary["RegisterPayment"] ?? "Paiement reçu"; ?></h3>
        <p>
            <?=$Dictionnary["BillingInvoice"] ?? "Facture"; ?> :
            <strong data-payment-reference></strong>
        </p>
        <label>
            <span><?=$Dictionnary["EuroAmount"] ?? "Montant"; ?></span>
            <input type="text" name="amount" required />
        </label>
        <label>
            <span><?=$Dictionnary["BillingMovementDate"] ?? "Date"; ?></span>
            <input type="date" name="payment_date" value="<?=date('Y-m-d'); ?>" required />
        </label>
        <label>
            <span><?=$Dictionnary["TransferReference"] ?? "Référence du virement"; ?></span>
            <input type="text" name="transfer_reference" maxlength="255" />
        </label>
        <input type="hidden" name="comment" />
        <div class="billing_invoice_credit_note_dialog_actions">
            <button type="button" onclick="this.closest('dialog').close();"><?=$Dictionnary["Cancel"]; ?></button>
            <button type="button" onclick="return billing_invoice_page_submit_payment(this);">
                <?=$Dictionnary["RegisterPayment"] ?? "Paiement reçu"; ?>
            </button>
        </div>
    </form>
</dialog>

<script>
function billing_invoice_page_refresh()
{
    window.location.href = "index.php?p=BillingInvoiceMenu";
}

function billing_invoice_page_create_company_invoice(button)
{
    let form = button.closest("form");
    if (!form || (form.reportValidity && !form.reportValidity()))
        return (false);
    return (silent_submitf(form, {after_success: billing_invoice_page_refresh}));
}

function billing_invoice_page_issue_company_draft(button)
{
    let form = button.closest("form");
    if (!form)
        return (false);
    let reference = form.querySelector('[name="invoice_reference"]');
    if (!reference || !reference.value.trim())
    {
        if (reference)
            reference.focus();
        return (false);
    }
    let match = form.action.match(/\/billing\/([0-9]+)\//);
    if (!match)
        return (false);
    let action = button.getAttribute("data-issue-action") || "send";
    form.action = "/api/billing/" + match[1] + "/" + action;
    return (silent_submitf(form, {after_success: billing_invoice_page_refresh}));
}

function billing_invoice_page_delete_company_draft(button)
{
    let form = button.closest("form");
    if (!form || !window.confirm("Supprimer ce brouillon ?"))
        return (false);
    return (silent_submitf(form, {after_success: billing_invoice_page_refresh}));
}

function billing_invoice_page_open_credit_note(button)
{
    let dialog = document.getElementById("billing_invoice_credit_note_dialog");
    if (!dialog)
        return (false);
    let form = dialog.querySelector("form");
    form.querySelector("[name='id_user']").value = button.getAttribute("data-id-user") || "";
    form.querySelector("[name='id_organization']").value = button.getAttribute("data-id-organization") || "";
    form.querySelector("[name='related_entry_id']").value = button.getAttribute("data-related-entry-id") || "";
    form.querySelector("[name='amount']").value = button.getAttribute("data-credit-capacity") || "";
    form.querySelector("[name='credit_date']").value = "<?=date('Y-m-d'); ?>";
    form.querySelector("[name='label']").value = "";
    dialog.querySelector("[data-credit-note-reference]").textContent = button.getAttribute("data-reference") || "";
    dialog.querySelector("[data-credit-note-maximum]").textContent =
        "<?=$Dictionnary["BillingCreditNoteAmount"]; ?> max. : " + (button.getAttribute("data-credit-capacity-label") || "");
    dialog.setAttribute("data-billing-hidden", button.getAttribute("data-billing-hidden") || "0");
    if (typeof dialog.showModal === "function")
        dialog.showModal();
    else
        dialog.setAttribute("open", "open");
    form.querySelector("[name='amount']").focus();
    return (false);
}

function billing_invoice_page_submit_credit_note(button)
{
    let form = button.closest("form");
    let dialog = form ? form.closest("dialog") : null;
    if (!form || !dialog)
        return (false);
    if (form.reportValidity && !form.reportValidity())
        return (false);
    let id_user = form.querySelector("[name='id_user']").value;
    let hidden = dialog.getAttribute("data-billing-hidden") === "1";
    return (silent_submitf(form, {
        after_success: function()
        {
            dialog.close();
            window.location.reload();
        }
    }));
}

function billing_invoice_page_open_payment(button)
{
    let dialog = document.getElementById("billing_invoice_payment_dialog");
    if (!dialog)
        return (false);
    let form = dialog.querySelector("form");
    let reference = button.getAttribute("data-reference") || "";
    form.querySelector("[name='id_organization']").value = button.getAttribute("data-id-organization") || "";
    form.querySelector("[name='id_school']").value = button.getAttribute("data-id-school") || "";
    form.querySelector("[name='amount']").value = button.getAttribute("data-amount") || "";
    form.querySelector("[name='payment_date']").value = "<?=date('Y-m-d'); ?>";
    form.querySelector("[name='transfer_reference']").value = reference;
    form.querySelector("[name='comment']").value = "Règlement facture " + reference;
    dialog.querySelector("[data-payment-reference]").textContent = reference;
    if (typeof dialog.showModal === "function")
        dialog.showModal();
    else
        dialog.setAttribute("open", "open");
    form.querySelector("[name='amount']").focus();
    return (false);
}

function billing_invoice_page_submit_payment(button)
{
    let form = button.closest("form");
    let dialog = form ? form.closest("dialog") : null;
    if (!form || !dialog)
        return (false);
    if (form.reportValidity && !form.reportValidity())
        return (false);
    return (silent_submitf(form, {
        after_success: function()
        {
            dialog.close();
            window.location.reload();
        }
    }));
}
</script>
