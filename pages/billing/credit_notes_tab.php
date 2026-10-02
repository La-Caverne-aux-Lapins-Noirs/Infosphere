<?php
$credit_notes = billing_fetch_student_credit_notes();
?>
<div class="billing_credit_notes_tab">
    <?php if (!count($credit_notes)) { ?>
        <p class="billing_empty_tab"><?=$Dictionnary["BillingNoStudentCreditNotes"]; ?></p>
    <?php } else { ?>
        <table class="billing_simple_table billing_credit_notes_table">
            <thead>
                <tr>
                    <th><?=$Dictionnary["BillingCreditNoteReference"]; ?></th>
                    <th><?=$Dictionnary["Student"]; ?></th>
                    <th><?=$Dictionnary["School"]; ?></th>
                    <th><?=$Dictionnary["BillingLabel"]; ?></th>
                    <th><?=$Dictionnary["BillingRelatedInvoice"]; ?></th>
                    <th><?=$Dictionnary["BillingMovementDate"]; ?></th>
                    <th><?=$Dictionnary["Amount"]; ?></th>
                    <th><?=$Dictionnary["Actions"]; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($credit_notes as $entry) { ?>
                    <?php
                    $related = NULL;
                    if (!empty($entry["related_entry_id"]))
                        $related = db_select_one("invoice_reference FROM billing_entry WHERE id = ".(int)$entry["related_entry_id"]);
                    ?>
                    <tr>
                        <td><?=billing_e(billing_invoice_document_reference($entry)); ?></td>
                        <td>
                            <a href="index.php?p=Profile&amp;a=<?=(int)$entry["id_user"]; ?>">
                                <?=billing_e(billing_student_name($entry)); ?>
                            </a>
                            <small><?=billing_e($entry["codename"] ?? ""); ?></small>
                        </td>
                        <td><?=billing_e($entry["school_name"] ?: $entry["school_codename"]); ?></td>
                        <td><?=billing_e($entry["label"]); ?></td>
                        <td><?=billing_e($related["invoice_reference"] ?? "—"); ?></td>
                        <td><?=billing_date_label($entry["sent_date"] ?: $entry["due_date"]); ?></td>
                        <td class="billing_amount_cell"><?=billing_euros(abs((int)$entry["amount"])); ?></td>
                        <td>
                            <a href="/api/billing/<?=(int)$entry["id"]; ?>/invoice" target="_blank">
                                <?=$Dictionnary["BillingOpenCreditNote"]; ?>
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>
