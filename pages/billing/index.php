<?php

if ($Position == "BillingTemplateMenu")
{
    require_once (__DIR__."/templates.php");
    return ;
}

if ($Position == "BillingInvoiceMenu")
{
    require_once (__DIR__."/invoices.php");
    return ;
}

require_once (__DIR__."/../../tools/document_sources.php");
require_once (__DIR__."/../../tools/document_print.php");
$payment_reminder_document = get_payment_reminder_document_source();
$payment_schedule_document = get_payment_schedule_document_source();

$show_hidden_billing_users = !empty($_GET["show_hidden"]);
$students = billing_fetch_students($show_hidden_billing_users);
$templates = billing_fetch_templates();
$schedule_labels = billing_schedule_labels();
$invoice_type_labels = billing_invoice_types();

function billing_e($str)
{
    return (htmlspecialchars((string)$str, ENT_QUOTES));
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
    $schedule = billing_payment_schedule_for_user($id_user);
    if (!count($schedule))
        return ("");

    $total = array_sum(array_map(fn($row) => (int)$row["remaining"], $schedule));
    $fields = [];
    $visible_count = count($schedule) > 24 ? 23 : count($schedule);
    foreach (array_slice($schedule, 0, $visible_count) as $index => $row)
    {
        $status = !empty($row["issued"])
            ? (($row["reference"] ?? "") != "" ? $row["reference"] : ($Dictionnary["BillingInvoice"] ?? "Facture"))
            : ($Dictionnary["BillingPlanned"] ?? "À facturer");
        $line = billing_date_label($row["date"])." — ".trim((string)$row["label"])." — ".billing_euros($row["remaining"])." — ".$status;
        $fields[sprintf("Line%02d", $index + 1)] = $line;
    }
    if (count($schedule) > 24)
    {
        $extra = array_sum(array_map(fn($row) => (int)$row["remaining"], array_slice($schedule, 23)));
        $fields["Line24"] = "+ ".(count($schedule) - 23)." ".($Dictionnary["BillingOtherInstallments"] ?? "autres échéances")." — ".billing_euros($extra);
    }

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
        <input type="hidden" name="fields[]" value="PaymentSchedule.GeneratedDate=<?=billing_e(datex('d/m/Y')); ?>" />
        <input type="hidden" name="fields[]" value="PaymentSchedule.TotalRemaining=<?=billing_e(billing_euros($total)); ?>" />
        <?php foreach ($fields as $name => $value) { ?>
            <input type="hidden" name="fields[]" value="PaymentSchedule.<?=billing_e($name); ?>=<?=billing_e($value); ?>" />
        <?php } ?>
        <button type="submit" class="billing_payment_schedule_button"><?=$Dictionnary["BillingPaymentSchedulePdf"]; ?></button>
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
                                $is_external = $is_invoice && !empty($movement["external"]);
                                if ($is_credit_note)
                                    $movement_label = $Dictionnary["BillingCreditNote"];
                                else if ($is_refund)
                                    $movement_label = $Dictionnary["BillingRefund"];
                                else if ($is_invoice)
                                    $movement_label = $is_external ? $Dictionnary["BillingExternalInvoice"] : $Dictionnary["BillingInvoice"];
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
                                <th><?=$Dictionnary["Amount"]; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $entry) { ?>
                                <tr>
                                    <td><?=billing_date_label($entry["due_date"]); ?></td>
                                    <td><?=billing_e($entry["label"]); ?></td>
                                    <td><?=billing_e(billing_invoice_type_label($entry["invoice_type"] ?? "school")); ?></td>
                                    <td class="billing_account_amount"><?=billing_euros($entry["amount"]); ?></td>
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

    $title = billing_e(
        $Dictionnary["BillingTariffBasis"]."\n".
        $Dictionnary["FirstCycle"]." : ".($tariff["first"]["name"] ?: $tariff["first"]["codename"])."\n".
        $Dictionnary["CurrentCycle"]." : ".($tariff["last"]["name"] ?: $tariff["last"]["codename"])."\n".
        $Dictionnary["CycleCount"]." : ".$tariff["cycle_count"]
    );

    return ("<div class='billing_tariff_year_box' data-tooltip='".$title."'>".
        "<strong>".$Dictionnary["Year"]." ".(int)$tariff["year"]."</strong>".
        "<small>".$Dictionnary["Trimester"]." ".(int)$tariff["trimester"]."</small>".
        "</div>");
}

