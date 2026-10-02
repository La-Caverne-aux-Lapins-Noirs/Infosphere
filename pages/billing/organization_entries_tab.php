<?php
$movement_type = $tab_data["type"] ?? "debit";
$entries = billing_fetch_organization_entries($movement_type);
$organizations = billing_fetch_organizations();
$organization_statements = billing_fetch_organization_statements();
$managed_schools = [];
foreach (billing_managed_school_ids() as $id_school)
{
    $school = db_select_one("id, codename FROM school WHERE id = ".(int)$id_school." AND deleted IS NULL");
    if ($school)
        $managed_schools[] = $school;
}
$is_debit = ($movement_type == "debit");
$title = $is_debit ? $Dictionnary["BillingEnterpriseDebit"] : $Dictionnary["BillingEnterpriseCredit"];
$total = 0;
foreach ($entries as $entry)
    $total += (int)$entry["amount"];
$dialog_id = "billing_organization_entry_dialog_".$movement_type;
$table_id = "billing_organization_table_".$movement_type;
$filter_id = "billing_organization_filter_".$movement_type;
?>
<div class="billing_organization_tab">
    <section class="billing_organization_entry_add">
        <div class="billing_organization_entry_heading">
            <h3><?=$title; ?></h3>
            <div class="billing_organization_summary" id="<?=billing_e($filter_id); ?>_summary">
                <span data-visible-count data-count-label="<?=billing_e($Dictionnary["BillingEnterpriseMovements"]); ?>"><?=count($entries); ?> <?=$Dictionnary["BillingEnterpriseMovements"]; ?></span>
                <strong data-visible-total data-total-label="<?=billing_e($Dictionnary["BillingTotal"]); ?>"><?=$Dictionnary["BillingTotal"]; ?> : <?=billing_euros($total); ?></strong>
            </div>
        </div>
        <?php if (!count($organizations)) { ?>
            <p class="billing_empty_tab"><?=$Dictionnary["BillingNoEnterprise"]; ?></p>
        <?php } else if (!count($managed_schools)) { ?>
            <p class="billing_empty_tab"><?=$Dictionnary["BillingNoManagedSchool"]; ?></p>
        <?php } else { ?>
            <form
                method="post"
                action="/api/billing/-1/organization_entry"
                enctype="multipart/form-data"
                data-direct-file-upload="1"
                onsubmit="return billing_submit_organization_entry(this);"
            >
                <input type="hidden" name="movement_type" value="<?=billing_e($movement_type); ?>" />
                <label>
                    <span><?=$Dictionnary["School"]; ?></span>
                    <select name="id_school" required>
                        <?php foreach ($managed_schools as $school) { ?>
                            <option value="<?=(int)$school["id"]; ?>"><?=billing_e($school["codename"]); ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>
                    <span><?=$Dictionnary["Enterprise"]; ?></span>
                    <select name="id_organization" required>
                        <?php foreach ($organizations as $organization) { ?>
                            <?php $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"])); ?>
                            <option value="<?=(int)$organization["id"]; ?>"><?=billing_e($name); ?> — <?=billing_e($organization["codename"]); ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>
                    <span><?=$Dictionnary["BillingMovementDate"]; ?></span>
                    <input type="date" name="movement_date" value="<?=date('Y-m-d'); ?>" required />
                </label>
                <label>
                    <span><?=$Dictionnary["BillingLabel"]; ?></span>
                    <input type="text" name="label" maxlength="255" required />
                </label>
                <label>
                    <span><?=$Dictionnary["EuroAmount"]; ?></span>
                    <input type="text" name="amount" placeholder="0.00" required />
                </label>
                <label>
                    <span><?=$Dictionnary["Reference"]; ?></span>
                    <input type="text" name="reference" maxlength="255" />
                </label>
                <label class="billing_organization_comment">
                    <span><?=$Dictionnary["BillingComment"]; ?></span>
                    <input type="text" name="comment" />
                </label>
                <label class="billing_organization_document">
                    <span><?=$Dictionnary["BillingSupportingDocument"]; ?></span>
                    <input type="file" name="document" accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" />
                </label>
                <input type="submit" value="<?=$Dictionnary["Add"]; ?>" />
            </form>
        <?php } ?>
    </section>

    <?php if (count($entries)) { ?>
        <section class="billing_organization_filters" id="<?=billing_e($filter_id); ?>" data-table-id="<?=billing_e($table_id); ?>" data-summary-id="<?=billing_e($filter_id); ?>_summary">
            <label class="billing_filter_search">
                <span><?=$Dictionnary["BillingSearch"]; ?></span>
                <input type="search" data-filter-search placeholder="<?=$Dictionnary["BillingSearchPlaceholder"]; ?>" oninput="billing_filter_organization_entries(this.closest('[data-table-id]'));" />
            </label>
            <label>
                <span><?=$Dictionnary["Enterprise"]; ?></span>
                <select data-filter-organization onchange="billing_filter_organization_entries(this.closest('[data-table-id]'));">
                    <option value=""><?=$Dictionnary["BillingAllEnterprises"]; ?></option>
                    <?php foreach ($organizations as $organization) { ?>
                        <?php $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"])); ?>
                        <option value="<?=(int)$organization["id"]; ?>"><?=billing_e($name); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span><?=$Dictionnary["School"]; ?></span>
                <select data-filter-school onchange="billing_filter_organization_entries(this.closest('[data-table-id]'));">
                    <option value=""><?=$Dictionnary["BillingAllSchools"]; ?></option>
                    <?php foreach ($managed_schools as $school) { ?>
                        <option value="<?=(int)$school["id"]; ?>"><?=billing_e($school["codename"]); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span><?=$Dictionnary["BillingFrom"]; ?></span>
                <input type="date" data-filter-start onchange="billing_filter_organization_entries(this.closest('[data-table-id]'));" />
            </label>
            <label>
                <span><?=$Dictionnary["BillingTo"]; ?></span>
                <input type="date" data-filter-end onchange="billing_filter_organization_entries(this.closest('[data-table-id]'));" />
            </label>
            <button type="button" onclick="billing_reset_organization_filters(this.closest('[data-table-id]')); return false;"><?=$Dictionnary["BillingResetFilters"]; ?></button>
            <?php if (count($organization_statements)) { ?>
                <div class="billing_statement_picker">
                    <select data-statement-picker>
                        <?php foreach ($organizations as $organization) { ?>
                            <?php if (!isset($organization_statements[(int)$organization["id"]])) continue; ?>
                            <?php $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"])); ?>
                            <option value="<?=(int)$organization["id"]; ?>"><?=billing_e($name); ?></option>
                        <?php } ?>
                    </select>
                    <button type="button" onclick="return billing_open_organization_statement_from_picker(this, '<?=billing_e($movement_type); ?>');"><?=$Dictionnary["BillingAccountStatement"]; ?></button>
                </div>
            <?php } ?>
        </section>
    <?php } ?>

    <section class="billing_organization_entries">
        <?php if (!count($entries)) { ?>
            <p class="billing_empty_tab"><?=$Dictionnary["BillingNoEnterpriseMovement"]; ?></p>
        <?php } else { ?>
            <table class="billing_simple_table billing_organization_table" id="<?=billing_e($table_id); ?>">
                <thead>
                    <tr>
                        <th><?=$Dictionnary["BillingMovementDate"]; ?></th>
                        <th><?=$Dictionnary["Enterprise"]; ?></th>
                        <th><?=$Dictionnary["School"]; ?></th>
                        <th><?=$Dictionnary["BillingLabel"]; ?></th>
                        <th><?=$Dictionnary["Reference"]; ?></th>
                        <th><?=$Dictionnary["BillingComment"]; ?></th>
                        <th><?=$Dictionnary["BillingSupportingDocument"]; ?></th>
                        <th><?=$Dictionnary["Amount"]; ?></th>
                        <th><?=$Dictionnary["Actions"]; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry) { ?>
                        <?php
                            $stamp = date_to_timestamp($entry["movement_date"]);
                            $input_date = $stamp === NULL ? "" : date("Y-m-d", $stamp);
                            $document_url = billing_organization_entry_document_url($entry);
                            $statement_dialog_id = "billing_organization_statement_".$movement_type."_".(int)$entry["id_organization"];
                        ?>
                        <tr data-billing-organization-entry data-organization-id="<?=(int)$entry["id_organization"]; ?>" data-school-id="<?=(int)$entry["id_school"]; ?>" data-movement-date="<?=billing_e($input_date); ?>" data-amount="<?=(int)$entry["amount"]; ?>">
                            <td><?=billing_date_label($entry["movement_date"]); ?></td>
                            <td>
                                <a href="index.php?p=EnterpriseMenu&amp;a=<?=(int)$entry["id_organization"]; ?>">
                                    <?=billing_e($entry["organization_name"]); ?>
                                </a>
                                <small><?=billing_e($entry["organization_codename"]); ?></small>
                                <button type="button" class="billing_statement_inline_button" onclick="return billing_open_dialog_by_id('<?=billing_e($statement_dialog_id); ?>');"><?=$Dictionnary["BillingAccountStatement"]; ?></button>
                            </td>
                            <td><?=billing_e($entry["school_codename"]); ?></td>
                            <td><?=billing_e($entry["label"]); ?></td>
                            <td><?=billing_e($entry["reference"] ?: "—"); ?></td>
                            <td>
                                <?=billing_e($entry["comment"] ?: "—"); ?>
                                <?php if (!empty($entry["actor_codename"])) { ?>
                                    <small><?=$Dictionnary["BillingEnteredBy"]; ?> <?=billing_e($entry["actor_codename"]); ?></small>
                                <?php } ?>
                            </td>
                            <td class="billing_organization_document_cell">
                                <?php if ($document_url != "") { ?>
                                    <a href="<?=billing_e($document_url); ?>" target="_blank" rel="noopener" title="<?=billing_e($entry["document_name"] ?: $Dictionnary["BillingSupportingDocument"]); ?>">📄 <?=billing_e($entry["document_name"] ?: $Dictionnary["BillingOpenDocument"]); ?></a>
                                <?php } else { ?>
                                    <span>—</span>
                                <?php } ?>
                            </td>
                            <td class="billing_amount_cell"><?=billing_euros($entry["amount"]); ?></td>
                            <td class="billing_organization_actions">
                                <button
                                    type="button"
                                    onclick="return billing_open_organization_entry_dialog(this);"
                                    data-dialog-id="<?=billing_e($dialog_id); ?>"
                                    data-entry-id="<?=(int)$entry["id"]; ?>"
                                    data-school-id="<?=(int)$entry["id_school"]; ?>"
                                    data-organization-id="<?=(int)$entry["id_organization"]; ?>"
                                    data-movement-type="<?=billing_e($entry["movement_type"]); ?>"
                                    data-movement-date="<?=billing_e($input_date); ?>"
                                    data-label="<?=billing_e($entry["label"]); ?>"
                                    data-amount="<?=number_format(((int)$entry["amount"]) / 100, 2, '.', ''); ?>"
                                    data-reference="<?=billing_e($entry["reference"]); ?>"
                                    data-comment="<?=billing_e($entry["comment"]); ?>"
                                    data-document-name="<?=billing_e($entry["document_name"] ?? ""); ?>"
                                ><?=$Dictionnary["BillingEditMovement"]; ?></button>
                                <form method="delete" action="/api/billing/<?=(int)$entry["id"]; ?>/organization_entry" data-confirm="<?=billing_e($Dictionnary["ConfirmDeleteEnterpriseMovement"]); ?>" onsubmit="return billing_delete_organization_entry(this);">
                                    <input type="submit" value="<?=$Dictionnary["Delete"]; ?>" />
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </section>

    <?php foreach ($organization_statements as $id_organization => $statement) { ?>
        <?php
            if (!count($statement)) continue;
            $summary = billing_organization_statement_summary($statement);
            $statement_name = $statement[0]["organization_name"] ?? ($statement[0]["organization_codename"] ?? "");
            $statement_dialog_id = "billing_organization_statement_".$movement_type."_".(int)$id_organization;
        ?>
        <dialog id="<?=billing_e($statement_dialog_id); ?>" class="billing_organization_dialog billing_statement_dialog">
            <div class="billing_organization_dialog_header">
                <div>
                    <h3><?=$Dictionnary["BillingAccountStatement"]; ?> — <?=billing_e($statement_name); ?></h3>
                    <small><?=billing_e($statement[0]["organization_codename"] ?? ""); ?></small>
                </div>
                <button type="button" onclick="this.closest('dialog').close();">×</button>
            </div>
            <div class="billing_statement_summary">
                <div><span><?=$Dictionnary["BillingDebit"]; ?></span><strong><?=billing_euros($summary["debit"]); ?></strong></div>
                <div><span><?=$Dictionnary["BillingCredit"]; ?></span><strong><?=billing_euros($summary["credit"]); ?></strong></div>
                <div><span><?=$Dictionnary["BillingBalanceDebitCredit"]; ?></span><strong><?=billing_euros($summary["balance"]); ?></strong></div>
            </div>
            <div class="billing_statement_scroll">
                <table class="billing_simple_table">
                    <thead>
                        <tr>
                            <th><?=$Dictionnary["BillingMovementDate"]; ?></th>
                            <th><?=$Dictionnary["School"]; ?></th>
                            <th><?=$Dictionnary["BillingLabel"]; ?></th>
                            <th><?=$Dictionnary["Reference"]; ?></th>
                            <th><?=$Dictionnary["BillingDebit"]; ?></th>
                            <th><?=$Dictionnary["BillingCredit"]; ?></th>
                            <th><?=$Dictionnary["BillingAccountBalance"]; ?></th>
                            <th><?=$Dictionnary["BillingSupportingDocument"]; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statement as $movement) { ?>
                            <?php $statement_document_url = billing_organization_entry_document_url($movement); ?>
                            <tr>
                                <td><?=billing_date_label($movement["movement_date"]); ?></td>
                                <td><?=billing_e($movement["school_codename"]); ?></td>
                                <td><?=billing_e($movement["label"]); ?><small><?=billing_e($movement["comment"] ?? ""); ?></small></td>
                                <td><?=billing_e($movement["reference"] ?: "—"); ?></td>
                                <td class="billing_amount_cell"><?=$movement["debit"] ? billing_euros($movement["debit"]) : "—"; ?></td>
                                <td class="billing_amount_cell"><?=$movement["credit"] ? billing_euros($movement["credit"]) : "—"; ?></td>
                                <td class="billing_amount_cell"><?=billing_euros($movement["balance"]); ?></td>
                                <td>
                                    <?php if ($statement_document_url != "") { ?>
                                        <a href="<?=billing_e($statement_document_url); ?>" target="_blank" rel="noopener">📄 <?=billing_e($movement["document_name"] ?: $Dictionnary["BillingOpenDocument"]); ?></a>
                                    <?php } else { ?>—<?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </dialog>
    <?php } ?>

    <?php if (count($organizations) && count($managed_schools)) { ?>
        <dialog id="<?=billing_e($dialog_id); ?>" class="billing_organization_dialog">
            <form
                method="post"
                action=""
                enctype="multipart/form-data"
                data-direct-file-upload="1"
                onsubmit="return billing_update_organization_entry(this);"
            >
                <div class="billing_organization_dialog_header">
                    <h3><?=$Dictionnary["BillingEditEnterpriseMovement"]; ?></h3>
                    <button type="button" onclick="this.closest('dialog').close();">×</button>
                </div>
                <div class="billing_organization_dialog_fields">
                    <label>
                        <span><?=$Dictionnary["BillingMovementType"]; ?></span>
                        <select name="movement_type" required>
                            <option value="debit"><?=$Dictionnary["BillingDebit"]; ?></option>
                            <option value="credit"><?=$Dictionnary["BillingCredit"]; ?></option>
                        </select>
                    </label>
                    <label>
                        <span><?=$Dictionnary["School"]; ?></span>
                        <select name="id_school" required>
                            <?php foreach ($managed_schools as $school) { ?>
                                <option value="<?=(int)$school["id"]; ?>"><?=billing_e($school["codename"]); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <label>
                        <span><?=$Dictionnary["Enterprise"]; ?></span>
                        <select name="id_organization" required>
                            <?php foreach ($organizations as $organization) { ?>
                                <?php $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"])); ?>
                                <option value="<?=(int)$organization["id"]; ?>"><?=billing_e($name); ?> — <?=billing_e($organization["codename"]); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <label>
                        <span><?=$Dictionnary["BillingMovementDate"]; ?></span>
                        <input type="date" name="movement_date" required />
                    </label>
                    <label class="billing_dialog_wide">
                        <span><?=$Dictionnary["BillingLabel"]; ?></span>
                        <input type="text" name="label" maxlength="255" required />
                    </label>
                    <label>
                        <span><?=$Dictionnary["EuroAmount"]; ?></span>
                        <input type="text" name="amount" placeholder="0.00" required />
                    </label>
                    <label>
                        <span><?=$Dictionnary["Reference"]; ?></span>
                        <input type="text" name="reference" maxlength="255" />
                    </label>
                    <label class="billing_dialog_wide">
                        <span><?=$Dictionnary["BillingComment"]; ?></span>
                        <input type="text" name="comment" />
                    </label>
                    <label class="billing_dialog_wide">
                        <span><?=$Dictionnary["BillingReplaceSupportingDocument"]; ?></span>
                        <input type="file" name="document" accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" />
                        <small data-current-document data-prefix="<?=billing_e($Dictionnary["BillingCurrentDocumentPrefix"]); ?>" data-none="<?=billing_e($Dictionnary["BillingNoSupportingDocument"]); ?>"></small>
                    </label>
                    <label class="billing_dialog_wide billing_delete_document hidden" data-delete-document-row>
                        <input type="checkbox" name="delete_document" value="1" />
                        <span><?=$Dictionnary["BillingDeleteSupportingDocument"]; ?></span>
                    </label>
                </div>
                <div class="billing_organization_dialog_actions">
                    <button type="button" onclick="this.closest('dialog').close();"><?=$Dictionnary["BillingCancel"]; ?></button>
                    <input type="submit" value="<?=$Dictionnary["BillingSave"]; ?>" />
                </div>
            </form>
        </dialog>
    <?php } ?>
</div>
