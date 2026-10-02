<div class="billing_students_tab">
    <div class="billing_tab_controls">
        <label class="billing_show_hidden_users">
            <input
                type="checkbox"
                <?= $show_hidden_billing_users ? "checked" : ""; ?>
                onchange="billing_toggle_hidden_users(this);"
            />
            <?=$Dictionnary["BillingShowHiddenUsers"]; ?>
        </label>
    </div>
    <div id="billing_panel">
        <?php render_dynamic_table("billing_table", $fields, $students); ?>
    </div>
</div>
