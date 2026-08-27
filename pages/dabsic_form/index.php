<?php

require_once (__DIR__."/../../tools/dabsic_form.php");
require_once (__DIR__."/../../tools/document_sources.php");

if (!dabsic_form_user_can_access_output($_GET["output"] ?? ""))
{
    http_response_code(404);
    die();
}

$dabsic_form_reference_key = $_GET["file"] ?? "";
$dabsic_form_output_key = $_GET["output"] ?? "";
$dabsic_form_chain = $_GET["chain"] ?? "";
$dabsic_form_mode = ($_GET["mode"] ?? "dabsic") === "docbuilder" ? "docbuilder" : "dabsic";
$dabsic_form_discovery = dabsic_form_discover_fields($dabsic_form_reference_key, $dabsic_form_mode, $dabsic_form_chain);
$dabsic_form_output = dabsic_form_resolve_output($dabsic_form_output_key, false);
$dabsic_form_error = "";
$dabsic_form_details = "";
$dabsic_form_values = [];
$dabsic_form_output_hash = hash("sha256", "");
$dabsic_form_output_exists = false;
$dabsic_form_output_complete = false;
$dabsic_form_reference_hash = "";
$dabsic_form_overrides = [];
$dabsic_form_overrides_hash = hash("sha256", "");
$dabsic_form_overrides_exists = false;
$dabsic_form_metadata = $dabsic_form_discovery["form_metadata"] ?? dabsic_form_empty_form_metadata();
$dabsic_form_role = trim((string)($_GET["form_role"] ?? ""));
if ($dabsic_form_role == "" && isset($dabsic_form_metadata["roles"]["Etablissement"]))
    $dabsic_form_role = "Etablissement";
if ($dabsic_form_role == "" && count($dabsic_form_metadata["roles"] ?? []) === 1)
    $dabsic_form_role = (string)array_key_first($dabsic_form_metadata["roles"]);
if ($dabsic_form_role != "" && dabsic_form_role_definition($dabsic_form_metadata, $dabsic_form_role) == NULL)
{
    $dabsic_form_error = $Dictionnary["PermissionDenied"] ?? "Accès refusé";
    $dabsic_form_details = $dabsic_form_role;
}

if (!$dabsic_form_discovery["ok"])
{
    $dabsic_form_error = $Dictionnary[$dabsic_form_discovery["error"]] ?? $Dictionnary["InvalidFile"];
    $dabsic_form_details = $dabsic_form_discovery["details"] ?? "";
}
else if (!$dabsic_form_output["ok"])
{
    $dabsic_form_error = $Dictionnary[$dabsic_form_output["error"]] ?? $Dictionnary["InvalidFile"];
    $dabsic_form_details = $dabsic_form_output["details"] ?? "";
}
else
{
    $dabsic_form_reference_content = @file_get_contents($dabsic_form_discovery["reference"]["absolute"]);
    if ($dabsic_form_reference_content === false)
        $dabsic_form_error = $Dictionnary["DabsicEditorCannotRead"];
    else
    {
        $dabsic_form_reference_hash = hash("sha256", $dabsic_form_reference_content);
        $dabsic_form_loaded = dabsic_form_load_output_values($dabsic_form_output);
        if (!$dabsic_form_loaded["ok"])
        {
            $dabsic_form_error = $Dictionnary[$dabsic_form_loaded["error"]] ?? $Dictionnary["InvalidFile"];
            $dabsic_form_details = $dabsic_form_loaded["details"] ?? "";
        }
        else
        {
            $dabsic_form_override_loaded = dabsic_form_load_overrides($dabsic_form_output);
            if (!$dabsic_form_override_loaded["ok"])
            {
                $dabsic_form_error = $Dictionnary[$dabsic_form_override_loaded["error"]] ?? $Dictionnary["InvalidFile"];
                $dabsic_form_details = $dabsic_form_override_loaded["details"] ?? "";
            }
            $dabsic_form_overrides = $dabsic_form_override_loaded["values"] ?? [];
            $dabsic_form_overrides_hash = $dabsic_form_override_loaded["hash"] ?? hash("sha256", "");
            $dabsic_form_overrides_exists = $dabsic_form_override_loaded["exists"] ?? false;
            // Automatic context (Student, School, etc.) remains visible even
            // when it is not stored in forms/*.dab. Explicit form values win.
            $dabsic_form_values = array_merge(
                dabsic_form_prefill_from_chain($dabsic_form_chain),
                $dabsic_form_loaded["values"]
            );
            $dabsic_form_defaults_added = false;
            foreach (dabsic_form_default_values($dabsic_form_discovery["reference"]["absolute"], $dabsic_form_role) as $field => $value)
                if (!array_key_exists($field, $dabsic_form_values) || (!is_array($dabsic_form_values[$field]) && trim((string)$dabsic_form_values[$field]) === ""))
                {
                    $dabsic_form_values[$field] = $value;
                    $dabsic_form_defaults_added = true;
                }
            $dabsic_form_output_hash = $dabsic_form_loaded["hash"];
            $dabsic_form_output_exists = $dabsic_form_output["exists"];
            $dabsic_form_output_complete = $dabsic_form_output_exists && !$dabsic_form_defaults_added;
        }
    }
}

