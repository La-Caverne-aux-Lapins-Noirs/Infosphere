<?php $questionnaire_page_all_usages = $questionnaire_page_catalog_usages ?? questionnaire_activity_usages(); ?>
<div class="questionnaire-usage-heading">
    <div>
        <h3><?=$Dictionnary["QuestionnaireUsages"] ?? "Utilisations"; ?></h3>
        <p><?=$Dictionnary["QuestionnaireUsagesHelp"] ?? "Les utilisations sont déduites des @include/@insert Dabsic des configuration.dab d’activité ; aucune table de liaison parallèle n’est nécessaire."; ?></p>
    </div>
    <span><?=count($questionnaire_page_all_usages); ?></span>
</div>
<div class="questionnaire-usage-list">
    <?php foreach ($questionnaire_page_all_usages as $usage) {
        $quiz = $usage["quiz"];
        $activity = $usage["activity"];
        $loaded = questionnaire_load_model($quiz);
        $quiz_name = $loaded["ok"] ? $loaded["model"]["name"] : $quiz["codename"];
        $activity_name = trim((string)($activity["localized_name"] ?? "")) ?: $activity["codename"];
    ?>
        <article class="questionnaire-usage-card">
            <div>
                <strong><?=htmlspecialchars($quiz_name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></strong>
                <code><?=htmlspecialchars($quiz["school_codename"]."/".$quiz["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            </div>
            <div>
                <strong><?=htmlspecialchars($activity_name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></strong>
                <code><?=htmlspecialchars($activity["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            </div>
            <div>
                <span class="questionnaire-usage-badge"><?=htmlspecialchars((string)$usage["language"], ENT_QUOTES); ?></span>
                <span class="questionnaire-usage-badge <?=$usage["depth"] === 0 ? "is-direct" : "is-indirect"; ?>">
                    <?=$usage["depth"] === 0 ? ($Dictionnary["QuestionnaireUsageDirect"] ?? "direct") : ($Dictionnary["QuestionnaireUsageIndirect"] ?? "indirect"); ?>
                </span>
                <code><?=htmlspecialchars($usage["configuration"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            </div>
            <div class="questionnaire-list-actions">
                <a class="questionnaire-secondary" href="index.php?p=QuestionnaireMenu&amp;a=<?=(int)$quiz["id"]; ?>"><?=$Dictionnary["Open"] ?? "Ouvrir"; ?></a>
                <a class="questionnaire-secondary" href="index.php?p=ActivityMenu&amp;a=<?=(int)$activity["id"]; ?>"><?=$Dictionnary["QuestionnaireOpenActivity"] ?? "Ouvrir l’activité"; ?></a>
            </div>
        </article>
    <?php } ?>
    <?php if (!count($questionnaire_page_all_usages)) { ?>
        <div class="questionnaire-notice questionnaire-empty"><?=$Dictionnary["QuestionnaireNoUsage"] ?? "Aucune configuration d’activité ne référence encore de questionnaire."; ?></div>
    <?php } ?>
</div>
