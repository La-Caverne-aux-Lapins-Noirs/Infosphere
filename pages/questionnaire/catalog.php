<?php
$questionnaire_page_rows = questionnaire_list();
$questionnaire_page_usage_counts = [];
foreach ((array)($questionnaire_page_catalog_usages ?? []) as $usage)
{
    $quiz_id = (int)($usage["quiz"]["id"] ?? 0);
    if ($quiz_id > 0)
        $questionnaire_page_usage_counts[$quiz_id] = ($questionnaire_page_usage_counts[$quiz_id] ?? 0) + 1;
}
?>
<div class="questionnaire-list-heading">
    <h3><?=$Dictionnary["QuestionnaireExisting"] ?? "Questionnaires existants"; ?></h3>
    <span><?=count($questionnaire_page_rows); ?></span>
</div>
<div class="questionnaire-list">
    <?php foreach ($questionnaire_page_rows as $row) {
        $loaded = questionnaire_load_model($row);
        $model = $loaded["ok"] ? $loaded["model"] : questionnaire_default_model($row["codename"], $row["codename"]);
        $counts = questionnaire_model_counts($model);
    ?>
        <article class="questionnaire-list-card">
            <div>
                <h3><?=htmlspecialchars($model["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></h3>
                <p><code><?=htmlspecialchars($row["school_codename"]."/".$row["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code></p>
            </div>
            <div>
                <strong><?=$counts["questions"]; ?></strong> <?=$Dictionnary["QuestionnaireQuestions"] ?? "questions"; ?><br />
                <span><?=$counts["groups"]; ?> <?=$Dictionnary["QuestionnaireGroups"] ?? "groupes"; ?></span>
            </div>
            <div>
                <strong><?=($questionnaire_page_usage_counts[(int)$row["id"]] ?? 0); ?></strong> <?=$Dictionnary["QuestionnaireUsageCount"] ?? "utilisation(s)"; ?><br />
                <small><?=$Dictionnary["Modified"] ?? "Modifié"; ?> : <?=htmlspecialchars((string)$row["updated_at"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></small>
            </div>
            <div class="questionnaire-list-actions">
                <a class="questionnaire-primary" href="index.php?p=QuestionnaireMenu&amp;a=<?=(int)$row["id"]; ?>"><?=$Dictionnary["Open"] ?? "Ouvrir"; ?></a>
            </div>
        </article>
    <?php } ?>
    <?php if (!count($questionnaire_page_rows)) { ?>
        <div class="questionnaire-notice questionnaire-empty"><?=$Dictionnary["QuestionnaireNone"] ?? "Aucun questionnaire n’a encore été créé."; ?></div>
    <?php } ?>
</div>