$dabsic_form_document_title = "";
if ($dabsic_form_discovery["ok"])
    $dabsic_form_document_title = document_title_from_file(
        $dabsic_form_discovery["reference"]["absolute"],
        document_title_fallback($dabsic_form_discovery["reference"]["relative"])
    );

$dabsic_form_groups = [];
$dabsic_form_readonly_groups = [];
$dabsic_form_billing_templates = [];
$dabsic_form_post_interview_prospect_id = 0;
if ($dabsic_form_error === "" && preg_match('/^post-interview-report:([0-9]+)$/', $dabsic_form_output_key, $m))
{
    $dabsic_form_post_interview_prospect_id = (int)$m[1];
    $school = document_context_first_school_for_user($dabsic_form_post_interview_prospect_id);
    $id_school = is_array($school) ? (int)($school["id_school"] ?? 0) : 0;
    if ($id_school > 0)
        $dabsic_form_billing_templates = billing_fetch_templates_for_school($id_school, "school");
}
if ($dabsic_form_error === "" && $dabsic_form_discovery["ok"])
{
    $read = array_flip(dabsic_form_role_groups($dabsic_form_metadata, $dabsic_form_role, "read"));
    $edit = array_flip(dabsic_form_role_groups($dabsic_form_metadata, $dabsic_form_role, "edit"));
    $validate = array_flip(dabsic_form_role_groups($dabsic_form_metadata, $dabsic_form_role, "validate"));
    foreach (($dabsic_form_metadata["group_order"] ?? []) as $group)
    {
        if (!isset($read[$group]))
            continue ;
        $entry = $dabsic_form_metadata["groups"][$group] ?? ["label" => $group, "fields" => []];
        $entry["key"] = $group;
        $entry["editable"] = isset($edit[$group]);
        $entry["validatable"] = isset($validate[$group]);
        if ($entry["editable"] || $entry["validatable"])
            $dabsic_form_groups[] = $entry;
        else
            $dabsic_form_readonly_groups[] = $entry;
    }
}
?>

<style>
<?php require (__DIR__."/style.css"); ?>
</style>

