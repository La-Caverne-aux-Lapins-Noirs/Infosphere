<?php

require_once (__DIR__."/dabsic_editor.php");

function dabsic_editor_component($file, array $options = [])
{
    global $Dictionnary;

    static $style_loaded = false;
    static $script_loaded = false;
    static $counter = 0;

    $options = array_merge([
        "embedded" => false,
        "show_title" => true,
        "show_path" => true,
        "autofocus" => true,
        "save_url" => "/api/dabsic/0/save",
        "extra_fields" => [],
        "extensions" => dabsic_editor_editable_extensions(),
    ], $options);

    $resolved = dabsic_editor_resolve_file((string)$file, false, $options["extensions"]);
    $error = "";
    $content = "";
    $hash = "";
    $mode = "dab";
    if (!$resolved["ok"])
    {
        if (!$options["embedded"] && !headers_sent())
            http_response_code(404);
        $error = $Dictionnary[$resolved["error"]] ?? ($Dictionnary["InvalidFile"] ?? "Invalid file");
    }
    else
    {
        $mode = $resolved["extension"] ?? "dab";
        $content = @file_get_contents($resolved["absolute"]);
        if ($content === false)
            $error = $Dictionnary["DabsicEditorCannotRead"] ?? "Cannot read file";
        else
            $hash = hash("sha256", $content);
    }

    ++$counter;
    $id = "dabsic-editor-".$counter;
    $extra = json_encode($options["extra_fields"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($extra === false)
        $extra = "{}";

    if (!$style_loaded)
    {
        $style_loaded = true;
        echo "<style>\n";
        require (__DIR__."/../pages/dabsic_editor/style.css");
        echo "\n</style>\n";
    }
?>
<div class="dabsic-editor-page<?=$options["embedded"] ? " is-embedded" : ""; ?>">
    <div
        id="<?=htmlspecialchars($id, ENT_QUOTES, "UTF-8"); ?>"
        class="dabsic-editor-root"
        data-dabsic-editor="1"
        data-file="<?=htmlspecialchars($resolved["relative"] ?? (string)$file, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-editor-mode="<?=htmlspecialchars($mode, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-hash="<?=htmlspecialchars($hash, ENT_QUOTES, "UTF-8"); ?>"
        data-save-url="<?=htmlspecialchars((string)$options["save_url"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-extra-fields="<?=htmlspecialchars($extra, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-autofocus="<?=$options["autofocus"] ? "1" : "0"; ?>"
        data-confirm-save="<?=htmlspecialchars($Dictionnary["DabsicEditorConfirmSave"] ?? "Save changes?", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-unsaved-warning="<?=htmlspecialchars($Dictionnary["DabsicEditorUnsavedWarning"] ?? "Unsaved changes.", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-clean-label="<?=htmlspecialchars($Dictionnary["DabsicEditorUnmodified"] ?? "No changes", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-dirty-label="<?=htmlspecialchars($Dictionnary["DabsicEditorModified"] ?? "Modified", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-saving-label="<?=htmlspecialchars($Dictionnary["DabsicEditorSaving"] ?? "Saving…", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-saved-label="<?=htmlspecialchars($Dictionnary["DabsicEditorSaved"] ?? "Saved.", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-network-error="<?=htmlspecialchars($Dictionnary["DabsicEditorNetworkError"] ?? "Network error.", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
        data-context-error="<?=htmlspecialchars($Dictionnary["DabsicEditorContextError"] ?? "Contexte d’édition invalide.", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
    >
        <?php if ($options["show_title"] || ($options["show_path"] && $resolved["ok"])) { ?>
            <div class="dabsic-editor-header">
                <div>
                    <?php if ($options["show_title"]) { ?>
                        <h2><?=$Dictionnary["DabsicEditor"] ?? "Dabsic"; ?></h2>
                    <?php } ?>
                    <?php if ($options["show_path"] && $resolved["ok"]) { ?>
                        <code class="dabsic-editor-path"><?=htmlspecialchars($resolved["relative"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
                    <?php } ?>
                </div>
                <div class="dabsic-editor-state" aria-live="polite"></div>
            </div>
        <?php } else { ?>
            <div class="dabsic-editor-state dabsic-editor-state-compact" aria-live="polite"></div>
        <?php } ?>

        <?php if ($error !== "") { ?>
            <div class="dabsic-editor-message is-error">
                <?=htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>
            </div>
        <?php } else { ?>
            <div class="dabsic-editor-surface">
                <pre class="dabsic-editor-highlight" aria-hidden="true"><code></code></pre>
                <textarea
                    class="dabsic-editor-input"
                    wrap="off"
                    spellcheck="false"
                    autocapitalize="off"
                    autocomplete="off"
                    autocorrect="off"
                    aria-label="<?=$Dictionnary["DabsicEditor"] ?? "Dabsic"; ?>"
                ><?=htmlspecialchars($content, ENT_NOQUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></textarea>
            </div>
            <div class="dabsic-editor-message" aria-live="assertive"></div>
            <div class="dabsic-editor-actions">
                <span class="dabsic-editor-hint"><?=$Dictionnary["DabsicEditorHint"] ?? ""; ?></span>
                <input class="dabsic-editor-save" type="button" value="<?=$Dictionnary["Save"] ?? "Save"; ?>" disabled />
            </div>
        <?php } ?>
    </div>
</div>
<?php
    if (!$script_loaded)
    {
        $script_loaded = true;
        echo "<script>\n";
        require (__DIR__."/../pages/dabsic_editor/script.js");
        echo "\n</script>\n";
    }
    else if ($error === "")
    {
?>
<script>
if (window.DabsicEditor)
    window.DabsicEditor.attach(document.getElementById(<?=json_encode($id); ?>));
</script>
<?php
    }
}
