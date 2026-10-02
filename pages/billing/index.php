<?php

$billing_requested_tab = NULL;
if ($Position == "BillingTemplateMenu")
    $billing_requested_tab = "templates";
else if ($Position == "BillingInvoiceMenu")
    $billing_requested_tab = "issued_invoices";

require_once (__DIR__."/../../tools/document_sources.php");
require_once (__DIR__."/../../tools/document_print.php");
$payment_reminder_document = get_payment_reminder_document_source();
$organization_payment_reminder_document = get_organization_payment_reminder_document_source();
$payment_schedule_document = get_payment_schedule_document_source();

$show_hidden_billing_users = !empty($_GET["show_hidden"]);
$students = billing_fetch_students($show_hidden_billing_users);
$templates = billing_fetch_templates();
$schedule_labels = billing_schedule_labels();
$invoice_type_labels = billing_invoice_types();
$vat_rates = billing_vat_rates();
$billing_organizations = billing_fetch_organizations();

function billing_e($str)
{
    return (htmlspecialchars((string)$str, ENT_QUOTES));
}

function billing_organization_option_label($organization)
{
    $name = trim((string)($organization["localized_name"] ?? ($organization["name"] ?? ($organization["legal_name"] ?? ($organization["codename"] ?? "")))));
    if ($name == "")
        $name = (string)($organization["codename"] ?? "Organisation");
    $kind = billing_organization_payer_kind($organization);
    if ($kind !== "direct")
        $name .= " — ".billing_organization_payer_kind_label($organization);
    return ($name);
}

function billing_date_label($date)
{
    $stamp = date_to_timestamp($date);

    if ($stamp == NULL)
        return (billing_e($date));
    return (datex("d/m/Y", $stamp));
}

function billing_month_short_label($date)
{
    global $Language;

    $stamp = date_to_timestamp($date);
    if ($stamp == NULL)
        return ("---");
    $month = (int)date("n", $stamp);
    if ($Language == "fr")
        $labels = [1 => "Jan", 2 => "Fév", 3 => "Mar", 4 => "Avr", 5 => "Mai", 6 => "Jun", 7 => "Jul", 8 => "Aoû", 9 => "Sep", 10 => "Oct", 11 => "Nov", 12 => "Déc"];
    else
        $labels = [1 => "Jan", 2 => "Feb", 3 => "Mar", 4 => "Apr", 5 => "May", 6 => "Jun", 7 => "Jul", 8 => "Aug", 9 => "Sep", 10 => "Oct", 11 => "Nov", 12 => "Dec"];
    return ($labels[$month] ?? "---");
}

function billing_event_sort_date($event)
{
    if ($event["type"] == "payment")
        return (date_to_timestamp($event["payment_date"]));
    if (!empty($event["sent_date"]))
        return (date_to_timestamp($event["sent_date"]));
    return (date_to_timestamp($event["due_date"]));
}

function billing_history_cutoff_timestamp()
{
    return (strtotime("-18 months"));
}

function billing_event_history_timestamp($event)
{
    if ($event["type"] == "payment")
        return (date_to_timestamp($event["payment_date"]));
    if (!empty($event["paid_date"]))
        return (date_to_timestamp($event["paid_date"]));
    return (billing_event_sort_date($event));
}

function billing_event_is_historical($event)
{
    $stamp = billing_event_history_timestamp($event);

    if ($stamp == NULL || $stamp >= billing_history_cutoff_timestamp())
        return (false);
    if ($event["type"] != "invoice")
        return (true);
    if (billing_is_credit_note($event))
        return (true);

    // A non-archived invoice still needs attention, even if it is old.
    return (!empty($event["paid_date"]));
}

function billing_student_name($student)
{
    $name = trim(($student["first_name"] ?? "")." ".($student["family_name"] ?? ""));

    if ($name == "")
        $name = $student["codename"];
    return ($name);
}

function billing_account_position_label($balance)
{
    global $Dictionnary;

    $balance = (int)$balance;
    if ($balance > 0)
        return ($Dictionnary["BillingAccountReceivable"]);
    if ($balance < 0)
        return ($Dictionnary["BillingAccountCredit"]);
    return ($Dictionnary["BillingAccountSettled"]);
}


function billing_render_payment_schedule_form($student)
{
    global $Dictionnary;
    global $payment_schedule_document;

    if ($payment_schedule_document === NULL)
        return ("");
    $id_user = (int)$student["id"];
    $fields = billing_payment_schedule_document_fields($id_user);

    ob_start();
    ?>
    <form
        method="post"
        action="/api/doc/0/generate"
        target="_blank"
        class="billing_payment_schedule_form"
    >
        <input type="hidden" name="doc_payment_schedule" value="1" />
        <input type="hidden" name="docref_payment_schedule" value="<?=billing_e($payment_schedule_document["reference"]); ?>" />
        <input type="hidden" name="context_bindings" value='<?=billing_e(json_encode([
            "Student" => (string)$id_user,
            "School" => (string)$student["school_codename"],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>' />
        <input type="hidden" name="save_user_document" value="<?=is_director_for_student($id_user) ? $id_user : 0; ?>" />
        <input type="hidden" name="mail_payment_schedule" value="0" />
        <?php foreach ($fields as $name => $value) { ?>
            <input type="hidden" name="fields[]" value="PaymentSchedule.<?=billing_e($name); ?>=<?=billing_e($value); ?>" />
        <?php } ?>
        <button type="submit" class="billing_payment_schedule_button"><?=$Dictionnary["BillingPaymentSchedulePdf"]; ?></button>
        <button
            type="button"
            class="billing_payment_schedule_button"
            data-confirm="<?=billing_e($Dictionnary["ConfirmSendBillingPaymentSchedule"]); ?>"
            onclick="return billing_send_payment_schedule(this);"
        ><?=$Dictionnary["BillingPaymentScheduleSend"]; ?></button>
    </form>
    <?php
    return (ob_get_clean());
}

