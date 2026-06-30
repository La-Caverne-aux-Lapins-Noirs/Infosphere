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

$students = billing_fetch_students();
$templates = billing_fetch_templates();
$schedule_labels = billing_schedule_labels();

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

function billing_render_account($student)
{
    global $Dictionnary;

    $account = $student["account"];
    ob_start();
    ?>
    <div class="billing_account_box">
        <strong><?=billing_euros($account["balance"]); ?></strong>
        <span><?=$Dictionnary["RemainingDue"]; ?></span>
        <small><?=$Dictionnary["BillingPlannedTotal"]; ?>: <?=billing_euros($account["planned"]); ?></small>
        <small><?=$Dictionnary["Billed"]; ?>: <?=billing_euros($account["billed"]); ?></small>
        <small><?=$Dictionnary["Paid"]; ?>: <?=billing_euros($account["paid"]); ?></small>
        <?php if ($account["late"] > 0) { ?>
            <b><?=$Dictionnary["LateAmount"]; ?>: <?=billing_euros($account["late"]); ?></b>
        <?php } else if (!empty($account["billed_unpaid"]) && $account["billed_unpaid"] > 0) { ?>
            <b><?=$Dictionnary["BilledUnpaid"]; ?>: <?=billing_euros($account["billed_unpaid"]); ?></b>
        <?php } else if ($account["advance"] > 0) { ?>
            <b><?=$Dictionnary["AdvanceAmount"]; ?>: <?=billing_euros($account["advance"]); ?></b>
        <?php } else { ?>
            <small><?=$Dictionnary["UpToDate"]; ?></small>
        <?php } ?>
    </div>
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

    $month = billing_month_short_label($entry["due_date"]);
    $ref = trim((string)($entry["invoice_reference"] ?? ""));
    if ($ref == "")
        $ref = billing_invoice_default_reference($entry);

    $tooltip =
        $entry["label"]."\n".
        billing_euros($entry["amount"])."\n".
        $Dictionnary["DueDate"]." : ".billing_date_label($entry["due_date"])."\n";
    $delete_confirm = billing_e(
        $Dictionnary["ConfirmDeleteBillingEntry"]."\n".
        $entry["label"]."\n".
        billing_euros($entry["amount"])."\n".
        $Dictionnary["DueDate"]." : ".billing_date_label($entry["due_date"])
    );

    if (!empty($entry["sent_date"]))
    {
        $class = "billing_invoice_sent";
        $style = "";
        $amount = max(1, (int)$entry["amount"]);
        $covered_amount = max(0, min((int)$covered_amount, $amount));
        $tooltip .= $Dictionnary["InvoiceIssued"]." : ".billing_date_label($entry["sent_date"])."\n".
            $Dictionnary["Reference"]." : ".$entry["invoice_reference"]."\n";
        $path = billing_invoice_relative_path($entry);
        if ($path != "")
            $tooltip .= $Dictionnary["InvoiceCopySavedIn"]." : ".$path."\n";
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
        ?>
        <form
            method="delete"
            action="/api/billing/<?=$entry["id"]; ?>/entry"
            class="billing_event_form"
        >
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
        return ;
    }

    $confirm = billing_e(
        $Dictionnary["ConfirmSendBillingInvoice"]."\n".
        $Dictionnary["Reference"]." : ".$ref."\n".
        $entry["label"]."\n".
        billing_euros($entry["amount"])."\n".
        $Dictionnary["DueDate"]." : ".billing_date_label($entry["due_date"])
    );
    $tooltip .= $Dictionnary["InvoicePending"]."\n".
        $Dictionnary["Reference"]." : ".$ref;
    ?>
    <form
        method="put"
        action="/api/billing/<?=$entry["id"]; ?>/send"
        data-send-action="/api/billing/<?=$entry["id"]; ?>/send"
        data-delete-action="/api/billing/<?=$entry["id"]; ?>/entry"
        data-delete-confirm="<?=$delete_confirm; ?>"
        class="billing_event_form billing_pending_invoice_form"
    >
        <button
            type="button"
            class="billing_event billing_invoice_pending"
            data-tooltip="<?=billing_e($tooltip); ?>"
            data-invoice-reference="<?=billing_e($ref); ?>"
            data-confirm="<?=$confirm; ?>"
            onclick="return billing_open_pending_invoice_menu(this, event);"
        ><?=$month; ?></button>
        <span class="billing_pending_invoice_menu hidden">
            <button type="button" onclick="return billing_pending_invoice_send(this, event);">
                <?=$Dictionnary["SendInvoice"]; ?>
            </button>
            <button type="button" onclick="return billing_pending_invoice_delete(this, event);">
                <?=$Dictionnary["Delete"]; ?>
            </button>
        </span>
    </form>
    <?php
}

