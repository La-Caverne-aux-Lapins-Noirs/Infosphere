<?php
$bank_transactions = billing_fetch_bank_transactions(750);
$bank_students = billing_bank_students();
$bank_organizations = billing_fetch_organizations();
$bank_invoices = billing_bank_invoices();
$bank_existing_payments = billing_bank_existing_payments_for_suggestions();
$bank_existing_entries = billing_bank_existing_entries_for_suggestions();
$bank_managed_schools = [];
foreach (billing_managed_school_ids() as $id_school)
{
    $school = db_select_one("id, codename FROM school WHERE id = ".(int)$id_school." AND deleted IS NULL");
    if ($school)
        $bank_managed_schools[] = $school;
}
$bank_suggestions = [];
$bank_extra_options = [];
foreach ($bank_transactions as $transaction)
{
    if (($transaction["status"] ?? "pending") != "pending")
        continue ;
    $suggestion = billing_bank_suggest_association_cached(
        $transaction,
        $bank_students,
        $bank_organizations,
        $bank_invoices,
        $bank_existing_payments,
        $bank_existing_entries
    );
    if ($suggestion !== NULL)
    {
        $bank_suggestions[(int)$transaction["id"]] = $suggestion;
        if (!empty($suggestion["existing"]))
            $bank_extra_options[$suggestion["kind"].":".$suggestion["id"]] = $suggestion;
    }
}
?>
<script src="script/ext/csv.js"></script>
<div class="billing_bank_tab">
    <div class="billing_bank_top_grid">
        <section class="billing_bank_card">
            <h3><?=$Dictionnary["BillingBankImport"] ?? "Import bancaire"; ?></h3>
            <p><?=$Dictionnary["BillingBankImportHint"] ?? "Importez un CSV bancaire puis rapprochez chaque ligne d'un élève, d'une facture ou d'une entreprise."; ?></p>
            <?php if (!count($bank_managed_schools)) { ?>
                <p class="billing_empty_tab"><?=$Dictionnary["BillingNoManagedSchool"]; ?></p>
            <?php } else { ?>
                <form
                    method="post"
                    action="/api/billing/-1/bank_import"
                    enctype="multipart/form-data"
                    data-direct-file-upload="1"
                    onsubmit="return billing_bank_import(this);"
                    class="billing_bank_import_form"
                >
                    <label>
                        <span><?=$Dictionnary["School"]; ?></span>
                        <select name="id_school" required>
                            <?php foreach ($bank_managed_schools as $school) { ?>
                                <option value="<?=(int)$school["id"]; ?>"><?=billing_e($school["codename"]); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <label class="billing_bank_file_label">
                        <span><?=$Dictionnary["BillingBankCsvFile"] ?? "Fichier CSV"; ?></span>
                        <input type="file" name="bank_csv" accept=".csv,text/csv" required onchange="billing_bank_preview_csv(this);" />
                    </label>
                    <button type="submit">⇧ <?=$Dictionnary["BillingBankAnalyze"] ?? "Importer et analyser"; ?></button>
                </form>
                <small class="billing_bank_hint"><?=$Dictionnary["BillingBankCsvHint"] ?? "CSV avec ligne d'en-tête. Le format bancaire Date opération / Libellé opération / Montant opération est reconnu."; ?></small>
                <div class="billing_bank_csv_preview hidden" data-bank-csv-preview>
                    <strong><?=$Dictionnary["BillingBankPreview"] ?? "Aperçu du fichier"; ?></strong>
                    <div class="billing_bank_csv_preview_scroll"><table><thead></thead><tbody></tbody></table></div>
                </div>
            <?php } ?>
        </section>

        <section class="billing_bank_card billing_supplier_invoice_card">
            <h3><?=$Dictionnary["BillingSupplierInvoiceImport"] ?? "Importer une facture fournisseur"; ?></h3>
            <p><?=$Dictionnary["BillingSupplierInvoiceImportHint"] ?? "Enregistrez la facture maintenant ; Infosphère peut la rapprocher d'une opération bancaire déjà importée."; ?></p>
            <?php if (count($bank_managed_schools) && count($bank_organizations)) { ?>
                <form
                    method="post"
                    action="/api/billing/-1/supplier_invoice"
                    enctype="multipart/form-data"
                    data-direct-file-upload="1"
                    data-bank-supplier-form
                    onsubmit="return billing_bank_supplier_submit(this);"
                    oninput="billing_bank_find_supplier_matches(this);"
                >
                    <div class="billing_supplier_fields">
                        <label><span><?=$Dictionnary["School"]; ?></span><select name="id_school" required>
                            <?php foreach ($bank_managed_schools as $school) { ?><option value="<?=(int)$school["id"]; ?>"><?=billing_e($school["codename"]); ?></option><?php } ?>
                        </select></label>
                        <label><span><?=$Dictionnary["Enterprise"]; ?></span><select name="id_organization" required>
                            <?php foreach ($bank_organizations as $organization) { $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"])); ?>
                                <option value="<?=(int)$organization["id"]; ?>"><?=billing_e($name); ?> — <?=billing_e($organization["codename"]); ?></option>
                            <?php } ?>
                        </select></label>
                        <label><span><?=$Dictionnary["BillingSupplierInvoiceDate"] ?? "Date facture"; ?></span><input type="date" name="invoice_date" value="<?=date('Y-m-d'); ?>" required /></label>
                        <label><span><?=$Dictionnary["EuroAmount"]; ?></span><input type="text" name="amount" placeholder="0.00" required /></label>
                        <label><span><?=$Dictionnary["Reference"]; ?></span><input type="text" name="reference" maxlength="255" /></label>
                        <label><span><?=$Dictionnary["BillingLabel"]; ?></span><input type="text" name="label" maxlength="255" /></label>
                        <label class="billing_supplier_document"><span><?=$Dictionnary["BillingSupportingDocument"]; ?></span><input type="file" name="document" accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" required /></label>
                        <input type="hidden" name="id_bank_transaction" value="0" />
                    </div>
                    <div class="billing_supplier_matches" data-supplier-matches>
                        <small><?=$Dictionnary["BillingSupplierMatchHint"] ?? "Saisissez le montant et la date pour rechercher un débit bancaire compatible."; ?></small>
                    </div>
                    <div class="billing_supplier_actions">
                        <button type="button" onclick="billing_bank_create_organization_for_supplier(this);">＋ <?=$Dictionnary["BillingBankCreateOrganization"] ?? "Créer une organisation"; ?></button>
                        <button type="submit">📄 <?=$Dictionnary["BillingSupplierInvoiceAdd"] ?? "Enregistrer la facture"; ?></button>
                    </div>
                </form>
            <?php } else { ?>
                <p class="billing_empty_tab"><?=$Dictionnary["BillingNoEnterprise"] ?? "Aucune entreprise"; ?></p>
            <?php } ?>
        </section>
    </div>

    <section class="billing_bank_reconciliation">
        <div class="billing_bank_reconciliation_header">
            <div>
                <h3><?=$Dictionnary["BillingBankReconciliation"] ?? "Rapprochement bancaire"; ?></h3>
                <p><?=$Dictionnary["BillingBankReconciliationHint"] ?? "Les lignes grisées sont déjà rapprochées ou correspondent fortement à un mouvement existant."; ?></p>
            </div>
            <div class="billing_bank_filters">
                <input type="search" data-bank-search placeholder="<?=$Dictionnary["BillingSearch"] ?? "Rechercher"; ?>" oninput="billing_bank_filter_rows();" />
                <select data-bank-status onchange="billing_bank_filter_rows();">
                    <option value="all" selected><?=$Dictionnary["All"] ?? "Tous"; ?></option>
                    <option value="pending"><?=$Dictionnary["BillingBankPending"] ?? "À rapprocher"; ?></option>
                    <option value="matched"><?=$Dictionnary["BillingBankMatched"] ?? "Rapprochés"; ?></option>
                    <option value="ignored"><?=$Dictionnary["BillingBankIgnoredPlural"] ?? "Ignorés"; ?></option>
                </select>
            </div>
        </div>

        <datalist id="billing_bank_associations">
            <?php foreach ($bank_invoices as $invoice) {
                $name = (int)($invoice["id_organization"] ?? 0) > 0
                    ? trim((string)($invoice["organization_name"] ?? $invoice["organization_codename"] ?? ""))
                    : trim(($invoice["first_name"] ?? "")." ".($invoice["family_name"] ?? ""));
                if ($name == "") $name = $invoice["codename"] ?? "";
                $value = "Facture ".$invoice["invoice_reference"]." — ".$name;
            ?>
                <option value="<?=billing_e($value); ?>" data-kind="invoice" data-id="<?=(int)$invoice["id"]; ?>"></option>
            <?php } ?>
            <?php foreach ($bank_students as $student) { $name = trim(($student["first_name"] ?? "")." ".($student["family_name"] ?? "")); if ($name == "") $name = $student["codename"]; $value = "Élève — ".$name." [".$student["codename"]."] — ".$student["school_codename"]; ?>
                <option value="<?=billing_e($value); ?>" data-kind="student" data-id="<?=(int)$student["id"]; ?>"></option>
            <?php } ?>
            <?php foreach ($bank_organizations as $organization) { $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"])); $value = "Entreprise — ".$name." [".$organization["codename"]."]"; ?>
                <option value="<?=billing_e($value); ?>" data-kind="organization" data-id="<?=(int)$organization["id"]; ?>"></option>
            <?php } ?>
            <?php foreach ($bank_existing_entries as $entry) { $name = $entry["name"] ?: ($entry["legal_name"] ?: $entry["codename"]); $value = (($entry["movement_type"] == "debit") ? "Débit" : "Crédit")." existant — ".$name." — ".billing_date_label($entry["movement_date"])." — ".billing_euros((int)$entry["amount"]); if (!empty($entry["reference"])) $value .= " — ".$entry["reference"]; ?>
                <option value="<?=billing_e($value); ?>" data-kind="organization_entry" data-id="<?=(int)$entry["id"]; ?>"></option>
            <?php } ?>
            <?php foreach ($bank_existing_payments as $payment) {
                $is_organization = (int)($payment["id_organization"] ?? 0) > 0;
                $name = $is_organization
                    ? trim((string)($payment["organization_name"] ?? $payment["organization_codename"] ?? ""))
                    : trim(($payment["first_name"] ?? "")." ".($payment["family_name"] ?? ""));
                if ($name == "") $name = $payment["codename"] ?? "";
                $value = (((int)$payment["amount"] < 0) ? "Remboursement" : ($is_organization ? "Règlement client" : "Paiement"))." existant — ".$name." — ".billing_date_label($payment["payment_date"])." — ".billing_euros(abs((int)$payment["amount"]));
                if (!empty($payment["transfer_reference"])) $value .= " — ".$payment["transfer_reference"];
            ?>
                <option value="<?=billing_e($value); ?>" data-kind="payment" data-id="<?=(int)$payment["id"]; ?>"></option>
            <?php } ?>
            <?php foreach ($bank_extra_options as $option) { ?>
                <option value="<?=billing_e($option["label"]); ?>" data-kind="<?=billing_e($option["kind"]); ?>" data-id="<?=(int)$option["id"]; ?>"></option>
            <?php } ?>
        </datalist>

        <div class="billing_bank_table_scroll">
            <table class="billing_bank_table">
                <thead><tr>
                    <th><?=$Dictionnary["BillingStatus"] ?? "État"; ?></th>
                    <th><?=$Dictionnary["BillingMovementDate"]; ?></th>
                    <th><?=$Dictionnary["BillingLabel"]; ?></th>
                    <th><?=$Dictionnary["EuroAmount"]; ?></th>
                    <th><?=$Dictionnary["BillingBankAssociation"] ?? "Association"; ?></th>
                    <th><?=$Dictionnary["BillingSupportingDocument"]; ?></th>
                    <th><?=$Dictionnary["Action"] ?? "Action"; ?></th>
                </tr></thead>
                <tbody>
                <?php if (!count($bank_transactions)) { ?><tr><td colspan="7" class="billing_empty_tab"><?=$Dictionnary["BillingBankNoTransaction"] ?? "Aucune opération bancaire importée."; ?></td></tr><?php } ?>
                <?php foreach ($bank_transactions as $transaction) {
                    $id_transaction = (int)$transaction["id"];
                    $status = $transaction["status"] ?? "pending";
                    $suggestion = $bank_suggestions[$id_transaction] ?? NULL;
                    $existing = $status == "pending" && !empty($suggestion["existing"]);
                    $row_class = $status == "matched" || $status == "ignored" || $existing ? " billing_bank_known" : ($suggestion !== NULL ? " billing_bank_suggested" : "");
                    $association_value = $suggestion["label"] ?? "";
                    $association_kind = $suggestion["kind"] ?? "";
                    $association_id = (int)($suggestion["id"] ?? 0);
                    $search = billing_bank_normalize_text(($transaction["label"] ?? "")." ".($transaction["pointage"] ?? "")." ".($transaction["bank_comment"] ?? "")." ".$association_value);
                ?>
                    <tr class="billing_bank_row<?=$row_class; ?>" data-bank-row data-status="<?=billing_e($status); ?>" data-search="<?=billing_e($search); ?>" data-bank-id="<?=$id_transaction; ?>" data-bank-school="<?=(int)$transaction["id_school"]; ?>" data-bank-amount="<?=(int)$transaction["amount"]; ?>" data-bank-date="<?=billing_e(date('Y-m-d', date_to_timestamp($transaction["operation_date"]))); ?>" data-bank-label="<?=billing_e($transaction["label"]); ?>">
                        <td class="billing_bank_status_cell">
                            <?php if ($status == "matched") { ?><span class="billing_bank_badge matched">✓ <?=$Dictionnary["BillingBankMatched"] ?? "Rapproché"; ?></span>
                            <?php } else if ($status == "ignored") { ?><span class="billing_bank_badge ignored">— <?=$Dictionnary["BillingBankIgnored"] ?? "Ignoré"; ?></span>
                            <?php } else if ($existing) { ?><span class="billing_bank_badge known">≈ <?=$Dictionnary["BillingBankExistingMovement"] ?? "Déjà saisi ?"; ?></span>
                            <?php } else if ($suggestion !== NULL) { ?><span class="billing_bank_badge suggested">★ <?=$Dictionnary["BillingBankRecognized"] ?? "Reconnu"; ?></span>
                            <?php } else { ?><span class="billing_bank_badge pending">? <?=$Dictionnary["BillingBankPending"] ?? "À rapprocher"; ?></span><?php } ?>
                            <small><?=billing_e($transaction["school_codename"]); ?></small>
                        </td>
                        <td><?=billing_date_label($transaction["operation_date"]); ?><?php if (!empty($transaction["value_date"])) { ?><small><?=$Dictionnary["BillingBankValueDate"] ?? "Valeur"; ?> : <?=billing_date_label($transaction["value_date"]); ?></small><?php } ?></td>
                        <td class="billing_bank_label_cell"><strong><?=billing_e($transaction["label"]); ?></strong><?php if (!empty($transaction["pointage"])) { ?><small><?=billing_e($transaction["pointage"]); ?></small><?php } ?><?php if (!empty($transaction["bank_comment"])) { ?><small><?=billing_e($transaction["bank_comment"]); ?></small><?php } ?></td>
                        <td class="billing_bank_amount <?=((int)$transaction["amount"] < 0 ? "debit" : "credit"); ?>"><?=billing_euros(abs((int)$transaction["amount"])); ?></td>
                        <?php if ($status == "pending") { ?>
                            <?php $reconcile_form_id = "billing_bank_reconcile_".$id_transaction; ?>
                            <td>
                                <form id="<?=billing_e($reconcile_form_id); ?>" method="post" action="/api/billing/<?=$id_transaction; ?>/bank_reconcile" enctype="multipart/form-data" data-direct-file-upload="1" class="billing_bank_reconcile_form" onsubmit="return billing_bank_reconcile(this);">
                                    <input type="text" list="billing_bank_associations" autocomplete="off" data-bank-association value="<?=billing_e($association_value); ?>" placeholder="<?=$Dictionnary["BillingBankAssociationPlaceholder"] ?? "Élève, facture ou entreprise…"; ?>" oninput="billing_bank_resolve_association(this);" />
                                    <input type="hidden" name="association_kind" value="<?=billing_e($association_kind); ?>" />
                                    <input type="hidden" name="association_id" value="<?=$association_id; ?>" />
                                    <button type="button" class="billing_bank_create_org" onclick="billing_bank_create_organization(this);" title="<?=$Dictionnary["BillingBankCreateOrganization"] ?? "Créer une organisation"; ?>">＋ org.</button>
                                </form>
                            </td>
                            <td><input form="<?=billing_e($reconcile_form_id); ?>" type="file" name="document" data-bank-document accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" <?=in_array($association_kind, ["organization", "organization_entry"], true) ? "" : "disabled"; ?> /></td>
                            <td class="billing_bank_actions"><button form="<?=billing_e($reconcile_form_id); ?>" type="submit">✓ <?=$Dictionnary["BillingValidate"] ?? "Valider"; ?></button><form method="post" action="/api/billing/<?=$id_transaction; ?>/bank_ignore" onsubmit="return billing_bank_simple_action(this);"><button type="submit" class="secondary">— <?=$Dictionnary["BillingBankIgnore"] ?? "Ignorer"; ?></button></form></td>
                        <?php } else { ?>
                            <td><?php
                                if ($status == "matched") {
                                    if (!empty($transaction["matched_invoice_reference"])) echo "Facture ".billing_e($transaction["matched_invoice_reference"])."<br />";
                                    if (!empty($transaction["matched_student_name"]) || !empty($transaction["matched_student_codename"])) echo billing_e(trim($transaction["matched_student_name"]) ?: $transaction["matched_student_codename"]);
                                    if (!empty($transaction["matched_organization_name"]) || !empty($transaction["matched_organization_codename"])) echo billing_e($transaction["matched_organization_name"] ?: $transaction["matched_organization_codename"]);
                                    if (empty($transaction["id_user"]) && empty($transaction["id_organization"])) echo billing_e($transaction["match_type"] ?? "");
                                } else echo "—";
                            ?></td>
                            <td>—</td>
                            <td class="billing_bank_actions"><form method="post" action="/api/billing/<?=$id_transaction; ?>/bank_reopen" onsubmit="return billing_bank_simple_action(this);"><button type="submit" class="secondary">↶ <?=$Dictionnary["BillingBankReopen"] ?? "Réouvrir"; ?></button></form></td>
                        <?php } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
