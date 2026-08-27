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
                    <?=form_field_render($question, NULL, [
                        "name" => "preview-".$group["key"]."-".$question["key"],
                        "disabled" => true,
                    ]); ?>
                </div>
            <?php } ?>
        </fieldset>
    <?php } ?>
</div>
