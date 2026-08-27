<?php $questionnaire_page_usages = questionnaire_usages((int)$questionnaire_page_row["id"]); ?>
<div class="questionnaire-usage-detail">
    <div class="questionnaire-usage-heading">
        <div>
            <h3><?=$Dictionnary["QuestionnaireUsages"] ?? "Utilisations"; ?></h3>
            <p><?=$Dictionnary["QuestionnaireUsageDetailHelp"] ?? "Les dépendances Dabsic vers ce questionnaire sont détectées dans les points d’entrée de l’activité, y compris à travers des inclusions intermédiaires."; ?></p>
        </div>
        <span><?=count($questionnaire_page_usages); ?></span>
    </div>

    <div class="questionnaire-notice questionnaire-usage-design-note">
        <?=$Dictionnary["QuestionnaireUsageNaturePending"] ?? "Le rôle métier d’un questionnaire pourra être porté par son point d’entrée Dabsic ; la ressource elle-même reste réutilisable."; ?>
    </div>

    <div class="questionnaire-usage-list">
        <?php foreach ($questionnaire_page_usages as $usage) {
            $usage_context = (string)($usage["context"] ?? "activity");
            $is_support = $usage_context === "support";
            $is_support_asset = $usage_context === "support_asset";
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
                <div>
                    <small><?=$Dictionnary["QuestionnaireUsageDirective"] ?? "Référence"; ?></small><br />
                    <code>@<?=htmlspecialchars($usage["directive"], ENT_QUOTES); ?> <?=htmlspecialchars((string)$usage["requested_path"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
                </div>
                <div class="questionnaire-list-actions">
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
        <?php if (!count($questionnaire_page_usages)) { ?>
            <div class="questionnaire-notice questionnaire-empty"><?=$Dictionnary["QuestionnaireNoUsageForQuiz"] ?? "Ce questionnaire n’est encore inclus par aucune configuration d’activité."; ?></div>
        <?php } ?>
    </div>
</div>
