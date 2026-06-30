<?php

$templates = billing_fetch_templates();
$schools = [];
foreach (billing_managed_school_ids() as $id_school)
{
    $school = db_select_one("id, codename FROM school WHERE id = ".(int)$id_school." AND deleted IS NULL");
    if ($school)
        $schools[] = $school;
}
$schedule_labels = billing_schedule_short_labels();
function billing_t_e($str) { return (htmlspecialchars((string)$str, ENT_QUOTES)); }
?>

<h2 class="alignable_blocks"><?=$Dictionnary["BillingTemplates"]; ?></h2>
<input
    type="button"
    class="alignable_blocks"
    value="<?=$Dictionnary["Billing"]; ?>"
    onclick="document.location='index.php?p=BillingMenu';"
/>
<input
    type="button"
    class="alignable_blocks"
    value="<?=$Dictionnary["BillingInvoices"]; ?>"
    onclick="document.location='index.php?p=BillingInvoiceMenu';"
/>

<style><?php require (__DIR__."/style.css"); ?></style>

<div class="fullscreen scrollable" id="billing_template_panel">
    <section class="billing_template_add">
        <h3><?=$Dictionnary["AddBillingTemplate"]; ?></h3>
        <p class="billing_template_hint"><?=$Dictionnary["BillingTemplateTariffHint"]; ?></p>
        <form method="post" action="/api/billing/-1/template" onsubmit="return silent_submitf(this, {after_success: function(){document.location.reload();}});">
            <select name="id_school">
                <?php foreach ($schools as $school) { ?>
                    <option value="<?=$school["id"]; ?>"><?=billing_t_e($school["codename"]); ?></option>
                <?php } ?>
            </select>
            <input type="number" name="tariff_year" min="0" step="1" placeholder="<?=$Dictionnary["TariffYear"]; ?>" />
            <input type="text" name="name" placeholder="<?=$Dictionnary["Name"]; ?>" />
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
        <p class="billing_template_hint">* <?=$Dictionnary["BillingTemplateInstallmentHint"]; ?></p>
    </section>
</div>
