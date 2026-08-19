<?php
require_once (__DIR__."/../../tools/dabsic_editor_component.php");
?>
<div class="questionnaire-source-panel">
    <div class="questionnaire-source-intro">
        <p><?=$Dictionnary["QuestionnaireSourceHelp"] ?? "Le fichier Dabsic est la définition canonique. Les modifications sont validées par mergeconf avant enregistrement."; ?></p>
    </div>
    <?php dabsic_editor_component($questionnaire_page_row["reference"], [
        "embedded" => true,
        "show_title" => false,
        "show_path" => true,
        "autofocus" => false,
        "save_url" => "/api/questionnaire/".(int)$questionnaire_page_row["id"]."/source",
        "extensions" => ["dab"],
    ]); ?>
</div>