function billing_render_payment_reminder_form($student, $invoices, $organization = NULL)
{
    global $Dictionnary;
    global $payment_reminder_document;
    global $organization_payment_reminder_document;

    if (!is_array($invoices) || !count($invoices))
        return ("");
    $id_user = (int)($student["id"] ?? 0);
    if ($id_user <= 0)
        return ("");

    $organization_id = is_array($organization) ? (int)($organization["id"] ?? 0) : 0;
    if ($organization_id > 0)
    {
        if (!billing_organization_can_receive_reminder($organization) || $organization_payment_reminder_document === NULL)
            return ("");
        $document = $organization_payment_reminder_document;
        $organization_name = trim((string)($organization["localized_name"] ?? ($organization["name"] ?? ($organization["legal_name"] ?? ($organization["codename"] ?? "")))));
        if ($organization_name == "")
            $organization_name = (string)($organization["codename"] ?? ("#".$organization_id));
        $heading = sprintf($Dictionnary["BillingReminderOrganization"] ?? "Financeur : %s", $organization_name);
        $recipient_label = $organization_name;
        $source_key = "billing-payment-reminder:".$id_user.":organization:".$organization_id;
    }
    else
    {
        if ($payment_reminder_document === NULL)
            return ("");
        $document = $payment_reminder_document;
        $heading = $Dictionnary["BillingReminderFinancialResponsible"] ?? "Responsable financier";
        $finance = function_exists("document_context_relation_user")
            ? document_context_relation_user($id_user, "financial", 0) : NULL;
        $recipient_label = trim((string)($finance["identity"] ?? ($finance["Identity"] ?? "")));
        if ($recipient_label == "")
            $recipient_label = billing_student_name($student);
        $source_key = "billing-payment-reminder:".$id_user.":personal";
    }

    ob_start();
    ?>
    <form method="post" action="/api/doc/0/generate" target="_blank" class="billing_payment_reminder_form" onsubmit="return billing_prepare_payment_reminder(this);">
        <strong><?=billing_e($heading); ?></strong>
        <input type="hidden" name="doc_payment_reminder" value="1" />
        <input type="hidden" name="docref_payment_reminder" value="<?=billing_e($document["reference"]); ?>" />
        <input type="hidden" name="context_bindings" value="{}" />
        <input type="hidden" name="queue_for_print" value="0" />
        <input type="hidden" name="print_context_type" value="finance" />
        <input type="hidden" name="print_owner_user_id" value="<?=$id_user; ?>" />
        <input type="hidden" name="print_school_id" value="<?=document_print_school_id_for_user($id_user); ?>" />
        <input type="hidden" name="print_recipient_label" value="<?=billing_e($recipient_label); ?>" />
        <input type="hidden" name="print_label" value="<?=$Dictionnary["BillingPaymentReminder"]; ?>" />
        <input type="hidden" name="print_source_key" value="<?=billing_e($source_key); ?>" />
        <input type="hidden" name="save_user_document" value="<?=is_director_for_student($id_user) ? $id_user : 0; ?>" />
        <input type="hidden" name="fields[]" data-payment-reminder-field="InvoiceReferences" />
        <input type="hidden" name="fields[]" data-payment-reminder-field="InvoiceLines" />
        <input type="hidden" name="fields[]" data-payment-reminder-field="TotalOutstanding" />
        <input type="hidden" name="fields[]" data-payment-reminder-field="OldestDueDate" />
        <input type="hidden" name="fields[]" data-payment-reminder-field="ResponseDeadline" />
        <input type="hidden" name="fields[]" data-payment-reminder-field="Observations" />
        <input type="hidden" data-payment-reminder-student value="<?=$id_user; ?>" />
        <input type="hidden" data-payment-reminder-school value="<?=billing_e($student["school_codename"]); ?>" />
        <?php if ($organization_id > 0) { ?>
            <input type="hidden" data-payment-reminder-organization value="<?=$organization_id; ?>" />
        <?php } ?>
        <div class="billing_payment_reminder_invoices">
            <?php foreach ($invoices as $invoice) { ?>
                <label>
                    <input type="checkbox" data-payment-reminder-invoice data-reference="<?=billing_e($invoice["reminder_reference"]); ?>" data-label="<?=billing_e($invoice["label"]); ?>" data-outstanding="<?=(int)$invoice["outstanding_amount"]; ?>" data-due-ts="<?=(int)date_to_timestamp($invoice["due_date"]); ?>" data-due-label="<?=billing_e(billing_date_label($invoice["due_date"])); ?>" checked />
                    <span><?=billing_e($invoice["reminder_reference"]); ?> — <?=billing_euros($invoice["outstanding_amount"]); ?> — échéance <?=billing_date_label($invoice["due_date"]); ?></span>
                </label>
            <?php } ?>
        </div>
        <input type="text" data-payment-reminder-deadline placeholder="Date limite demandée (facultatif)" />
        <textarea data-payment-reminder-observations rows="2" placeholder="Observation facultative"></textarea>
        <input type="submit" value="Générer la relance de paiement" onclick="this.form.queue_for_print.value='0';" />
        <?php if (document_print_current_user_can_manage_context([
            "type" => "finance",
            "owner_user_id" => $id_user,
            "school_id" => document_print_school_id_for_user($id_user),
        ])) { ?>
            <input type="submit" value="Générer + impression" onclick="this.form.queue_for_print.value='1';" />
        <?php } ?>
    </form>
    <?php
    return (ob_get_clean());
}