function billing_render_payment_event($payment)
{
    global $Dictionnary;

    $tooltip = billing_e(
        $Dictionnary["PaymentReceived"]."\n".
        billing_euros($payment["amount"])."\n".
        billing_date_label($payment["payment_date"])."\n".
        $Dictionnary["Reference"]." : ".$payment["transfer_reference"]
    );
    $delete_confirm = billing_e(
        $Dictionnary["ConfirmDeleteBillingPayment"]."\n".
        billing_euros($payment["amount"])."\n".
        billing_date_label($payment["payment_date"])."\n".
        $Dictionnary["Reference"]." : ".$payment["transfer_reference"]
    );
    $month = billing_month_short_label($payment["payment_date"]);
    ?>
    <form
        method="delete"
        action="/api/billing/<?=$payment["id"]; ?>/payment"
        class="billing_event_form"
    >
        <button
            type="button"
            class="billing_event billing_payment_received"
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

    $id_user = (int)$student["id"];
    $paid_entry_ids = billing_paid_entry_ids_for_user($id_user);
    $entry_coverage = billing_payment_coverage_for_user($id_user);
    $archivable_invoices = billing_paid_archivable_entries($id_user);
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
                <h4><?=$Dictionnary["AddInvoiceLine"]; ?></h4>
                <form method="post" action="/api/billing/-1/entry" onsubmit="return billing_submit_and_refresh(this);">
                    <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                    <input type="text" name="label" placeholder="<?=$Dictionnary["BillingLabel"]; ?>" />
                    <input type="text" name="amount" placeholder="<?=$Dictionnary["EuroAmount"]; ?>" />
                    <input type="date" name="due_date" value="<?=date('Y-m-d'); ?>" />
                    <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["AddBillableAmount"]; ?>" />
                </form>

                <?php if (count($templates)) { ?>
                    <h4><?=$Dictionnary["ApplyBillingTemplate"]; ?></h4>
                    <form method="post" action="/api/billing/-1/apply_template" onsubmit="return billing_submit_and_refresh(this);">
                        <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                        <select name="id_template">
                            <?php foreach ($templates as $template) { ?>
                                <option value="<?=$template["id"]; ?>">
                                    <?=billing_e(($template["school_codename"] ? $template["school_codename"]." - " : "").$template["name"]); ?>
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
                <?php } ?>

                <h4><?=$Dictionnary["AddPaymentLine"]; ?></h4>
                <form method="post" action="/api/billing/-1/payment" onsubmit="return billing_submit_and_refresh(this);">
                    <input type="hidden" name="id_user" value="<?=$id_user; ?>" />
                    <input type="text" name="amount" placeholder="<?=$Dictionnary["EuroAmount"]; ?>" />
                    <input type="date" name="payment_date" value="<?=date('Y-m-d'); ?>" />
                    <input type="text" name="transfer_reference" placeholder="<?=$Dictionnary["TransferReference"]; ?>" />
                    <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["RegisterPayment"]; ?>" />
                </form>

                <?php if (count($archivable_invoices)) { ?>
                    <h4><?=$Dictionnary["PaidInvoices"]; ?></h4>
                    <form method="put" action="/api/billing/<?=$id_user; ?>/archive_paid" onsubmit="return billing_submit_and_refresh(this);">
                        <input type="button" onclick="billing_submit_and_refresh(this);" value="<?=$Dictionnary["ArchivePaidInvoices"]; ?>" />
                    </form>
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
        "width" => "55px",
        "raw" => fn($s) => $s["id"],
        "render" => fn($s) => $s["id"],
        "copyable" => true,
    ],
    [
        "name" => "student",
        "label" => $Dictionnary["Student"],
        "type" => "text",
        "width" => "200px",
        "raw" => fn($s) => billing_student_name($s)." ".$s["codename"]." ".$s["mail"],
        "render" => function($s)
        {
            return ("<a href='index.php?p=ProfileMenu&amp;a=".(int)$s["id"]."'>".
                billing_e(billing_student_name($s))."</a><br />".
                "<small>".billing_e($s["codename"])."</small>");
        },
        "cell_class" => "billing_student_name",
    ],
    [
        "name" => "school",
        "label" => $Dictionnary["School"],
        "type" => "text",
        "width" => "100px",
        "raw" => fn($s) => $s["school_name"] ?: $s["school_codename"],
        "render" => fn($s) => billing_e($s["school_name"] ?: $s["school_codename"]),
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
        "width" => "160px",
        "raw" => fn($s) => $s["account"]["balance"],
        "render" => fn($s) => billing_render_account($s),
        "cell_class" => fn($s) => billing_account_class($s),
    ],
];
?>

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

<style><?php require (__DIR__."/style.css"); ?></style>
<script><?php require (__DIR__."/script.js"); ?></script>

<div class="fullscreen scrollable" id="billing_panel">
    <?php render_dynamic_table("billing_table", $fields, $students); ?>
</div>