function billing_render_invoice_event($entry, $student_id, $covered_amount = 0)
{
    global $Dictionnary;

    $credit_note = billing_is_credit_note($entry);
    $month = billing_month_short_label(!empty($entry["sent_date"]) ? $entry["sent_date"] : $entry["due_date"]);
    $amount_display = billing_euros($credit_note ? abs((int)$entry["amount"]) : (int)$entry["amount"]);
    $piece = $credit_note ? $Dictionnary["BillingCreditNote"] : $Dictionnary["BillingInvoice"];
    $tooltip = $piece."\n".$entry["label"]."\n".$amount_display."\n";
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
    $tooltip .= ($credit_note ? $Dictionnary["BillingCreditNotePending"] : $Dictionnary["InvoicePending"])."\n".
        $Dictionnary["Reference"]." : ".$Dictionnary["BillingInvoiceDraft"]."\n";
    if (!$credit_note)
        $tooltip .= $Dictionnary["BillingInvoiceType"]." : ".billing_invoice_type_label($entry["invoice_type"] ?? "school");
    $reference_label = $credit_note ? $Dictionnary["BillingCreditNoteReference"] : $Dictionnary["BillingInvoiceReference"];
    $placeholder = $credit_note ? "AV-0001" : "INF-0001";
    ?>
    <form
        method="put"
        action="/api/billing/<?=$entry["id"]; ?>/send"
        data-send-action="/api/billing/<?=$entry["id"]; ?>/send"
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
            data-reference-label="<?=billing_e($reference_label); ?>"
            onclick="return billing_open_pending_invoice_menu(this, event);"
        ><?=$month; ?></button>
        <span class="billing_pending_invoice_menu hidden">
            <label class="billing_invoice_reference_field">
                <span><?=billing_e($reference_label); ?></span>
                <input
                    type="text"
                    name="invoice_reference"
                    maxlength="128"
                    autocomplete="off"
                    placeholder="<?=$placeholder; ?>"
                    data-required-message="<?=billing_e($Dictionnary["BillingInvoiceReferenceRequired"]); ?>"
                    onclick="event.stopPropagation();"
                    onkeydown="event.stopPropagation();"
                />
                <small><?=$Dictionnary["BillingInvoiceReferenceManualHint"]; ?></small>
            </label>
            <div class="billing_pending_invoice_actions">
                <a href="/api/billing/<?=$entry["id"]; ?>/invoice" target="_blank" onclick="event.stopPropagation();">
                    <?=$credit_note ? $Dictionnary["BillingViewDraftCreditNote"] : $Dictionnary["ViewDraftInvoice"]; ?>
                </a>
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
    <form method="delete" action="/api/billing/<?=$payment["id"]; ?>/payment" class="billing_event_form">
        <button
            type="button"
            class="billing_event <?=$refund ? "billing_refund_sent" : "billing_payment_received"; ?>"
            data-tooltip="<?=$tooltip; ?>"
            data-delete-confirm="<?=$delete_confirm; ?>"
            data-delete-hint="<?=billing_e($Dictionnary["BillingDeleteSecondClickHint"]); ?>"
            onclick="return billing_select_or_confirm_delete_event(this, event);"
        ><?=$month; ?></button>
    </form>
    <?php
}