function billing_render_account_dialog($student)
{
    global $Dictionnary;

    $id_user = (int)$student["id"];
    $account = $student["account"];
    $statement = $student["account_statement"] ?? [];
    $pending = [];
    foreach ($student["entries"] as $entry)
        if (empty($entry["sent_date"]) && !billing_is_credit_note($entry))
            $pending[] = $entry;

    $default_payment = "";
    if ($account["balance"] > 0)
        $default_payment = number_format($account["balance"] / 100, 2, ".", "");

    ob_start();
    ?>
    <dialog
        id="billing_account_dialog_<?=$id_user; ?>"
        class="billing_account_dialog"
        data-billing-user-id="<?=$id_user; ?>"
    >
        <div class="billing_account_dialog_header">
            <div>
                <h3><?=$Dictionnary["BillingFinancialAccount"]; ?> — <?=billing_e(billing_student_name($student)); ?></h3>
                <small><?=billing_e($student["codename"]); ?></small>
            </div>
            <button type="button" class="billing_account_close" onclick="billing_close_account_dialog(this);">×</button>
        </div>

        <div class="billing_account_summary">
            <div>
                <span><?=$Dictionnary["BillingAccountBalance"]; ?></span>
                <strong><?=billing_euros($account["balance"]); ?></strong>
                <small><?=billing_e(billing_account_position_label($account["balance"])); ?></small>
            </div>
            <div>
                <span><?=$Dictionnary["Billed"]; ?></span>
                <strong><?=billing_euros($account["billed"]); ?></strong>
            </div>
            <div>
                <span><?=$Dictionnary["Paid"]; ?></span>
                <strong><?=billing_euros($account["paid"]); ?></strong>
            </div>
            <div>
                <span><?=$Dictionnary["BillingToInvoice"]; ?></span>
                <strong><?=billing_euros($account["to_invoice"]); ?></strong>
            </div>
            <div>
                <span><?=$Dictionnary["BillingProjectedBalance"]; ?></span>
                <strong><?=billing_euros($account["projected_balance"]); ?></strong>
                <small><?=$Dictionnary["BillingProjectedBalanceHint"]; ?></small>
            </div>
        </div>

        <section class="billing_account_statement">
            <h4><?=$Dictionnary["BillingAccountStatement"]; ?></h4>
            <div class="billing_account_table_scroll">
                <table>
                    <thead>
                        <tr>
                            <th><?=$Dictionnary["BillingMovementDate"]; ?></th>
                            <th><?=$Dictionnary["BillingMovement"]; ?></th>
                            <th><?=$Dictionnary["BillingLabel"]; ?></th>
                            <th><?=$Dictionnary["BillingDebit"]; ?></th>
                            <th><?=$Dictionnary["BillingCredit"]; ?></th>
                            <th><?=$Dictionnary["BillingAccountBalance"]; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!count($statement)) { ?>
                            <tr>
                                <td colspan="6" class="billing_account_empty"><?=$Dictionnary["BillingNoAccountMovement"]; ?></td>
                            </tr>
                        <?php } ?>
                        <?php foreach ($statement as $movement) { ?>
                            <?php
                                $movement_type = $movement["type"] ?? "";
                                $is_invoice = $movement_type == "invoice";
                                $is_credit_note = $movement_type == "credit_note";
                                $is_refund = $movement_type == "refund";
                                $is_organization_payment = $movement_type == "organization_payment";
                                $is_external = $is_invoice && !empty($movement["external"]);
                                if ($is_credit_note)
                                    $movement_label = $Dictionnary["BillingCreditNote"];
                                else if ($is_refund)
                                    $movement_label = $Dictionnary["BillingRefund"];
                                else if ($is_invoice)
                                    $movement_label = $is_external ? $Dictionnary["BillingExternalInvoice"] : $Dictionnary["BillingInvoice"];
                                else if ($is_organization_payment)
                                    $movement_label = $Dictionnary["BillingOrganizationPayment"] ?? "Règlement entreprise";
                                else
                                    $movement_label = $Dictionnary["PaymentReceived"];
                                $reference = trim((string)$movement["reference"]);
                                $detail = trim((string)$movement["label"]);
                                if (!$is_invoice && !$is_credit_note && $detail == "")
                                    $detail = trim((string)$movement["comment"]);
                                $row_class = ((int)$movement["debit"] > 0) ? "billing_account_debit_row" : "billing_account_credit_row";
                            ?>
                            <tr class="<?=$row_class; ?>">
                                <td><?=billing_date_label($movement["date"]); ?></td>
                                <td>
                                    <strong><?=billing_e($movement_label); ?></strong>
                                    <?php if ($reference != "") { ?><br /><small><?=billing_e($reference); ?></small><?php } ?>
                                    <?php if (($is_external || $is_credit_note) && !empty($movement["has_pdf"])) { ?>
                                        <br /><a href="/api/billing/<?=(int)$movement["id"]; ?>/invoice" target="_blank" onclick="event.stopPropagation();"><?=$is_credit_note ? $Dictionnary["BillingOpenCreditNote"] : $Dictionnary["BillingExternalInvoiceOpenPdf"]; ?></a>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?=billing_e($detail != "" ? $detail : "—"); ?>
                                    <?php if ($is_invoice && !empty($movement["due_date"])) { ?>
                                        <br /><small><?=$Dictionnary["DueDate"]; ?> : <?=billing_date_label($movement["due_date"]); ?></small>
                                    <?php } ?>
                                    <?php if ($is_invoice) {
                                        $payer = trim((string)($movement["payer"] ?? ""));
                                        if ($payer == "") $payer = $Dictionnary["BillingFinancialResponsible"] ?? "Responsable financier";
                                    ?>
                                        <br /><small><?=billing_e(($Dictionnary["BillingInvoiceRecipient"] ?? "Destinataire")." : ".$payer); ?></small>
                                    <?php } ?>
                                </td>
                                <td class="billing_account_amount"><?=((int)$movement["debit"] > 0) ? billing_euros($movement["debit"]) : "—"; ?></td>
                                <td class="billing_account_amount"><?=((int)$movement["credit"] > 0) ? billing_euros($movement["credit"]) : "—"; ?></td>
                                <td class="billing_account_amount">
                                    <strong><?=billing_euros($movement["balance"]); ?></strong>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5"><?=$Dictionnary["BillingAccountBalance"]; ?></th>
                            <th class="billing_account_amount"><?=billing_euros($account["balance"]); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <?php if (count($pending)) { ?>
            <section class="billing_account_pending">
                <h4><?=$Dictionnary["BillingPlannedInvoices"]; ?></h4>
                <p><?=$Dictionnary["BillingPlannedInvoicesHint"]; ?></p>
                <div class="billing_account_table_scroll">
                    <table>
                        <thead>
                            <tr>
                                <th><?=$Dictionnary["DueDate"]; ?></th>
                                <th><?=$Dictionnary["BillingLabel"]; ?></th>
                                <th><?=$Dictionnary["BillingInvoiceType"]; ?></th>
                                <th><?=$Dictionnary["BillingInvoiceRecipient"] ?? "Destinataire"; ?></th>
                                <th><?=$Dictionnary["Amount"]; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $entry) { ?>
                                <tr data-billing-draft-entry-id="<?=(int)$entry["id"]; ?>">
                                    <td data-billing-draft-field="due_date"><?=billing_date_label($entry["due_date"]); ?></td>
                                    <td data-billing-draft-field="label"><?=billing_e($entry["label"]); ?></td>
                                    <td data-billing-draft-field="invoice_type"><?=billing_e(billing_invoice_type_label($entry["invoice_type"] ?? "school")); ?></td>
                                    <td><?=billing_e((int)($entry["id_organization"] ?? 0) > 0
                                        ? billing_entry_organization_name($entry)
                                        : ($Dictionnary["BillingFinancialResponsible"] ?? "Responsable financier")); ?></td>
                                    <td class="billing_account_amount" data-billing-draft-field="amount"><?=billing_euros($entry["amount"]); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php } ?>

        <section class="billing_account_quick_payment">
            <h4><?=$Dictionnary["AddPaymentLine"]; ?></h4>
            <form method="post" action="/api/billing/-1/payment" onsubmit="return billing_submit_account_payment(this);">
                <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                <label>
                    <span><?=$Dictionnary["EuroAmount"]; ?></span>
                    <input type="text" name="amount" value="<?=billing_e($default_payment); ?>" placeholder="<?=$Dictionnary["EuroAmount"]; ?>" />
                </label>
                <label>
                    <span><?=$Dictionnary["BillingMovementDate"]; ?></span>
                    <input type="date" name="payment_date" value="<?=date('Y-m-d'); ?>" />
                </label>
                <label>
                    <span><?=$Dictionnary["TransferReference"]; ?></span>
                    <input type="text" name="transfer_reference" />
                </label>
                <label class="billing_account_payment_comment">
                    <span><?=$Dictionnary["BillingComment"]; ?></span>
                    <input type="text" name="comment" />
                </label>
                <input type="button" onclick="billing_submit_account_payment(this);" value="<?=$Dictionnary["RegisterPayment"]; ?>" />
            </form>
        </section>
    </dialog>
    <?php
    return (ob_get_clean());
}

function billing_account_class($student)
{
    $account = $student["account"];

    if ($account["late"] > 0)
        return ("billing_account_late");
    if (!empty($account["billed_unpaid"]) && $account["billed_unpaid"] > 0)
        return ("billing_account_billed_unpaid");
    if ($account["advance"] > 0)
        return ("billing_account_advance");
    return ("billing_account_ok");
}

function billing_compact_account_status($account)
{
    global $Dictionnary;

    if ($account["late"] > 0)
        return ($Dictionnary["LateAmount"].": ".billing_euros($account["late"]));
    if ($account["balance"] > 0)
        return ($Dictionnary["BillingAccountReceivable"]);
    if ($account["advance"] > 0)
        return ($Dictionnary["AdvanceAmount"].": ".billing_euros($account["advance"]));
    return ($Dictionnary["BillingAccountSettledShort"] ?? $Dictionnary["BillingAccountSettled"]);
}

function billing_render_account($student)
{
    global $Dictionnary;

    $account = $student["account"];
    $id_user = (int)$student["id"];
    ob_start();
    ?>
    <div class="billing_account_box">
        <div class="billing_account_compact">
            <strong><?=billing_euros($account["balance"]); ?></strong>
            <span class="billing_account_status"><?=billing_e(billing_compact_account_status($account)); ?></span>
            <small class="billing_account_metrics"><?=$Dictionnary["Billed"]; ?>: <?=billing_euros($account["billed"]); ?> · <?=$Dictionnary["Paid"]; ?>: <?=billing_euros($account["paid"]); ?></small>
            <?php if ($account["to_invoice"] > 0) { ?>
                <small class="billing_account_to_invoice"><?=$Dictionnary["BillingToInvoice"]; ?>: <?=billing_euros($account["to_invoice"]); ?></small>
            <?php } ?>
        </div>
        <button
            type="button"
            class="billing_account_statement_button"
            title="<?=billing_e($Dictionnary["BillingAccountStatement"]); ?>"
            aria-label="<?=billing_e($Dictionnary["BillingAccountStatement"]); ?>"
            onclick="billing_open_account_dialog(<?=$id_user; ?>);"
        >&#x1F4C4;</button>
        <?=billing_render_payment_schedule_form($student); ?>
    </div>
    <?=billing_render_account_dialog($student); ?>
    <?php
    return (ob_get_clean());
}

function billing_render_tariff_year($student)
{
    global $Dictionnary;

    $tariff = $student["tariff_year"];
    if ($tariff["year"] == NULL)
        return ("<span class='billing_tariff_year_empty'>—</span>");

    $title = billing_e($Dictionnary["BillingTariffBasis"]);
    return ("<div class='billing_tariff_year_box' data-tooltip='".$title."'>".
        "<strong>".$Dictionnary["Year"]." ".(int)$tariff["year"]."</strong>".
        "</div>");
}

function billing_render_invoice_event($entry, $student_id, $covered_amount = 0)
{
    global $Dictionnary;
    global $invoice_type_labels;
    global $vat_rates;
    global $billing_organizations;

    $credit_note = billing_is_credit_note($entry);
    $month = billing_month_short_label(!empty($entry["sent_date"]) ? $entry["sent_date"] : $entry["due_date"]);
    $amount_display = billing_euros($credit_note ? abs((int)$entry["amount"]) : (int)$entry["amount"]);
    $piece = $credit_note ? $Dictionnary["BillingCreditNote"] : $Dictionnary["BillingInvoice"];
    $tooltip = $piece."\n".$entry["label"]."\n".$amount_display."\n";
    $payer = (int)($entry["id_organization"] ?? 0) > 0
        ? billing_entry_organization_name($entry)
        : ($Dictionnary["BillingFinancialResponsible"] ?? "Responsable financier");
    if ($payer != "")
        $tooltip .= ($Dictionnary["BillingInvoiceRecipient"] ?? "Destinataire")." : ".$payer."\n";
    if (!$credit_note)
        $tooltip .= $Dictionnary["DueDate"]." : ".billing_date_label($entry["due_date"])."\n";
    else if (!empty($entry["related_entry_id"]))
    {
        $related = billing_entry_with_user((int)$entry["related_entry_id"], true);
        if ($related != NULL)
            $tooltip .= $Dictionnary["BillingRelatedInvoice"]." : ".billing_invoice_document_reference($related)."\n";
    }

    $delete_confirm = billing_e(
        ($credit_note ? $Dictionnary["ConfirmDeleteBillingCreditNote"] : $Dictionnary["ConfirmDeleteBillingEntry"])."\n".
        $entry["label"]."\n".$amount_display
    );

    if (!empty($entry["sent_date"]))
    {
        $class = $credit_note ? "billing_credit_note_sent" : "billing_invoice_sent";
        $style = "";
        if ($credit_note)
        {
            $tooltip .= $Dictionnary["BillingCreditNoteIssued"]." : ".billing_date_label($entry["sent_date"])."\n".
                $Dictionnary["Reference"]." : ".$entry["invoice_reference"]."\n";
        }
        else
        {
            $amount = max(1, (int)$entry["amount"]);
            $covered_amount = max(0, min((int)$covered_amount, $amount));
            $tooltip .= $Dictionnary["InvoiceIssued"]." : ".billing_date_label($entry["sent_date"])."\n".
                $Dictionnary["Reference"]." : ".$entry["invoice_reference"]."\n".
                $Dictionnary["BillingInvoiceType"]." : ".billing_invoice_type_label($entry["invoice_type"] ?? "school")."\n";
            if (billing_is_external_invoice($entry))
                $tooltip .= $Dictionnary["BillingInvoiceOrigin"]." : ".$Dictionnary["BillingInvoiceOriginExternal"]."\n";
            if (!empty($entry["paid_date"]))
            {
                $class = "billing_invoice_paid";
                $tooltip .= $Dictionnary["InvoiceMarkedPaid"]." : ".billing_date_label($entry["paid_date"])."\n";
            }
            else if ($covered_amount > 0 && $covered_amount < $amount)
            {
                $class = "billing_invoice_partial";
                $percent = max(1, min(99, (int)round($covered_amount * 100 / $amount)));
                $style = " style='--billing-paid-percent: ".$percent."%;'";
                $tooltip .= $Dictionnary["PartiallyPaid"]." : ".billing_euros($covered_amount)." / ".billing_euros($amount)."\n";
            }
            else if (!empty($entry["paid_by_account"]))
            {
                $class = "billing_invoice_paid_ready";
                $tooltip .= $Dictionnary["InvoiceCoveredByPayments"]."\n";
            }
        }
        $path = billing_invoice_relative_path($entry);
        if ($path != "")
            $tooltip .= $Dictionnary["InvoiceCopySavedIn"]." : ".$path."\n";
        $credit_capacity = 0;
        if (!$credit_note && (int)$entry["amount"] > 0)
            $credit_capacity = max(0, (int)$entry["amount"] - billing_credit_note_total_for_entry((int)$entry["id"], false));
        if ($credit_capacity > 0)
        {
            $credit_input = number_format($credit_capacity / 100, 2, '.', '');
            $can_view = !billing_is_external_invoice($entry) || billing_invoice_existing_file_path($entry) != "";
            ?>
            <form
                method="post"
                action="/api/billing/-1/credit_note"
                data-credit-action="/api/billing/-1/credit_note"
                data-delete-action="/api/billing/<?=$entry["id"]; ?>/entry"
                data-delete-confirm="<?=$delete_confirm; ?>"
                class="billing_event_form billing_issued_invoice_form"
                onsubmit="return false;"
            >
                <input type="hidden" name="id_user" value="<?=(int)$student_id; ?>" />
                <input type="hidden" name="related_entry_id" value="<?=(int)$entry["id"]; ?>" />
                <button
                    type="button"
                    class="billing_event <?=$class; ?>"
                    data-tooltip="<?=billing_e($tooltip); ?>"<?=$style; ?>
                    onclick="return billing_open_pending_invoice_menu(this, event);"
                ><?=$month; ?></button>
                <span class="billing_pending_invoice_menu billing_issued_invoice_menu hidden">
                    <strong><?=$Dictionnary["BillingPrepareCreditNote"]; ?></strong>
                    <small>
                        <?=$Dictionnary["BillingRelatedInvoice"]; ?> : <?=billing_e(billing_invoice_document_reference($entry)); ?><br />
                        <?=$Dictionnary["BillingCreditNoteAmount"]; ?> max. : <?=billing_euros($credit_capacity); ?>
                    </small>
                    <label>
                        <span><?=$Dictionnary["BillingCreditNoteAmount"]; ?></span>
                        <input type="text" name="amount" value="<?=$credit_input; ?>" required />
                    </label>
                    <label>
                        <span><?=$Dictionnary["BillingMovementDate"]; ?></span>
                        <input type="date" name="credit_date" value="<?=date('Y-m-d'); ?>" required />
                    </label>
                    <label>
                        <span><?=$Dictionnary["BillingCreditNoteReason"]; ?></span>
                        <input type="text" name="label" />
                    </label>
                    <div class="billing_pending_invoice_actions">
                        <?php if ($can_view) { ?>
                            <a href="/api/billing/<?=$entry["id"]; ?>/invoice" target="_blank" onclick="event.stopPropagation();">
                                <?=$Dictionnary["ViewInvoice"]; ?>
                            </a>
                        <?php } ?>
                        <button type="button" onclick="return billing_prepare_credit_note_from_invoice(this, event);">
                            <?=$Dictionnary["BillingPrepareCreditNote"]; ?>
                        </button>
                        <button type="button" onclick="return billing_pending_invoice_delete(this, event);">
                            <?=$Dictionnary["Delete"]; ?>
                        </button>
                    </div>
                </span>
            </form>
            <?php
        }
        else
        {
            ?>
            <form method="delete" action="/api/billing/<?=$entry["id"]; ?>/entry" class="billing_event_form">
                <button
                    type="button"
                    class="billing_event <?=$class; ?>"
                    data-tooltip="<?=billing_e($tooltip); ?>"<?=$style; ?>
                    data-delete-confirm="<?=$delete_confirm; ?>"
                    data-delete-hint="<?=billing_e($Dictionnary["BillingDeleteSecondClickHint"]); ?>"
                    onclick="return billing_select_or_confirm_delete_event(this, event);"
                ><?=$month; ?></button>
            </form>
            <?php
        }
        return ;
    }

    $confirm = billing_e(
        ($credit_note ? $Dictionnary["ConfirmSendBillingCreditNote"] : $Dictionnary["ConfirmSendBillingInvoice"])."\n".
        $entry["label"]."\n".$amount_display
    );
    $mark_sent_confirm = !$credit_note
        ? billing_e(($Dictionnary["ConfirmMarkBillingInvoiceSent"] ?? "Confirm that this invoice was already sent manually?")."\n".
            $entry["label"]."\n".$amount_display)
        : "";
    $tooltip .= ($credit_note ? $Dictionnary["BillingCreditNotePending"] : $Dictionnary["InvoicePending"])."\n".
        $Dictionnary["Reference"]." : ".$Dictionnary["BillingInvoiceDraft"]."\n";
    if (!$credit_note)
        $tooltip .= $Dictionnary["BillingInvoiceType"]." : ".billing_invoice_type_label($entry["invoice_type"] ?? "school");
    $reference_label = $credit_note ? $Dictionnary["BillingCreditNoteReference"] : $Dictionnary["BillingInvoiceReference"];
    $suggested_reference = $credit_note ? "" : billing_next_invoice_reference();
    $placeholder = $credit_note ? "AV-0001" : $suggested_reference;
    ?>
    <form
        method="put"
        action="/api/billing/<?=$entry["id"]; ?>/send"
        data-send-action="/api/billing/<?=$entry["id"]; ?>/send"
        data-mark-sent-action="/api/billing/<?=$entry["id"]; ?>/mark_sent"
        data-preview-action="/api/billing/<?=$entry["id"]; ?>/preview"
        data-edit-action="/api/billing/<?=$entry["id"]; ?>/entry"
        data-delete-action="/api/billing/<?=$entry["id"]; ?>/entry"
        data-delete-confirm="<?=$delete_confirm; ?>"
        class="billing_event_form billing_pending_invoice_form"
        onsubmit="return false;"
    >
        <button
            type="button"
            class="billing_event <?=$credit_note ? "billing_credit_note_pending" : "billing_invoice_pending"; ?>"
            data-tooltip="<?=billing_e($tooltip); ?>"
            data-confirm="<?=$confirm; ?>"
            data-mark-sent-confirm="<?=$mark_sent_confirm; ?>"
            data-reference-label="<?=billing_e($reference_label); ?>"
            onclick="return billing_open_pending_invoice_menu(this, event);"
        ><?=$month; ?></button>
        <span class="billing_pending_invoice_menu hidden">
            <?php if (!$credit_note) { ?>
                <div class="billing_pending_invoice_edit">
                    <strong><?=$Dictionnary["BillingEditDraft"]; ?></strong>
                    <label>
                        <span><?=$Dictionnary["BillingLabel"]; ?></span>
                        <input type="text" name="label" maxlength="255" value="<?=billing_e($entry["label"]); ?>" required />
                    </label>
                    <div class="billing_pending_invoice_edit_grid">
                        <label>
                            <span><?=$Dictionnary["BillingAmountHT"]; ?></span>
                            <input type="text" name="amount" value="<?=number_format(billing_entry_amount_ht($entry) / 100, 2, '.', ''); ?>" required />
                        </label>
                        <label>
                            <span><?=$Dictionnary["BillingVAT"]; ?></span>
                            <select name="vat_rate">
                                <?php foreach ($vat_rates as $rate => $label) { ?>
                                    <option value="<?=$rate; ?>" <?=((int)$rate === billing_normalize_vat_rate($entry["vat_rate"] ?? 0) ? "selected" : ""); ?>><?=billing_e($label); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>
                            <span><?=$Dictionnary["DueDate"]; ?></span>
                            <input type="date" name="due_date" value="<?=date('Y-m-d', date_to_timestamp($entry["due_date"])); ?>" required />
                        </label>
                        <label>
                            <span><?=$Dictionnary["BillingInvoiceType"]; ?></span>
                            <select name="invoice_type">
                                <?php foreach ($invoice_type_labels as $key => $label) { ?>
                                    <option value="<?=billing_e($key); ?>" <?=($key === billing_normalize_invoice_type($entry["invoice_type"] ?? "school") ? "selected" : ""); ?>><?=billing_e($label); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>
                            <span>Destinataire entreprise</span>
                            <select name="id_organization">
                                <option value="0">Particulier / responsable financier</option>
                                <?php foreach ($billing_organizations as $organization) { ?>
                                    <option value="<?=(int)$organization["id"]; ?>" <?=((int)($entry["id_organization"] ?? 0) === (int)$organization["id"] ? "selected" : ""); ?>><?=billing_e(billing_organization_option_label($organization)); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>
                            <span>Code de routage (optionnel)</span>
                            <input type="text" name="buyer_routing_code" maxlength="255" value="<?=billing_e($entry["buyer_routing_code"] ?? ""); ?>" />
                        </label>
                    </div>
                    <button type="button" class="billing_pending_invoice_save" onclick="return billing_pending_invoice_save(this, event);">
                        <?=$Dictionnary["Save"]; ?>
                    </button>
                </div>
            <?php } ?>
            <label class="billing_invoice_reference_field">
                <span><?=billing_e($reference_label); ?></span>
                <input
                    type="text"
                    name="invoice_reference"
                    maxlength="128"
                    autocomplete="off"
                    value="<?=billing_e($suggested_reference); ?>"
                    placeholder="<?=billing_e($placeholder); ?>"
                    data-required-message="<?=billing_e($Dictionnary["BillingInvoiceReferenceRequired"]); ?>"
                    onclick="event.stopPropagation();"
                    onkeydown="event.stopPropagation();"
                />
                <small><?=$credit_note ? $Dictionnary["BillingInvoiceReferenceManualHint"] : $Dictionnary["BillingInvoiceReferenceAutoHint"]; ?></small>
            </label>
            <?php if (!$credit_note) { ?>
                <label class="billing_invoice_message_field">
                    <span><?=billing_e($Dictionnary["BillingInvoiceMailMessage"]); ?></span>
                    <textarea
                        name="message"
                        rows="4"
                        maxlength="8000"
                        placeholder="<?=billing_e($Dictionnary["BillingInvoiceMailMessagePlaceholder"]); ?>"
                        onclick="event.stopPropagation();"
                        onkeydown="event.stopPropagation();"
                    ></textarea>
                    <small><?=$Dictionnary["BillingInvoiceMailMessageHint"]; ?></small>
                </label>
            <?php } ?>
            <div class="billing_pending_invoice_actions">
                <a href="/api/billing/<?=$entry["id"]; ?>/invoice" target="_blank" onclick="event.stopPropagation();">
                    <?=$credit_note ? $Dictionnary["BillingViewDraftCreditNote"] : $Dictionnary["ViewDraftInvoice"]; ?>
                </a>
                <?php if (!$credit_note) { ?>
                    <button type="button" onclick="return billing_pending_invoice_preview(this, event);">
                        <?=$Dictionnary["BillingGenerateInvoicePreview"]; ?>
                    </button>
                <?php } ?>
                <?php if (!$credit_note) { ?>
                    <button type="button" onclick="return billing_pending_invoice_mark_sent(this, event);">
                        <?=$Dictionnary["BillingMarkInvoiceSent"] ?? "Marquer comme envoyée"; ?>
                    </button>
                <?php } ?>
                <button type="button" onclick="return billing_pending_invoice_send(this, event);">
                    <?=$credit_note ? $Dictionnary["BillingIssueCreditNote"] : $Dictionnary["SendInvoice"]; ?>
                </button>
                <button type="button" onclick="return billing_pending_invoice_delete(this, event);">
                    <?=$Dictionnary["Delete"]; ?>
                </button>
            </div>
        </span>
    </form>
    <?php
}


function billing_render_payment_event($payment)
{
    global $Dictionnary;

    $refund = (int)$payment["amount"] < 0;
    $label = $refund ? $Dictionnary["BillingRefund"] : $Dictionnary["PaymentReceived"];
    $amount = abs((int)$payment["amount"]);
    $tooltip = billing_e(
        $label."\n".
        billing_euros($amount)."\n".
        billing_date_label($payment["payment_date"])."\n".
        $Dictionnary["Reference"]." : ".$payment["transfer_reference"]
    );
    $delete_confirm = billing_e(
        ($refund ? $Dictionnary["ConfirmDeleteBillingRefund"] : $Dictionnary["ConfirmDeleteBillingPayment"])."\n".
        billing_euros($amount)."\n".
        billing_date_label($payment["payment_date"])."\n".
        $Dictionnary["Reference"]." : ".$payment["transfer_reference"]
    );
    $month = billing_month_short_label($payment["payment_date"]);
    ?>
    <form
        method="put"
        action="/api/billing/<?=$payment["id"]; ?>/payment"
        data-delete-action="/api/billing/<?=$payment["id"]; ?>/payment"
        data-delete-confirm="<?=$delete_confirm; ?>"
        class="billing_event_form billing_payment_edit_form"
        onsubmit="return false;"
    >
        <button
            type="button"
            class="billing_event <?=$refund ? "billing_refund_sent" : "billing_payment_received"; ?>"
            data-tooltip="<?=$tooltip; ?>"
            onclick="return billing_open_pending_invoice_menu(this, event);"
        ><?=$month; ?></button>
        <span class="billing_pending_invoice_menu billing_payment_edit_menu hidden">
            <strong><?=billing_e($label); ?></strong>
            <small><?=billing_euros($amount); ?> — <?=billing_date_label($payment["payment_date"]); ?></small>
            <label class="billing_payment_reference_field">
                <span><?=$Dictionnary["TransferReference"]; ?></span>
                <input
                    type="text"
                    name="transfer_reference"
                    value="<?=billing_e($payment["transfer_reference"] ?? ""); ?>"
                    onclick="event.stopPropagation();"
                    onkeydown="event.stopPropagation();"
                />
            </label>
            <div class="billing_pending_invoice_actions">
                <button type="button" onclick="return billing_update_payment_reference(this, event);"><?=$Dictionnary["Save"]; ?></button>
                <button type="button" onclick="return billing_pending_invoice_delete(this, event);"><?=$Dictionnary["Delete"]; ?></button>
            </div>
        </span>
    </form>
    <?php
}


function billing_render_timeline($student)
{
    global $Dictionnary;
    global $templates;
    global $schedule_labels;
    global $invoice_type_labels;
    global $vat_rates;
    global $payment_reminder_document;
    global $organization_payment_reminder_document;
    global $billing_organizations;

    $id_user = (int)$student["id"];
    $student_school = billing_user_main_school($id_user);
    $student_invoice_type_labels = billing_invoice_types_for_school($student_school);
    $student_templates = array_values(array_filter($templates, function($template) use ($student_school) {
        return ($student_school != NULL
            && (int)$template["id_school"] === (int)$student_school["id_school"]
            && billing_invoice_type_is_allowed_for_school($student_school, $template["invoice_type"] ?? "school"));
    }));
    $paid_entry_ids = billing_paid_entry_ids_for_student($id_user);
    $entry_coverage = billing_payment_coverage_for_student($id_user);
    $archivable_invoices = billing_paid_archivable_entries($id_user);
    $creditable_invoices = [];
    foreach ($student["entries"] as $creditable)
    {
        if (empty($creditable["sent_date"]) || billing_is_credit_note($creditable) || (int)$creditable["amount"] <= 0)
            continue ;
        $already_credited = billing_credit_note_total_for_entry((int)$creditable["id"], false);
        $capacity = max(0, (int)$creditable["amount"] - $already_credited);
        if ($capacity <= 0)
            continue ;
        $creditable["credit_capacity"] = $capacity;
        $creditable_invoices[] = $creditable;
    }
    $deductible_invoices = [];
    foreach ($student["entries"] as $deductible)
    {
        if (!empty($deductible["deleted"]) || billing_is_credit_note($deductible) ||
            (int)$deductible["amount"] <= 0)
            continue ;
        // An already issued invoice remains a historical fact. A draft from a
        // disabled activity, however, cannot be used as the paid/prepared part
        // of a new remaining schedule because it can no longer be issued.
        if (empty($deductible["sent_date"]) &&
            !billing_invoice_type_is_allowed_for_school($student_school, $deductible["invoice_type"] ?? "school"))
            continue ;
        $effective = max(0, (int)$deductible["amount"] -
            billing_credit_note_total_for_entry((int)$deductible["id"], true));
        if ($effective <= 0)
            continue ;
        $deductible["remaining_schedule_amount"] = $effective;
        $deductible_invoices[] = $deductible;
    }
    $refundable_amount = max(0, -(int)($student["account"]["balance"] ?? 0));
    $late_person_invoices = [];
    $late_organization_invoices = [];
    $organization_index = [];
    foreach ($billing_organizations as $organization)
        $organization_index[(int)$organization["id"]] = $organization;
    $now = now();
    foreach ($student["entries"] as $entry)
    {
        if (empty($entry["sent_date"]) || !empty($entry["paid_date"]))
            continue ;
        $due = date_to_timestamp($entry["due_date"] ?? "");
        if ($due == NULL || $due >= $now)
            continue ;
        $covered = (int)($entry_coverage[(int)$entry["id"]] ?? 0);
        $outstanding = max(0, (int)$entry["amount"] - $covered);
        if ($outstanding <= 0)
            continue ;
        $entry["covered_amount"] = $covered;
        $entry["outstanding_amount"] = $outstanding;
        $entry["reminder_reference"] = billing_invoice_document_reference($entry);
        $id_organization = (int)($entry["id_organization"] ?? 0);
        if ($id_organization <= 0)
            $late_person_invoices[] = $entry;
        else
        {
            if (!isset($organization_index[$id_organization]))
                continue ;
            $organization = $organization_index[$id_organization];
            if (!billing_organization_can_receive_reminder($organization))
                continue ;
            if (!isset($late_organization_invoices[$id_organization]))
                $late_organization_invoices[$id_organization] = [
                    "organization" => $organization,
                    "invoices" => [],
                ];
            $late_organization_invoices[$id_organization]["invoices"][] = $entry;
        }
    }
    $has_payment_reminders =
        ($payment_reminder_document !== NULL && count($late_person_invoices)) ||
        ($organization_payment_reminder_document !== NULL && count($late_organization_invoices));
    $events = [];
    foreach ($student["entries"] as $entry)
    {
        $entry["type"] = "invoice";
        $entry["paid_by_account"] = isset($paid_entry_ids[(int)$entry["id"]]);
        $entry["covered_amount"] = $entry_coverage[(int)$entry["id"]] ?? 0;
        $events[] = $entry;
    }
    foreach ($student["payments"] as $payment)
    {
        $payment["type"] = "payment";
        $events[] = $payment;
    }
    usort($events, function($a, $b)
    {
        $ta = billing_event_sort_date($a);
        $tb = billing_event_sort_date($b);

        if ($ta == $tb)
            return (($a["type"] == "payment") - ($b["type"] == "payment"));
        return ($ta - $tb);
    });

    $visible_events = [];
    $historical_events = [];
    foreach ($events as $event)
        if (billing_event_is_historical($event))
            $historical_events[] = $event;
        else
            $visible_events[] = $event;

    ob_start();
    ?>
    <div class="billing_action_div" data-user-id="<?=$id_user; ?>">
        <div class="billing_actions">
            <?php if (!count($visible_events) && !count($historical_events)) { ?>
                <span class="billing_empty_timeline">—</span>
            <?php } ?>
            <?php foreach ($visible_events as $event) { ?>
                <?php if ($event["type"] == "invoice") billing_render_invoice_event($event, $id_user, $event["covered_amount"] ?? 0); ?>
                <?php if ($event["type"] == "payment") billing_render_payment_event($event); ?>
            <?php } ?>
            <?php if (count($historical_events)) { ?>
                <?php
                    $closed = "+ ".count($historical_events)." ".$Dictionnary["OlderBillingEvents"];
                    $opened = "− ".$Dictionnary["HideOlderBillingEvents"];
                ?>
                <button
                    type="button"
                    class="billing_history_toggle"
                    data-closed-label="<?=billing_e($closed); ?>"
                    data-open-label="<?=billing_e($opened); ?>"
                    data-tooltip="<?=billing_e($Dictionnary["BillingOldEventsHiddenHint"]); ?>"
                    onclick="billing_toggle_history(this);"
                ><?=billing_e($closed); ?></button>
                <span class="billing_history_events hidden">
                    <?php foreach ($historical_events as $event) { ?>
                        <?php if ($event["type"] == "invoice") billing_render_invoice_event($event, $id_user, $event["covered_amount"] ?? 0); ?>
                        <?php if ($event["type"] == "payment") billing_render_payment_event($event); ?>
                    <?php } ?>
                </span>
            <?php } ?>
        </div>

        <div class="billing_action_edit">
            <button type="button" onclick="toggle_billing_action_menu(this); event.stopPropagation();">
                + <?=$Dictionnary["Add"]; ?>
            </button>

            <div class="billing_action_menu hidden">
                <select
                    class="billing_action_selector"
                    onchange="billing_select_action(this);"
                    onclick="event.stopPropagation();"
                >
                    <option value=""><?=$Dictionnary["BillingChooseAction"]; ?></option>
                    <option value="billable"><?=$Dictionnary["AddBillableAmount"]; ?></option>
                    <?php if (count($student_templates)) { ?>
                        <option value="template"><?=$Dictionnary["ApplyBillingTemplate"]; ?></option>
                    <?php } ?>
                    <?php if (count($student_templates) && count($deductible_invoices)) { ?>
                        <option value="remaining_template"><?=$Dictionnary["BillingApplyRemainingSchedule"]; ?></option>
                    <?php } ?>
                    <option value="external_invoice"><?=$Dictionnary["BillingImportExternalInvoice"]; ?></option>
                    <?php if (count($creditable_invoices)) { ?>
                        <option value="credit_note"><?=$Dictionnary["BillingPrepareCreditNote"]; ?></option>
                    <?php } ?>
                    <?php if ($refundable_amount > 0) { ?>
                        <option value="refund"><?=$Dictionnary["BillingRefund"]; ?></option>
                    <?php } ?>
                    <option value="payment"><?=$Dictionnary["RegisterPayment"]; ?></option>
                    <?php if (count($archivable_invoices)) { ?>
                        <option value="archive"><?=$Dictionnary["ArchivePaidInvoices"]; ?></option>
                    <?php } ?>
                    <?php if ($has_payment_reminders) { ?>
                        <option value="payment_reminder"><?=$Dictionnary["BillingPaymentReminder"]; ?></option>
                    <?php } ?>
                </select>

                <div class="billing_action_choice hidden" data-billing-action="billable">
                    <form method="post" action="/api/billing/-1/entry" onsubmit="return billing_submit_and_refresh(this);">
                        <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                        <input type="text" name="label" placeholder="<?=$Dictionnary["BillingLabel"]; ?>" />
                        <select name="id_organization" title="<?=$Dictionnary["BillingPayer"] ?? "Payeur"; ?>">
                            <option value="0"><?=$Dictionnary["BillingReminderFinancialResponsible"] ?? "Responsable financier"; ?></option>
                            <?php foreach ($billing_organizations as $organization) { ?>
                                <option value="<?=(int)$organization["id"]; ?>"><?=billing_e(billing_organization_option_label($organization)); ?></option>
                            <?php } ?>
                        </select>
                        <select name="invoice_type">
                            <?php foreach ($student_invoice_type_labels as $key => $label) { ?>
                                <option value="<?=billing_e($key); ?>"><?=billing_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <select name="vat_rate" title="TVA">
                            <?php foreach ($vat_rates as $rate => $label) { ?>
                                <option value="<?=$rate; ?>"><?=billing_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <input type="text" name="amount" placeholder="Montant HT (€)" />
                        <input type="date" name="due_date" value="<?=date('Y-m-d'); ?>" />
                        <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["AddBillableAmount"]; ?>" />
                    </form>
                </div>

                <?php if (count($student_templates)) { ?>
                    <div class="billing_action_choice hidden" data-billing-action="template">
                        <form method="post" action="/api/billing/-1/apply_template" onsubmit="return billing_submit_and_refresh(this);">
                            <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                            <select name="id_organization" title="<?=$Dictionnary["BillingPayer"] ?? "Payeur"; ?>">
                                <option value="0"><?=$Dictionnary["BillingReminderFinancialResponsible"] ?? "Responsable financier"; ?></option>
                                <?php foreach ($billing_organizations as $organization) { ?>
                                    <option value="<?=(int)$organization["id"]; ?>"><?=billing_e(billing_organization_option_label($organization)); ?></option>
                                <?php } ?>
                            </select>
                            <select name="id_template">
                                <?php foreach ($student_templates as $template) { ?>
                                    <option value="<?=$template["id"]; ?>">
                                        <?=billing_e(($template["school_codename"] ? $template["school_codename"]." - " : "")."[".billing_invoice_type_label($template["invoice_type"] ?? "school")."] ".$template["name"]); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <select name="schedule_type">
                                <?php foreach ($schedule_labels as $key => $label) { ?>
                                    <option value="<?=billing_e($key); ?>"><?=billing_e($label); ?></option>
                                <?php } ?>
                            </select>
                            <input type="date" name="first_due_date" value="<?=date('Y-m-d'); ?>" />
                            <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["ApplyBillingTemplate"]; ?>" />
                        </form>
                    </div>
                <?php } ?>

                <?php if (count($student_templates) && count($deductible_invoices)) { ?>
                    <div class="billing_action_choice hidden" data-billing-action="remaining_template">
                        <form
                            method="post"
                            action="/api/billing/-1/apply_template_remaining"
                            class="billing_remaining_schedule_form"
                            data-select-error="<?=billing_e($Dictionnary["BillingRemainingScheduleSelectInvoice"]); ?>"
                            onsubmit="return billing_submit_remaining_schedule(this);"
                        >
                            <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                            <select name="id_organization" title="<?=$Dictionnary["BillingPayer"] ?? "Payeur"; ?>">
                                <option value="0"><?=$Dictionnary["BillingReminderFinancialResponsible"] ?? "Responsable financier"; ?></option>
                                <?php foreach ($billing_organizations as $organization) { ?>
                                    <option value="<?=(int)$organization["id"]; ?>"><?=billing_e(billing_organization_option_label($organization)); ?></option>
                                <?php } ?>
                            </select>
                            <select name="id_template">
                                <?php foreach ($student_templates as $template) { ?>
                                    <option value="<?=$template["id"]; ?>">
                                        <?=billing_e(($template["school_codename"] ? $template["school_codename"]." - " : "")."[".billing_invoice_type_label($template["invoice_type"] ?? "school")."] ".$template["name"]); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <select name="schedule_type">
                                <?php foreach ($schedule_labels as $key => $label) { ?>
                                    <option value="<?=billing_e($key); ?>"><?=billing_e($label); ?></option>
                                <?php } ?>
                            </select>
                            <input type="date" name="first_due_date" value="<?=date('Y-m-d'); ?>" />
                            <fieldset class="billing_remaining_schedule_invoices">
                                <legend><?=$Dictionnary["BillingDeductIssuedInvoices"]; ?></legend>
                                <small><?=$Dictionnary["BillingApplyRemainingScheduleHint"]; ?></small>
                                <?php foreach ($deductible_invoices as $invoice) { ?>
                                    <label class="billing_remaining_schedule_invoice">
                                        <input
                                            type="checkbox"
                                            name="deduct_entry_<?=(int)$invoice["id"]; ?>"
                                            value="1"
                                            data-billing-deduct-entry
                                        />
                                        <span><?=billing_e(
                                            billing_invoice_document_reference($invoice)." — ".
                                            billing_date_label(!empty($invoice["sent_date"])
                                                ? $invoice["sent_date"]
                                                : $invoice["due_date"])." — ".
                                            trim((string)$invoice["label"])." — ".
                                            billing_euros($invoice["remaining_schedule_amount"])
                                        ); ?></span>
                                    </label>
                                <?php } ?>
                            </fieldset>
                            <input type="button" onclick="billing_submit_remaining_schedule(this);" value="<?=$Dictionnary["BillingApplyRemainingSchedule"]; ?>" />
                        </form>
                    </div>
                <?php } ?>

                <div class="billing_action_choice hidden" data-billing-action="external_invoice">
                    <form
                        class="billing_external_invoice_form"
                        method="post"
                        action="/api/billing/-1/external_invoice"
                        enctype="multipart/form-data"
                        data-direct-file-upload="1"
                        onsubmit="return billing_submit_external_invoice(this);"
                    >
                        <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                        <label>
                            <span><?=$Dictionnary["BillingPayer"] ?? "Payeur"; ?></span>
                            <select name="id_organization">
                                <option value="0"><?=$Dictionnary["BillingReminderFinancialResponsible"] ?? "Responsable financier"; ?></option>
                                <?php foreach ($billing_organizations as $organization) { ?>
                                    <option value="<?=(int)$organization["id"]; ?>"><?=billing_e(billing_organization_option_label($organization)); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label><span><?=$Dictionnary["BillingInvoiceReference"]; ?></span><input type="text" name="invoice_reference" maxlength="128" placeholder="EXT-0001" required /></label>
                        <label><span><?=$Dictionnary["EuroAmount"]; ?></span><input type="text" name="amount" placeholder="0.00" required /></label>
                        <label><span><?=$Dictionnary["BillingInvoiceDate"]; ?></span><input type="date" name="invoice_date" value="<?=date('Y-m-d'); ?>" required /></label>
                        <label><span><?=$Dictionnary["DueDate"]; ?></span><input type="date" name="due_date" /></label>
                        <label>
                            <span><?=$Dictionnary["BillingInvoiceType"]; ?></span>
                            <select name="invoice_type">
                                <?php foreach ($student_invoice_type_labels as $key => $label) { ?>
                                    <option value="<?=billing_e($key); ?>"><?=billing_e($label); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label><span><?=$Dictionnary["BillingLabel"]; ?></span><input type="text" name="label" placeholder="<?=$Dictionnary["BillingExternalInvoiceLabelHint"]; ?>" /></label>
                        <label class="billing_external_invoice_pdf billing_action_menu_full">
                            <span><?=$Dictionnary["BillingExternalInvoicePdf"]; ?></span>
                            <input type="file" name="invoice_pdf" accept="application/pdf,.pdf" title="<?=billing_e($Dictionnary["BillingExternalInvoicePdfHint"]); ?>" />
                        </label>
                        <label class="billing_external_invoice_paid billing_action_menu_full">
                            <input type="checkbox" name="register_payment" value="1" onchange="billing_toggle_external_invoice_payment(this);" />
                            <span><?=$Dictionnary["BillingExternalInvoiceRegisterPayment"]; ?></span>
                        </label>
                        <div class="billing_external_invoice_payment_fields billing_action_menu_full hidden" data-external-invoice-payment-fields>
                            <label><span><?=$Dictionnary["BillingMovementDate"]; ?></span><input type="date" name="payment_date" value="<?=date('Y-m-d'); ?>" /></label>
                            <label><span><?=$Dictionnary["TransferReference"]; ?></span><input type="text" name="transfer_reference" /></label>
                            <small><?=$Dictionnary["BillingExternalInvoicePaymentHint"]; ?></small>
                        </div>
                        <input type="button" onclick="billing_submit_external_invoice(this);" value="<?=$Dictionnary["BillingImportExternalInvoice"]; ?>" />
                    </form>
                </div>

                <?php if (count($creditable_invoices)) { ?>
                    <div class="billing_action_choice hidden" data-billing-action="credit_note">
                        <form method="post" action="/api/billing/-1/credit_note" onsubmit="return billing_submit_and_refresh(this);">
                            <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                            <select name="related_entry_id" required>
                                <?php foreach ($creditable_invoices as $invoice) { ?>
                                    <option value="<?=(int)$invoice["id"]; ?>"><?=billing_e(billing_invoice_document_reference($invoice)." — ".$invoice["label"]." — max. ".billing_euros($invoice["credit_capacity"])); ?></option>
                                <?php } ?>
                            </select>
                            <input type="text" name="amount" placeholder="<?=$Dictionnary["BillingCreditNoteAmount"]; ?>" required />
                            <input type="date" name="credit_date" value="<?=date('Y-m-d'); ?>" />
                            <input type="text" name="label" placeholder="<?=$Dictionnary["BillingCreditNoteReason"]; ?>" />
                            <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["BillingPrepareCreditNote"]; ?>" />
                        </form>
                    </div>
                <?php } ?>

                <?php if ($refundable_amount > 0) { ?>
                    <div class="billing_action_choice hidden" data-billing-action="refund">
                        <form method="post" action="/api/billing/-1/refund" onsubmit="return billing_submit_and_refresh(this);">
                            <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                            <input type="text" name="amount" value="<?=billing_e(number_format($refundable_amount / 100, 2, '.', '')); ?>" placeholder="<?=$Dictionnary["EuroAmount"]; ?>" />
                            <input type="date" name="payment_date" value="<?=date('Y-m-d'); ?>" />
                            <input type="text" name="transfer_reference" placeholder="<?=$Dictionnary["TransferReference"]; ?>" />
                            <input type="text" name="comment" placeholder="<?=$Dictionnary["BillingRefundReason"]; ?>" />
                            <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["BillingRegisterRefund"]; ?>" />
                        </form>
                    </div>
                <?php } ?>

                <div class="billing_action_choice hidden" data-billing-action="payment">
                    <form method="post" action="/api/billing/-1/payment" onsubmit="return billing_submit_and_refresh(this);">
                        <input type="hidden" name="id_user" value="<?=$id_user; ?>" data-payment-user />
                        <input type="hidden" name="id_organization" value="0" data-payment-organization />
                        <input type="hidden" name="id_school" value="<?=(int)($student_school["id_school"] ?? 0); ?>" />
                        <select data-billing-payment-payer onchange="billing_payment_payer_changed(this);">
                            <option value="user"><?=$Dictionnary["BillingReminderFinancialResponsible"] ?? "Responsable financier"; ?></option>
                            <?php foreach ($billing_organizations as $organization) { ?>
                                <option value="organization:<?=(int)$organization["id"]; ?>"><?=billing_e(billing_organization_option_label($organization)); ?></option>
                            <?php } ?>
                        </select>
                        <small class="billing_payment_payer_hint" data-billing-payment-payer-hint><?=billing_e($Dictionnary["BillingOrganizationPaymentAllocationHint"] ?? ""); ?></small>
                        <input type="text" name="amount" placeholder="<?=$Dictionnary["EuroAmount"]; ?>" />
                        <input type="date" name="payment_date" value="<?=date('Y-m-d'); ?>" />
                        <input type="text" name="transfer_reference" placeholder="<?=$Dictionnary["TransferReference"]; ?>" />
                        <input type="text" name="comment" placeholder="<?=$Dictionnary["BillingComment"]; ?>" />
                        <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["RegisterPayment"]; ?>" />
                    </form>
                </div>

                <?php if (count($archivable_invoices)) { ?>
                    <div class="billing_action_choice hidden" data-billing-action="archive">
                        <form method="put" action="/api/billing/<?=$id_user; ?>/archive_paid" onsubmit="return billing_submit_and_refresh(this);">
                            <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["ArchivePaidInvoices"]; ?>" />
                        </form>
                    </div>
                <?php } ?>

                <?php if ($has_payment_reminders) { ?>
                    <div class="billing_action_choice hidden" data-billing-action="payment_reminder">
                        <?=billing_render_payment_reminder_form($student, $late_person_invoices); ?>
                        <?php foreach ($late_organization_invoices as $group) { ?>
                            <?=billing_render_payment_reminder_form($student, $group["invoices"], $group["organization"]); ?>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php
    return (ob_get_clean());
}

$fields = [
    [
        "name" => "id",
        "label" => "#",
        "type" => "number",
        "width" => "65px",
        "raw" => fn($s) => $s["id"],
        "render" => function($s)
        {
            global $Dictionnary;

            $hidden = !empty($s["billing_hidden"]);
            $button_title = $hidden ? $Dictionnary["BillingShowUserHint"] : $Dictionnary["BillingHideUserHint"];
            $visibility_class = $hidden ? "billing_visibility_hidden" : "billing_visibility_visible";
            return ("<div class='billing_id_box'>".
                "<span>".(int)$s["id"]."</span>".
                "<button type='button' class='billing_user_visibility_button ".$visibility_class."' ".
                "data-user-id='".(int)$s["id"]."' data-hidden='".($hidden ? "1" : "0")."' ".
                "data-hide-title='".billing_e($Dictionnary["BillingHideUserHint"])."' ".
                "data-show-title='".billing_e($Dictionnary["BillingShowUserHint"])."' ".
                "data-hidden-label='".billing_e($Dictionnary["BillingHiddenUser"])."' ".
                "title='".billing_e($button_title)."' aria-label='".billing_e($button_title)."' ".
                "onclick='event.stopPropagation(); return billing_set_user_visibility(this);'>&#x1F441;&#xFE0E;</button>".
                "</div>");
        },
        "copyable" => true,
        "cell_class" => "billing_id_cell",
    ],
    [
        "name" => "student",
        "label" => $Dictionnary["Student"],
        "type" => "text",
        "width" => "200px",
        "raw" => fn($s) => billing_student_name($s)." ".$s["codename"]." ".$s["mail"]." ".($s["school_name"] ?: $s["school_codename"]),
        "render" => function($s)
        {
            global $Dictionnary;

            $hidden = !empty($s["billing_hidden"]);
            $badge = $hidden ? "<span class='billing_hidden_badge'>".billing_e($Dictionnary["BillingHiddenUser"])."</span>" : "";
            $school = $s["school_name"] ?: $s["school_codename"];
            return ("<a href='index.php?p=ProfileMenu&amp;a=".(int)$s["id"]."'>".
                billing_e(billing_student_name($s))."</a><br />".
                "<small>".billing_e($s["codename"])."</small><br />".
                "<small class='billing_student_school'>".billing_e($school)."</small>".$badge);
        },
        "cell_class" => fn($s) => "billing_student_name".(!empty($s["billing_hidden"]) ? " billing_hidden_user" : ""),
    ],
    [
        "name" => "tariff_year",
        "label" => $Dictionnary["TariffYear"],
        "type" => "number",
        "width" => "110px",
        "raw" => fn($s) => $s["tariff_year"]["index"] ?? -1,
        "render" => fn($s) => billing_render_tariff_year($s),
        "cell_class" => "billing_tariff_year",
    ],
    [
        "name" => "timeline",
        "label" => $Dictionnary["BillingTimeline"],
        "type" => "misc",
        "width" => "auto",
        "render" => fn($s) => billing_render_timeline($s),
        "cell_class" => "billing_timeline_cell",
    ],
    [
        "name" => "balance",
        "label" => $Dictionnary["Account"],
        "type" => "number",
        "width" => "260px",
        "raw" => fn($s) => $s["account"]["balance"],
        "render" => fn($s) => billing_render_account($s),
        "cell_class" => fn($s) => billing_account_class($s),
    ],
];
?>

<style><?php require (__DIR__."/style.css"); ?></style>
<script><?php require (__DIR__."/script.js"); ?></script>

<?php
$billing_tabs = [
    $Dictionnary["BillingStudentInvoices"] => __DIR__."/students_tab.php",
    $Dictionnary["BillingStudentCreditNotes"] => __DIR__."/credit_notes_tab.php",
    $Dictionnary["BillingInvoices"] => __DIR__."/invoices.php",
    $Dictionnary["BillingTemplates"] => __DIR__."/templates.php",
    $Dictionnary["BillingEnterpriseDebits"] => __DIR__."/organization_entries_tab.php",
    $Dictionnary["BillingEnterpriseCredits"] => __DIR__."/organization_entries_tab.php",
    ($Dictionnary["BillingBankImport"] ?? "Import bancaire") => __DIR__."/bank_import_tab.php",
    $Dictionnary["BillingAccountingExports"] => __DIR__."/exports_tab.php",
    ($Dictionnary["BillingElectronicInvoicing"] ?? "Facturation électronique") => __DIR__."/electronic_invoicing_tab.php",
];
$billing_tab_data = [
    NULL,
    NULL,
    NULL,
    NULL,
    ["type" => "debit"],
    ["type" => "credit"],
    NULL,
    NULL,
    NULL,
];

$billing_default_tab = $Dictionnary["BillingStudentInvoices"];
if ($billing_requested_tab == "issued_invoices")
    $billing_default_tab = $Dictionnary["BillingInvoices"];
else if ($billing_requested_tab == "templates")
    $billing_default_tab = $Dictionnary["BillingTemplates"];

if ($billing_requested_tab != NULL)
{
    $billing_requested_hash = md5($billing_default_tab);
?>
<script>localStorage.setItem("billing-accounting-tabs", <?=json_encode($billing_requested_hash); ?>);</script>
<?php
}
?>
<div id="billing_page">
    <div class="billing_toolbar">
        <h2 class="alignable_blocks billing_title"><?=$Dictionnary["Billing"]; ?></h2>
    </div>

    <?php tabpanel(
        $billing_tabs,
        "billing-accounting-tabs",
        $billing_default_tab,
        "billing_tab_button",
        "billing_tab_content",
        $billing_tab_data
    ); ?>
</div>
