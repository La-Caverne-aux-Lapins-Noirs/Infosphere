<?php
$model = $questionnaire_page_model;
$advanced = !empty($model["advanced"]);
?>
<div class="questionnaire-definition-panel">
    <?php if ($advanced) { ?>
        <div class="questionnaire-notice questionnaire-notice-warning">
            <strong><?=$Dictionnary["QuestionnaireAdvancedSourceTitle"] ?? "Définition Dabsic avancée"; ?></strong>
            <p><?=$Dictionnary["QuestionnaireAdvancedSourceHelp"] ?? "Cette définition contient des éléments que l’éditeur visuel ne sait pas représenter sans perte. Elle reste consultable et modifiable dans l’onglet Source Dabsic."; ?></p>
            <?php if (is_array($model["advanced_reasons"] ?? NULL) && count($model["advanced_reasons"])) { ?>
                <details>
                    <summary><?=$Dictionnary["QuestionnaireAdvancedDetails"] ?? "Éléments non représentables"; ?></summary>
                    <code><?=htmlspecialchars(implode("\n", $model["advanced_reasons"]), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
                </details>
            <?php } ?>
        </div>
    <?php } ?>

    <form method="post" class="questionnaire-builder" data-questionnaire-builder data-readonly="<?=$advanced ? "1" : "0"; ?>">
        <input type="hidden" name="action" value="save_visual" />
        <input type="hidden" name="questionnaire_id" value="<?=(int)$questionnaire_page_row["id"]; ?>" />
        <input type="hidden" name="expected_hash" value="<?=htmlspecialchars($questionnaire_page_loaded["hash"], ENT_QUOTES, "UTF-8"); ?>" />
        <input type="hidden" name="model" value="" data-questionnaire-model-output />
        <script type="application/json" data-questionnaire-model><?=json_encode($model, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>

        <section class="questionnaire-builder-general">
            <div class="questionnaire-section-heading">
                <div>
                    <h3><?=$Dictionnary["QuestionnaireGeneral"] ?? "Questionnaire"; ?></h3>
                    <p><?=$Dictionnary["QuestionnaireGeneralHelp"] ?? "Ces informations appartiennent au fichier Dabsic, pas à la base SQL."; ?></p>
                </div>
                <code><?=htmlspecialchars($questionnaire_page_row["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            </div>
            <div class="questionnaire-form-grid">
                <label class="questionnaire-field questionnaire-field-wide">
                    <span><?=$Dictionnary["Name"] ?? "Nom"; ?></span>
                    <input type="text" data-q-general="name" value="<?=htmlspecialchars($model["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>" <?=$advanced ? "disabled" : ""; ?> required />
                </label>
                <label class="questionnaire-field questionnaire-field-wide">
                    <span><?=$Dictionnary["Description"] ?? "Description"; ?></span>
                    <textarea rows="3" data-q-general="description" <?=$advanced ? "disabled" : ""; ?>><?=htmlspecialchars($model["description"], ENT_NOQUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></textarea>
                </label>
                <label class="questionnaire-field">
                    <span><?=$Dictionnary["QuestionnaireMinimumPercent"] ?? "Seuil global (%)"; ?></span>
                    <input type="number" min="0" max="100" step="0.01" data-q-general="minimum_percent" value="<?=htmlspecialchars((string)$model["minimum_percent"], ENT_QUOTES); ?>" <?=$advanced ? "disabled" : ""; ?> />
                </label>
                <label class="questionnaire-field questionnaire-field-wide">
                    <span><?=$Dictionnary["QuestionnaireMedals"] ?? "Médailles"; ?></span>
                    <textarea rows="2" data-q-general="medals" placeholder="abc&#10;def" <?=$advanced ? "disabled" : ""; ?>><?=htmlspecialchars(implode("\n", $model["medals"]), ENT_NOQUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></textarea>
                    <small><?=$Dictionnary["QuestionnaireMedalsHelp"] ?? "Une médaille par ligne. Au niveau du questionnaire, elles seront associées à l’atteinte du seuil global."; ?></small>
                </label>
            </div>
        </section>

        <div class="questionnaire-groups" data-questionnaire-groups></div>

        <?php if (!$advanced) { ?>
            <div class="questionnaire-builder-footer">
                <button type="button" class="questionnaire-secondary" data-q-add-group>+ <?=$Dictionnary["QuestionnaireAddGroup"] ?? "Ajouter un groupe"; ?></button>
                <button type="submit" class="questionnaire-primary"><?=$Dictionnary["Save"] ?? "Enregistrer"; ?></button>
            </div>
        <?php } ?>
    </form>
</div>
