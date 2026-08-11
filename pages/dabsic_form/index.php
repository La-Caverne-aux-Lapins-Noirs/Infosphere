<?php

if (!is_admin())
{
    http_response_code(404);
    die();
}

require_once (__DIR__."/../../tools/dabsic_form.php");

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
            $dabsic_form_values = $dabsic_form_loaded["values"];
            if (!$dabsic_form_output["exists"] && $dabsic_form_chain !== "")
                $dabsic_form_values = array_merge(dabsic_form_prefill_from_chain($dabsic_form_chain), $dabsic_form_values);
            $dabsic_form_output_hash = $dabsic_form_loaded["hash"];
            $dabsic_form_output_exists = $dabsic_form_output["exists"];
            $dabsic_form_existing_fields = array_keys($dabsic_form_values);
            $dabsic_form_required_fields = $dabsic_form_discovery["fields"];
            natcasesort($dabsic_form_existing_fields);
            natcasesort($dabsic_form_required_fields);
            $dabsic_form_output_complete =
                $dabsic_form_output_exists &&
                array_values($dabsic_form_existing_fields) === array_values($dabsic_form_required_fields);
        }
    }
}

$dabsic_form_groups = [];
if ($dabsic_form_error === "" && $dabsic_form_discovery["ok"])
    foreach ($dabsic_form_discovery["fields"] as $field)
    {
        $parts = explode(".", $field);
        $group = count($parts) > 1 ? $parts[0] : $Dictionnary["DabsicFormRootFields"];
        if (!isset($dabsic_form_groups[$group]))
            $dabsic_form_groups[$group] = [];
        $dabsic_form_groups[$group][] = $field;
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
                <div class="dabsic-form-path-line">
                    <span><?=$Dictionnary["DabsicFormReference"]; ?></span>
                    <code><?=htmlspecialchars($dabsic_form_discovery["reference"]["relative"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
                </div>
            <?php } ?>
            <?php if ($dabsic_form_output["ok"]) { ?>
                <div class="dabsic-form-path-line">
                    <span><?=$Dictionnary["OutputFile"]; ?></span>
                    <code><?=htmlspecialchars($dabsic_form_output["relative"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
                    <small><?=htmlspecialchars($dabsic_form_output["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></small>
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
            data-reference-hash="<?=htmlspecialchars($dabsic_form_reference_hash, ENT_QUOTES, "UTF-8"); ?>"
            data-output="<?=htmlspecialchars($dabsic_form_output["key"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-output-hash="<?=htmlspecialchars($dabsic_form_output_hash, ENT_QUOTES, "UTF-8"); ?>"
            data-output-exists="<?=$dabsic_form_output_exists ? "1" : "0"; ?>"
            data-output-complete="<?=$dabsic_form_output_complete ? "1" : "0"; ?>"
            data-overrides-hash="<?=htmlspecialchars($dabsic_form_overrides_hash, ENT_QUOTES, "UTF-8"); ?>"
            data-overrides-exists="<?=$dabsic_form_overrides_exists ? "1" : "0"; ?>"
            data-save-url="/api/dabsic/0/form"
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
            <p class="dabsic-form-introduction">
                <?=$Dictionnary["DabsicFormIntroduction"]; ?>
            </p>

            <?php if (!count($dabsic_form_discovery["fields"])) { ?>
                <div class="dabsic-form-message is-success"><?=$Dictionnary["DabsicFormNothingMissing"]; ?></div>
            <?php } ?>

            <?php foreach ($dabsic_form_groups as $group => $fields) { ?>
                <fieldset class="dabsic-form-group">
                    <legend><?=htmlspecialchars($group, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></legend>
                    <?php foreach ($fields as $field) {
                        $value = $dabsic_form_values[$field] ?? "";
                    ?>
                        <label class="dabsic-form-field">
                            <code><?=htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
                            <input
                                type="text"
                                data-dabsic-field="<?=htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
                                value="<?=htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
                                autocomplete="off"
                                spellcheck="false"
                            />
                        </label>
                    <?php } ?>
                </fieldset>
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
                <span><?=$Dictionnary["DabsicFormFieldCount"]; ?>: <?=count($dabsic_form_discovery["fields"]); ?></span>
                <input id="dabsic-form-save" class="dabsic-form-save" type="submit" value="<?=$Dictionnary["Save"]; ?>" disabled />
            </div>
        </form>

        <script>
        <?php require (__DIR__."/script.js"); ?>
        </script>
    <?php } ?>
</div>
