<?php $preview_model = $questionnaire_page_model; ?>
<div class="questionnaire-preview">
    <div class="questionnaire-notice">
        <strong><?=$Dictionnary["QuestionnairePreview"] ?? "Aperçu"; ?></strong>
        <p><?=$Dictionnary["QuestionnairePreviewHelp"] ?? "Cet aperçu n’enregistre aucune réponse. Il permet seulement de vérifier ce que le répondant verra."; ?></p>
    </div>
    <header class="questionnaire-preview-header">
        <h2><?=htmlspecialchars($preview_model["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></h2>
        <?php if (trim((string)$preview_model["description"]) != "") { ?>
            <p><?=nl2br(htmlspecialchars($preview_model["description"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")); ?></p>
        <?php } ?>
    </header>
    <?php foreach ($preview_model["groups"] as $group) { ?>
        <fieldset class="questionnaire-preview-group">
            <legend><?=htmlspecialchars($group["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></legend>
            <?php if (!count($group["questions"])) { ?>
                <p class="questionnaire-empty"><?=$Dictionnary["QuestionnaireNoQuestions"] ?? "Aucune question dans ce groupe."; ?></p>
            <?php } ?>
            <?php foreach ($group["questions"] as $question) { ?>
                <div class="questionnaire-preview-question">
                    <label class="questionnaire-preview-label">
                        <?=htmlspecialchars($question["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>
                        <?php if ($question["required"]) { ?><span class="questionnaire-required">*</span><?php } ?>
                    </label>
                    <?php if ($question["type"] == "textarea") { ?>
                        <textarea rows="4" disabled></textarea>
                    <?php } else if ($question["type"] == "radio" || $question["type"] == "checkbox") { ?>
                        <div class="questionnaire-preview-choices">
                            <?php foreach ($question["choices"] as $ci => $choice) { ?>
                                <label>
                                    <input type="<?=$question["type"] == "radio" ? "radio" : "checkbox"; ?>" name="preview-<?=htmlspecialchars($group["key"]."-".$question["key"], ENT_QUOTES); ?>" disabled />
                                    <span><?=htmlspecialchars($choice, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span>
                                </label>
                            <?php } ?>
                            <?php if (!count($question["choices"])) { ?>
                                <span class="questionnaire-empty"><?=$Dictionnary["QuestionnaireNoChoices"] ?? "Aucune proposition définie."; ?></span>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <input type="text" disabled />
                    <?php } ?>
                </div>
            <?php } ?>
        </fieldset>
    <?php } ?>
</div>
