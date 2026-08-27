<?php $questionnaire_page_all_usages = $questionnaire_page_catalog_usages ?? questionnaire_usages(); ?>
<div class="questionnaire-usage-heading">
    <div>
        <h3><?=$Dictionnary["QuestionnaireUsages"] ?? "Utilisations"; ?></h3>
        <p><?=$Dictionnary["QuestionnaireUsagesHelp"] ?? "Les utilisations sont déduites des points d’entrée Dabsic de l’activité (configuration.dab, preaccess.dab, …) ; aucune table de liaison parallèle n’est nécessaire."; ?></p>
    </div>
    <span><?=count($questionnaire_page_all_usages); ?></span>
</div>
<div class="questionnaire-usage-list">
    <?php foreach ($questionnaire_page_all_usages as $usage) {
        $quiz = $usage["quiz"];
        $usage_context = (string)($usage["context"] ?? "activity");
        $is_support = $usage_context === "support";
        $is_support_asset = $usage_context === "support_asset";
        $loaded = questionnaire_load_model($quiz);
        $quiz_name = $loaded["ok"] ? $loaded["model"]["name"] : $quiz["codename"];
        if ($is_support_asset)
        {
            $asset = $usage["asset"];
            $target_name = trim((string)($asset[$Language."_name"] ?? "")) ?: $asset["codename"];
            $target_code = $asset["category_codename"]."/".$asset["support_codename"]."/#".$asset["codename"];
        }
        else if ($is_support)
        {
            $support = $usage["support"];
            $target_name = trim((string)($support[$Language."_name"] ?? "")) ?: $support["codename"];
            $target_code = $support["category_codename"]."/".$support["codename"];
        }
        else
        {
            $activity = $usage["activity"];
            $target_name = trim((string)($activity["localized_name"] ?? "")) ?: $activity["codename"];
            $target_code = $activity["codename"];
        }
    ?>
        <article class="questionnaire-usage-card">
            <div>
                <strong><?=htmlspecialchars($quiz_name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></strong>
                <code><?=htmlspecialchars($quiz["school_codename"]."/".$quiz["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            </div>
            <div>
                <strong><?=htmlspecialchars($target_name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></strong>
                <code><?=htmlspecialchars($target_code, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            </div>
            <div>
                <span class="questionnaire-usage-badge"><?php
                    if ($is_support_asset)
                        echo $Dictionnary["SupportAsset"] ?? "Ressource";
                    else if ($is_support)
                        echo $Dictionnary["Support"] ?? "Support";
                    else if (($activity["parent_activity"] ?? NULL) === NULL)
                        echo $Dictionnary["Matter"] ?? "Matière";
                    else
                        echo $Dictionnary["Activity"] ?? "Activité";
                ?></span>
                <span class="questionnaire-usage-badge"><?=htmlspecialchars((string)($usage["entrypoint"] ?? "configuration"), ENT_QUOTES); ?></span>
                <span class="questionnaire-usage-badge"><?=htmlspecialchars((string)$usage["language"], ENT_QUOTES); ?></span>
                <span class="questionnaire-usage-badge <?=$usage["depth"] === 0 ? "is-direct" : "is-indirect"; ?>">
                    <?=$usage["depth"] === 0 ? ($Dictionnary["QuestionnaireUsageDirect"] ?? "direct") : ($Dictionnary["QuestionnaireUsageIndirect"] ?? "indirect"); ?>
                </span>
                <code><?=htmlspecialchars($usage["configuration"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            </div>
            <div class="questionnaire-list-actions">
                <a class="questionnaire-secondary" href="index.php?p=QuestionnaireMenu&amp;a=<?=(int)$quiz["id"]; ?>"><?=$Dictionnary["Open"] ?? "Ouvrir"; ?></a>
                <?php if ($is_support_asset) { ?>
                    <a class="questionnaire-secondary" href="index.php?p=SupportAssetMenu&amp;a=<?=(int)$asset["id"]; ?>"><?=$Dictionnary["QuestionnaireOpenSupportAsset"] ?? "Ouvrir la ressource"; ?></a>
                <?php } else if ($is_support) { ?>
                    <a class="questionnaire-secondary" href="index.php?p=SupportMenu&amp;a=<?=(int)$support["id"]; ?>"><?=$Dictionnary["QuestionnaireOpenSupport"] ?? "Ouvrir le support"; ?></a>
                <?php } else { ?>
                    <a class="questionnaire-secondary" href="index.php?p=ActivityMenu&amp;a=<?=(int)$activity["id"]; ?>"><?=$Dictionnary["QuestionnaireOpenActivity"] ?? "Ouvrir l’activité"; ?></a>
                <?php } ?>
            </div>
        </article>
    <?php } ?>
    <?php if (!count($questionnaire_page_all_usages)) { ?>
        <div class="questionnaire-notice questionnaire-empty"><?=$Dictionnary["QuestionnaireNoUsage"] ?? "Aucune configuration d’activité ne référence encore de questionnaire."; ?></div>
    <?php } ?>
</div>