<div class="dabsic-form-page">
    <div class="dabsic-form-header">
        <div>
            <h2><?=$Dictionnary["DabsicFormTitle"]; ?></h2>
            <?php if ($dabsic_form_discovery["ok"]) { ?>
                <div class="dabsic-form-path-line" title="<?=htmlspecialchars($dabsic_form_discovery["reference"]["relative"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>">
                    <span><?=$Dictionnary["DabsicFormReference"]; ?></span>
                    <strong><?=htmlspecialchars($dabsic_form_document_title, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></strong>
                </div>
            <?php } ?>
            <?php if ($dabsic_form_output["ok"]) { ?>
                <div class="dabsic-form-path-line" title="<?=htmlspecialchars($dabsic_form_output["relative"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>">
                    <span><?=$Dictionnary["OutputFile"]; ?></span>
                    <strong><?=htmlspecialchars($dabsic_form_output["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></strong>
                </div>
            <?php } ?>
        </div>
        <div id="dabsic-form-state" class="dabsic-form-state" aria-live="polite"></div>
    </div>

    <?php if ($dabsic_form_error !== "") { ?>
        <div class="dabsic-form-message is-error">
            <strong><?=htmlspecialchars($dabsic_form_error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></strong>
            <?php if ($dabsic_form_details !== "") { ?>
                <pre><?=htmlspecialchars($dabsic_form_details, ENT_NOQUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></pre>
            <?php } ?>
        </div>
    <?php } else { ?>
        <form
            id="dabsic-form-root"
            class="dabsic-form-root"
            data-reference="<?=htmlspecialchars($dabsic_form_discovery["reference"]["relative"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-mode="<?=htmlspecialchars($dabsic_form_mode, ENT_QUOTES, "UTF-8"); ?>"
            data-chain="<?=htmlspecialchars($dabsic_form_chain, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-form-role="<?=htmlspecialchars($dabsic_form_role, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-reference-hash="<?=htmlspecialchars($dabsic_form_reference_hash, ENT_QUOTES, "UTF-8"); ?>"
            data-output="<?=htmlspecialchars($dabsic_form_output["key"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-output-hash="<?=htmlspecialchars($dabsic_form_output_hash, ENT_QUOTES, "UTF-8"); ?>"
            data-output-exists="<?=$dabsic_form_output_exists ? "1" : "0"; ?>"
            data-output-complete="<?=$dabsic_form_output_complete ? "1" : "0"; ?>"
            data-overrides-hash="<?=htmlspecialchars($dabsic_form_overrides_hash, ENT_QUOTES, "UTF-8"); ?>"
            data-overrides-exists="<?=$dabsic_form_overrides_exists ? "1" : "0"; ?>"
            data-save-url="/api/dabsic/0/form"
            data-finalize-url="<?=$dabsic_form_post_interview_prospect_id > 0 ? "/api/prospect/".$dabsic_form_post_interview_prospect_id."/interview_report" : ""; ?>"
            data-confirm-finalize="Valider ce compte rendu, générer le PDF signé et tamponné, puis l'envoyer au prospect ?"
            data-confirm-save="<?=htmlspecialchars($Dictionnary["DabsicFormConfirmSave"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-unsaved-warning="<?=htmlspecialchars($Dictionnary["DabsicEditorUnsavedWarning"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-clean-label="<?=htmlspecialchars($Dictionnary["DabsicEditorUnmodified"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-dirty-label="<?=htmlspecialchars($Dictionnary["DabsicEditorModified"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-saving-label="<?=htmlspecialchars($Dictionnary["DabsicEditorSaving"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-saved-label="<?=htmlspecialchars($Dictionnary["DabsicFormSaved"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-network-error="<?=htmlspecialchars($Dictionnary["DabsicEditorNetworkError"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-override-key-placeholder="<?=htmlspecialchars($Dictionnary["DabsicFormOverrideKey"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-override-value-placeholder="<?=htmlspecialchars($Dictionnary["DabsicFormOverrideValue"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-remove-label="<?=htmlspecialchars($Dictionnary["Delete"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        >
            <?php if (!count($dabsic_form_groups) && !count($dabsic_form_readonly_groups)) { ?>
                <div class="dabsic-form-message is-success"><?=$Dictionnary["DabsicFormNothingMissing"]; ?></div>
            <?php } ?>

            <?php foreach ($dabsic_form_groups as $group) { ?>
                <fieldset class="dabsic-form-group<?=$group["editable"] ? " is-editable" : " is-readonly"; ?>">
                    <legend><?=htmlspecialchars((string)$group["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></legend>
                    <?php foreach (($group["fields"] ?? []) as $field) {
                        $definition = $dabsic_form_metadata["fields"][$field] ?? ["label" => $field, "required" => false];
                        $value = $dabsic_form_values[$field] ?? "";
                    ?>
                        <div class="dabsic-form-field<?=$group["editable"] ? "" : " is-readonly"; ?>">
                            <span><?=htmlspecialchars((string)$definition["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?><?php if (!empty($definition["required"])) { ?><strong class="dabsic-form-required"> *</strong><?php } ?></span>
                            <?php if ($group["editable"]) {
                                $field_type = strtolower((string)($definition["type"] ?? "text"));
                                $field_key = htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
                            ?>
                                <?php if (form_field_is_common_type($field_type)) { ?>
                                    <?=form_field_render($definition, $value, [
                                        "attributes" => ["data-dabsic-field" => $field],
                                    ]); ?>
                                <?php } else if ($field_type === "billing_template") { ?>
                                    <select data-dabsic-field="<?=$field_key; ?>" data-dabsic-billing-template>
                                        <option value="">— Sélectionner un tarif —</option>
                                        <?php
                                        $known_tariff = false;
                                        foreach ($dabsic_form_billing_templates as $template) {
                                            $amounts = billing_admission_amounts($template, false);
                                            $selected = (string)$value === (string)$template["id"];
                                            $known_tariff = $known_tariff || $selected;
                                        ?>
                                            <option
                                                value="<?=(int)$template["id"]; ?>"
                                                <?=$selected ? "selected" : ""; ?>
                                                data-billing-name="<?=htmlspecialchars((string)$template["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
                                                data-billing-registration="<?=(int)$amounts["registration_fee_cents"]; ?>"
                                                data-billing-tuition="<?=(int)$amounts["tuition_cents"]; ?>"
                                            ><?=htmlspecialchars((string)$template["name"]." — scolarité ".$amounts["tuition"]." + inscription ".$amounts["registration_fee"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></option>
                                        <?php } ?>
                                        <?php if ((string)$value !== "" && !$known_tariff) { ?>
                                            <option value="<?=htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>" selected>Tarif #<?=htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?> indisponible</option>
                                        <?php } ?>
                                    </select>
                                <?php } else if ($field_type === "boolean") { ?>
                                    <select data-dabsic-field="<?=$field_key; ?>" data-dabsic-billing-foreign="<?=$field === "InterviewReport.ForeignStudent" ? "1" : "0"; ?>">
                                        <option value=""></option>
                                        <option value="1" <?=(string)$value === "1" ? "selected" : ""; ?>><?=$Dictionnary["Yes"] ?? "Oui"; ?></option>
                                        <option value="0" <?=(string)$value === "0" ? "selected" : ""; ?>><?=$Dictionnary["No"] ?? "Non"; ?></option>
                                    </select>
                                <?php } else if ($field_type === "textarea") { ?>
                                    <textarea data-dabsic-field="<?=$field_key; ?>" autocomplete="off"><?=htmlspecialchars((string)$value, ENT_NOQUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></textarea>
                                <?php } else if ($field_type === "computed") { ?>
                                    <input
                                        type="text"
                                        data-dabsic-field="<?=$field_key; ?>"
                                        data-dabsic-computed="1"
                                        value="<?=htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
                                        readonly
                                    />
                                <?php } else { ?>
                                    <input
                                        type="<?=htmlspecialchars($field_type === "" ? "text" : $field_type, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
                                        data-dabsic-field="<?=$field_key; ?>"
                                        value="<?=htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
                                        autocomplete="off"
                                        spellcheck="false"
                                    />
                                <?php } ?>
                            <?php } else { ?>
                                <div class="dabsic-form-readonly-value"><?php $display = form_field_display_values($definition, $value); ?><?=count($display) ? htmlspecialchars(implode(", ", $display), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") : "—"; ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </fieldset>
            <?php } ?>

            <?php if (count($dabsic_form_readonly_groups)) { ?>
                <details class="dabsic-form-readonly-groups">
                    <summary><?=$Dictionnary["DocumentOtherInformation"] ?? "Autres informations du document"; ?></summary>
                    <?php foreach ($dabsic_form_readonly_groups as $group) { ?>
                        <fieldset class="dabsic-form-group is-readonly">
                            <legend><?=htmlspecialchars((string)$group["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></legend>
                            <?php foreach (($group["fields"] ?? []) as $field) {
                                $definition = $dabsic_form_metadata["fields"][$field] ?? ["label" => $field, "required" => false];
                                $value = $dabsic_form_values[$field] ?? "";
                            ?>
                                <div class="dabsic-form-field is-readonly">
                                    <span><?=htmlspecialchars((string)$definition["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span>
                                    <div class="dabsic-form-readonly-value"><?php $display = form_field_display_values($definition, $value); ?><?=count($display) ? htmlspecialchars(implode(", ", $display), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") : "—"; ?></div>
                                </div>
                            <?php } ?>
                        </fieldset>
                    <?php } ?>
                </details>
            <?php } ?>

            <fieldset class="dabsic-form-group dabsic-form-overrides">
                <legend><?=$Dictionnary["DabsicFormOverrides"]; ?></legend>
                <p class="dabsic-form-overrides-help"><?=$Dictionnary["DabsicFormOverridesHelp"]; ?></p>
                <div id="dabsic-form-overrides-list">
                    <?php foreach ($dabsic_form_overrides as $key => $value) { ?>
                        <div class="dabsic-form-override-row">
                            <input class="dabsic-form-override-key" type="text" value="<?=htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>" autocomplete="off" spellcheck="false" />
                            <input class="dabsic-form-override-value" type="text" value="<?=htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>" autocomplete="off" spellcheck="false" />
                            <button class="dabsic-form-override-remove" type="button"><?=$Dictionnary["Delete"]; ?></button>
                        </div>
                    <?php } ?>
                </div>
                <button id="dabsic-form-override-add" class="dabsic-form-override-add" type="button">+ <?=$Dictionnary["DabsicFormAddOverride"]; ?></button>
            </fieldset>

            <div id="dabsic-form-message" class="dabsic-form-message" aria-live="assertive"></div>

            <div class="dabsic-form-actions">
                <span>
                    <?=$Dictionnary["DabsicFormFieldCount"]; ?>: <?=count(dabsic_form_role_fields($dabsic_form_metadata, $dabsic_form_role, "edit")); ?>
                    <?php if ($dabsic_form_post_interview_prospect_id > 0) { ?>
                        — la validation apposera la signature de l'auteur et le tampon de l'école, puis enverra le PDF.
                    <?php } ?>
                </span>
                <div class="dabsic-form-action-buttons">
                    <input id="dabsic-form-save" class="dabsic-form-save" type="submit" value="<?=$Dictionnary["Save"]; ?>" disabled />
                    <?php if ($dabsic_form_post_interview_prospect_id > 0) { ?>
                        <input id="dabsic-form-finalize" class="dabsic-form-finalize" type="button" value="Valider, générer et envoyer" />
                    <?php } ?>
                </div>
            </div>
        </form>

        <script>
        <?php require (__DIR__."/script.js"); ?>
        </script>
    <?php } ?>
</div>
