<?php

$templates = billing_fetch_templates();
$schools = [];
foreach (billing_managed_school_ids() as $id_school)
{
    $school = db_select_one("id, codename, is_school, is_of, is_cfa FROM school WHERE id = ".(int)$id_school." AND deleted IS NULL");
    if ($school)
        $schools[] = $school;
}
$schedule_labels = billing_schedule_short_labels();
$invoice_type_labels = billing_invoice_types();
$vat_rates = billing_vat_rates();
function billing_t_e($str) { return (htmlspecialchars((string)$str, ENT_QUOTES)); }
?>

<div id="billing_template_panel" class="billing_embedded_panel">
    <section class="billing_template_add">
        <h3><?=$Dictionnary["AddBillingTemplate"]; ?></h3>
        <p class="billing_template_hint"><?=$Dictionnary["BillingTemplateTariffHint"]; ?></p>
        <form method="post" action="/api/billing/-1/template" onsubmit="return silent_submitf(this, {after_success: function(){document.location.reload();}});">
            <select name="id_school" onchange="billing_template_update_invoice_types(this.form);">
                <?php foreach ($schools as $school) { ?>
                    <?php $allowed_types = array_keys(billing_invoice_types_for_school($school)); ?>
                    <option
                        value="<?=$school["id"]; ?>"
                        data-invoice-types="<?=billing_t_e(implode(",", $allowed_types)); ?>"
                    ><?=billing_t_e($school["codename"]); ?></option>
                <?php } ?>
            </select>
            <input type="number" name="tariff_year" min="0" step="1" placeholder="<?=$Dictionnary["TariffYear"]; ?>" />
            <input type="text" name="name" placeholder="<?=$Dictionnary["Name"]; ?>" />
            <select name="invoice_type">
                <?php foreach ($invoice_type_labels as $key => $label) { ?>
                    <option value="<?=billing_t_e($key); ?>"><?=billing_t_e($label); ?></option>
                <?php } ?>
            </select>
            <select name="vat_rate" title="TVA appliquée aux montants HT du modèle">
                <?php foreach ($vat_rates as $rate => $label) { ?>
                    <option value="<?=$rate; ?>"><?=billing_t_e($label); ?></option>
                <?php } ?>
            </select>
            <input type="text" name="registration_fee" placeholder="<?=$Dictionnary["RegistrationFees"]; ?>" />
            <input type="text" name="amount_once" placeholder="<?=$Dictionnary["BillingScheduleOnceShort"]; ?>" />
            <input type="text" name="amount_twice" placeholder="<?=$Dictionnary["BillingScheduleTwiceShort"]; ?>" />
            <input type="text" name="amount_four" placeholder="<?=$Dictionnary["BillingScheduleFourShort"]; ?>" />
            <input type="text" name="amount_twelve" placeholder="<?=$Dictionnary["BillingScheduleTwelveShort"]; ?>" />
            <input type="button" onclick="silent_submit(this);" value="<?=$Dictionnary["Add"]; ?>" />
        </form>
    </section>

    <section class="billing_template_table">
        <table>
            <tr>
                <th><?=$Dictionnary["School"]; ?></th>
                <th><?=$Dictionnary["TariffYear"]; ?></th>
                <th><?=$Dictionnary["Name"]; ?></th>
                <th><?=$Dictionnary["BillingInvoiceType"]; ?></th>
                <th>TVA</th>
                <th><?=$Dictionnary["RegistrationFees"]; ?></th>
                <th><?=$schedule_labels["once"]; ?></th>
                <th><?=$schedule_labels["twice_two_months"]; ?></th>
                <th><?=$schedule_labels["four_with_gap"]; ?></th>
                <th><?=$schedule_labels["twelve_monthly"]; ?></th>
                <th><?=$Dictionnary["Actions"]; ?></th>
            </tr>
            <?php foreach ($templates as $template) { ?>
                <tr>
                    <td><?=billing_t_e($template["school_name"] ?: $template["school_codename"]); ?></td>
                    <td><?=((int)$template["tariff_year"] > 0 ? $Dictionnary["Year"]." ".(int)$template["tariff_year"] : "—"); ?></td>
                    <td><?=billing_t_e($template["name"]); ?></td>
                    <td><?=billing_t_e(billing_invoice_type_label($template["invoice_type"] ?? "school")); ?></td>
                    <td><?=billing_t_e((billing_vat_rates()[billing_normalize_vat_rate($template["vat_rate"] ?? 0)] ?? "0 %")); ?></td>
                    <td><?=billing_euros($template["registration_fee"]); ?></td>
                    <td><?=billing_euros($template["amount_once"]); ?></td>
                    <td><?=billing_euros($template["amount_twice"]); ?></td>
                    <td><?=billing_euros($template["amount_four"]); ?></td>
                    <td><?=billing_euros($template["amount_twelve"]); ?></td>
                    <td>
                        <form method="delete" action="/api/billing/-1/template/<?=$template["id"]; ?>" onsubmit="return window.confirm('<?=$Dictionnary["Confirm"]; ?>') && silent_submitf(this, {after_success: function(){document.location.reload();}});">
                            <input type="button" onclick="silent_submit(this);" value="<?=$Dictionnary["Delete"]; ?>" />
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </table>
        <p class="billing_template_hint">* <?=$Dictionnary["BillingTemplateInstallmentHint"]; ?> Les montants du modèle sont saisis HT ; à 20 %, Infosphère ajoute la TVA au montant dû.</p>
    </section>
</div>
<script>
function billing_template_update_invoice_types(form)
{
    if (!form || !form.elements.id_school || !form.elements.invoice_type)
        return ;
    var school = form.elements.id_school;
    var option = school.options[school.selectedIndex];
    var allowed = (option ? option.getAttribute('data-invoice-types') : '').split(',').filter(Boolean);
    var type = form.elements.invoice_type;
    Array.prototype.forEach.call(type.options, function (candidate) {
        var visible = allowed.indexOf(candidate.value) !== -1;
        candidate.hidden = !visible;
        candidate.disabled = !visible;
    });
    if (allowed.indexOf(type.value) === -1 && allowed.length)
        type.value = allowed[0];
}
window.setTimeout(function () {
    document.querySelectorAll('#billing_template_panel form').forEach(function (form) {
        if (form.elements.id_school && form.elements.invoice_type)
            billing_template_update_invoice_types(form);
    });
}, 0);
</script>
