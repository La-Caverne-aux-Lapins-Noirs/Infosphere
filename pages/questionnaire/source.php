<?php
require_once (__DIR__."/../../tools/dabsic_editor_component.php");
?>
<div class="questionnaire-source-panel">
    <div class="questionnaire-source-intro">
        <p><?=$Dictionnary["QuestionnaireSourceHelp"] ?? "Le fichier Dabsic est la définition canonique. Les modifications sont validées par mergeconf avant enregistrement."; ?></p>
        <details class="questionnaire-source-contract">
            <summary><?=$Dictionnary["QuestionnaireSourceV2Title"] ?? "Contrat FormGroup V2"; ?></summary>
            <p><?=$Dictionnary["QuestionnaireSourceV2Help"] ?? "Choices contient les libellés visibles. ChoiceValues contient les identifiants stables stockés et référencés par Correct."; ?></p>
            <pre>[Question
  Label = "Langage principal"
  Type = "radio"
  {Choices
    "C",
    "C++ moderne"
  }
  {ChoiceValues
    "c",
    "cpp"
  }
  {Correct
    "cpp"
  }
]</pre>
        </details>
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
