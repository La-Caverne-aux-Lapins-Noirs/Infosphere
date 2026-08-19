<?php $questionnaire_page_usages = questionnaire_activity_usages((int)$questionnaire_page_row["id"]); ?>
<div class="questionnaire-usage-detail">
    <div class="questionnaire-usage-heading">
        <div>
            <h3><?=$Dictionnary["QuestionnaireUsages"] ?? "Utilisations"; ?></h3>
            <p><?=$Dictionnary["QuestionnaireUsageDetailHelp"] ?? "Les dépendances Dabsic vers ce questionnaire sont détectées dans les configuration.dab d’activité, y compris à travers des inclusions intermédiaires."; ?></p>
        </div>
        <span><?=count($questionnaire_page_usages); ?></span>
    </div>

    <div class="questionnaire-notice questionnaire-usage-design-note">
        <?=$Dictionnary["QuestionnaireUsageNaturePending"] ?? "Le rôle métier d’un questionnaire pourra être porté par son point d’entrée Dabsic ; la ressource elle-même reste réutilisable."; ?>
    </div>

    <div class="questionnaire-usage-list">
        <?php foreach ($questionnaire_page_usages as $usage) {
            $activity = $usage["activity"];
            $activity_name = trim((string)($activity["localized_name"] ?? "")) ?: $activity["codename"];
        ?>
            <article class="questionnaire-usage-card">
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
                <div>
                    <small><?=$Dictionnary["QuestionnaireUsageDirective"] ?? "Référence"; ?></small><br />
                    <code>@<?=htmlspecialchars($usage["directive"], ENT_QUOTES); ?> <?=htmlspecialchars((string)$usage["requested_path"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
                </div>
                <div class="questionnaire-list-actions">
                    <a class="questionnaire-secondary" href="index.php?p=ActivityMenu&amp;a=<?=(int)$activity["id"]; ?>"><?=$Dictionnary["QuestionnaireOpenActivity"] ?? "Ouvrir l’activité"; ?></a>
                </div>
            </article>
        <?php } ?>
        <?php if (!count($questionnaire_page_usages)) { ?>
            <div class="questionnaire-notice questionnaire-empty"><?=$Dictionnary["QuestionnaireNoUsageForQuiz"] ?? "Ce questionnaire n’est encore inclus par aucune configuration d’activité."; ?></div>
        <?php } ?>
    </div>
</div>