function billing_render_timeline($student)
{
    global $Dictionnary;
    global $templates;
    global $schedule_labels;
    global $invoice_type_labels;
    global $payment_reminder_document;

    $id_user = (int)$student["id"];
    $paid_entry_ids = billing_paid_entry_ids_for_user($id_user);
    $entry_coverage = billing_payment_coverage_for_user($id_user);
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
    $refundable_amount = max(0, -(int)($student["account"]["balance"] ?? 0));
    $late_invoices = [];
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
        $late_invoices[] = $entry;
    }
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
                    <?php if (count($templates)) { ?>
                        <option value="template"><?=$Dictionnary["ApplyBillingTemplate"]; ?></option>
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
                    <?php if ($payment_reminder_document !== NULL && count($late_invoices)) { ?>
                        <option value="payment_reminder"><?=$Dictionnary["BillingPaymentReminder"]; ?></option>
                    <?php } ?>
                </select>

                <div class="billing_action_choice hidden" data-billing-action="billable">
                    <form method="post" action="/api/billing/-1/entry" onsubmit="return billing_submit_and_refresh(this);">
                        <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                        <input type="text" name="label" placeholder="<?=$Dictionnary["BillingLabel"]; ?>" />
                        <select name="invoice_type">
                            <?php foreach ($invoice_type_labels as $key => $label) { ?>
                                <option value="<?=billing_e($key); ?>"><?=billing_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <input type="text" name="amount" placeholder="<?=$Dictionnary["EuroAmount"]; ?>" />
                        <input type="date" name="due_date" value="<?=date('Y-m-d'); ?>" />
                        <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["AddBillableAmount"]; ?>" />
                    </form>
                </div>

                <?php if (count($templates)) { ?>
                    <div class="billing_action_choice hidden" data-billing-action="template">
                        <form method="post" action="/api/billing/-1/apply_template" onsubmit="return billing_submit_and_refresh(this);">
                            <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                            <select name="id_template">
                                <?php foreach ($templates as $template) { ?>
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
                        <label><span><?=$Dictionnary["BillingInvoiceReference"]; ?></span><input type="text" name="invoice_reference" maxlength="128" placeholder="EXT-0001" required /></label>
                        <label><span><?=$Dictionnary["EuroAmount"]; ?></span><input type="text" name="amount" placeholder="0.00" required /></label>
                        <label><span><?=$Dictionnary["BillingInvoiceDate"]; ?></span><input type="date" name="invoice_date" value="<?=date('Y-m-d'); ?>" required /></label>
                        <label><span><?=$Dictionnary["DueDate"]; ?></span><input type="date" name="due_date" /></label>
                        <label>
                            <span><?=$Dictionnary["BillingInvoiceType"]; ?></span>
                            <select name="invoice_type">
                                <?php foreach ($invoice_type_labels as $key => $label) { ?>
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
                        <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
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

                <?php if ($payment_reminder_document !== NULL && count($late_invoices)) { ?>
                    <div class="billing_action_choice hidden" data-billing-action="payment_reminder">
                        <form method="post" action="/api/doc/0/generate" target="_blank" class="billing_payment_reminder_form" onsubmit="return billing_prepare_payment_reminder(this);">
                            <input type="hidden" name="doc_payment_reminder" value="1" />
                            <input type="hidden" name="docref_payment_reminder" value="<?=billing_e($payment_reminder_document["reference"]); ?>" />
                            <input type="hidden" name="context_bindings" value="{}" />
                            <input type="hidden" name="queue_for_print" value="0" />
                            <input type="hidden" name="print_context_type" value="finance" />
                            <input type="hidden" name="print_owner_user_id" value="<?=$id_user; ?>" />
                            <input type="hidden" name="print_school_id" value="<?=document_print_school_id_for_user($id_user); ?>" />
                            <input type="hidden" name="print_recipient_label" value="<?=billing_e(billing_student_name($student)); ?>" />
                            <input type="hidden" name="print_label" value="<?=$Dictionnary["BillingPaymentReminder"]; ?>" />
                            <input type="hidden" name="print_source_key" value="billing-payment-reminder:<?=$id_user; ?>" />
                            <input type="hidden" name="save_user_document" value="<?=is_director_for_student($id_user) ? $id_user : 0; ?>" />
                            <input type="hidden" name="fields[]" data-payment-reminder-field="InvoiceReferences" />
                            <input type="hidden" name="fields[]" data-payment-reminder-field="InvoiceLines" />
                            <input type="hidden" name="fields[]" data-payment-reminder-field="TotalOutstanding" />
                            <input type="hidden" name="fields[]" data-payment-reminder-field="OldestDueDate" />
                            <input type="hidden" name="fields[]" data-payment-reminder-field="ResponseDeadline" />
                            <input type="hidden" name="fields[]" data-payment-reminder-field="Observations" />
                            <input type="hidden" data-payment-reminder-student value="<?=$id_user; ?>" />
                            <input type="hidden" data-payment-reminder-school value="<?=billing_e($student["school_codename"]); ?>" />
                            <div class="billing_payment_reminder_invoices">
                                <?php foreach ($late_invoices as $invoice) { ?>
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

<div id="billing_page">
    <div class="billing_toolbar">
        <h2 class="alignable_blocks billing_title"><?=$Dictionnary["Billing"]; ?></h2>
        <input
            type="button"
            class="alignable_blocks"
            value="<?=$Dictionnary["BillingTemplates"]; ?>"
            onclick="document.location='index.php?p=BillingTemplateMenu';"
        />
        <input
            type="button"
            class="alignable_blocks"
            value="<?=$Dictionnary["BillingInvoices"]; ?>"
            onclick="document.location='index.php?p=BillingInvoiceMenu';"
        />
        <label class="alignable_blocks billing_show_hidden_users">
            <input
                type="checkbox"
                <?= $show_hidden_billing_users ? "checked" : ""; ?>
                onchange="billing_toggle_hidden_users(this);"
            />
            <?=$Dictionnary["BillingShowHiddenUsers"]; ?>
        </label>
    </div>

    <div class="scrollable" id="billing_panel">
        <?php render_dynamic_table("billing_table", $fields, $students); ?>
    </div>
</div>
