<?php

if (!is_admin())
{
    http_response_code(404);
    die();
}

require_once (__DIR__."/../../tools/dabsic_editor.php");

$dabsic_editor_file = $_GET["file"] ?? "";
$dabsic_editor_resolved = dabsic_editor_resolve_file($dabsic_editor_file, false);
$dabsic_editor_error = "";
$dabsic_editor_content = "";
$dabsic_editor_hash = "";

if (!$dabsic_editor_resolved["ok"])
{
    http_response_code(404);
    $dabsic_editor_error = $Dictionnary[$dabsic_editor_resolved["error"]] ?? $Dictionnary["InvalidFile"];
}
else
{
    $dabsic_editor_content = file_get_contents($dabsic_editor_resolved["absolute"]);
    if ($dabsic_editor_content === false)
        $dabsic_editor_error = $Dictionnary["DabsicEditorCannotRead"];
    else
        $dabsic_editor_hash = hash("sha256", $dabsic_editor_content);
}
?>

<style>
<?php require (__DIR__."/style.css"); ?>
</style>

<div class="dabsic-editor-page">
    <div class="dabsic-editor-header">
        <div>
            <h2><?=$Dictionnary["DabsicEditor"]; ?></h2>
            <?php if ($dabsic_editor_resolved["ok"]) { ?>
                <code class="dabsic-editor-path"><?=htmlspecialchars($dabsic_editor_resolved["relative"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            <?php } ?>
        </div>
        <div id="dabsic-editor-state" class="dabsic-editor-state" aria-live="polite"></div>
    </div>

    <?php if ($dabsic_editor_error !== "") { ?>
        <div class="dabsic-editor-message is-error">
            <?=htmlspecialchars($dabsic_editor_error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>
        </div>
    <?php } else { ?>
        <div
            id="dabsic-editor-root"
            class="dabsic-editor-root"
            data-file="<?=htmlspecialchars($dabsic_editor_resolved["relative"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-hash="<?=htmlspecialchars($dabsic_editor_hash, ENT_QUOTES, "UTF-8"); ?>"
            data-save-url="/api/dabsic/0/save"
            data-confirm-save="<?=htmlspecialchars($Dictionnary["DabsicEditorConfirmSave"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-unsaved-warning="<?=htmlspecialchars($Dictionnary["DabsicEditorUnsavedWarning"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-clean-label="<?=htmlspecialchars($Dictionnary["DabsicEditorUnmodified"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-dirty-label="<?=htmlspecialchars($Dictionnary["DabsicEditorModified"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-saving-label="<?=htmlspecialchars($Dictionnary["DabsicEditorSaving"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-saved-label="<?=htmlspecialchars($Dictionnary["DabsicEditorSaved"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            data-network-error="<?=htmlspecialchars($Dictionnary["DabsicEditorNetworkError"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        >
            <div class="dabsic-editor-surface">
                <pre id="dabsic-editor-highlight" class="dabsic-editor-highlight" aria-hidden="true"><code></code></pre>
                <textarea
                    id="dabsic-editor-input"
                    class="dabsic-editor-input"
                    wrap="off"
                    spellcheck="false"
                    autocapitalize="off"
                    autocomplete="off"
                    autocorrect="off"
                    aria-label="<?=$Dictionnary["DabsicEditor"]; ?>"
                ><?=htmlspecialchars($dabsic_editor_content, ENT_NOQUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></textarea>
            </div>

            <div id="dabsic-editor-message" class="dabsic-editor-message" aria-live="assertive"></div>

            <div class="dabsic-editor-actions">
                <span class="dabsic-editor-hint"><?=$Dictionnary["DabsicEditorHint"]; ?></span>
                <input
                    id="dabsic-editor-save"
                    class="dabsic-editor-save"
                    type="button"
                    value="<?=$Dictionnary["Save"]; ?>"
                    disabled
                />
            </div>
        </div>

        <script>
        <?php require (__DIR__."/script.js"); ?>
        </script>
    <?php } ?>
</div>
