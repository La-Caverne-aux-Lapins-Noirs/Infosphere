<?php
$year = (int)date("Y");
$month = (int)date("n");
$quarter = (int)floor(($month - 1) / 3) + 1;
$quarter_start_month = (($quarter - 1) * 3) + 1;
$default_start = sprintf("%04d-%02d-01", $year, $quarter_start_month);
$default_end = date("Y-m-t", strtotime(sprintf("%04d-%02d-01", $year, $quarter_start_month + 2)));
?>
<div class="billing_export_tab">
    <section class="billing_export_card">
        <div class="billing_export_heading">
            <div>
                <h3><?=$Dictionnary["BillingAccountingExports"]; ?></h3>
                <p><?=$Dictionnary["BillingAccountingExportsHint"]; ?></p>
            </div>
        </div>

        <form method="post" action="/api/billing/-1/accounting_export" target="_blank" class="billing_export_form" data-billing-export-form>
            <div class="billing_export_presets">
                <label>
                    <span><?=$Dictionnary["BillingExportYear"]; ?></span>
                    <input type="number" name="preset_year" min="2000" max="2200" value="<?=$year; ?>" />
                </label>
                <div class="billing_export_preset_buttons">
                    <button type="button" onclick="billing_set_export_period(this.form, 'q1');">T1</button>
                    <button type="button" onclick="billing_set_export_period(this.form, 'q2');">T2</button>
                    <button type="button" onclick="billing_set_export_period(this.form, 'q3');">T3</button>
                    <button type="button" onclick="billing_set_export_period(this.form, 'q4');">T4</button>
                    <button type="button" onclick="billing_set_export_period(this.form, 's1');">S1</button>
                    <button type="button" onclick="billing_set_export_period(this.form, 's2');">S2</button>
                    <button type="button" onclick="billing_set_export_period(this.form, 'year');"><?=$Dictionnary["BillingExportWholeYear"]; ?></button>
                </div>
            </div>

            <div class="billing_export_dates">
                <label>
                    <span><?=$Dictionnary["BillingFrom"]; ?></span>
                    <input type="date" name="start_date" value="<?=billing_e($default_start); ?>" required />
                </label>
                <label>
                    <span><?=$Dictionnary["BillingTo"]; ?></span>
                    <input type="date" name="end_date" value="<?=billing_e($default_end); ?>" required />
                </label>
                <button type="submit" class="billing_export_submit">📦 <?=$Dictionnary["BillingGenerateAccountingExport"]; ?></button>
            </div>
        </form>
    </section>

    <section class="billing_export_contents">
        <h4><?=$Dictionnary["BillingExportContains"]; ?></h4>
        <div class="billing_export_tree">
            <div>
                <strong>Eleves/</strong>
                <span><?=$Dictionnary["BillingExportStudentsDescription"]; ?></span>
                <code>École / OF / CFA / Autre → Factures / Avoirs</code>
                <small>factures_et_avoirs.csv · factures.csv · avoirs.csv</small>
            </div>
            <div>
                <strong>Entreprises/</strong>
                <span><?=$Dictionnary["BillingExportEnterprisesDescription"]; ?></span>
                <code>Entreprise → Debits / Credits</code>
                <small>debits.csv · credits.csv · debits_et_credits.csv</small>
            </div>
            <div>
                <strong>tout.csv</strong>
                <span><?=$Dictionnary["BillingExportAllDescription"]; ?></span>
            </div>
        </div>
        <p class="billing_export_note"><?=$Dictionnary["BillingExportRightsHint"]; ?></p>
    </section>
</div>
