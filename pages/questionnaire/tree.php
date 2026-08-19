<?php

require_once (__DIR__."/../../tools/quiz_resources.php");

$questionnaire_tree_schools = questionnaire_manageable_schools();
$questionnaire_tree_school_id = (int)($questionnaire_tree_school_id ?? ($_GET["quiz_school"] ?? 0));
if ($questionnaire_tree_school_id <= 0 || quiz_resource_school($questionnaire_tree_school_id) == NULL)
    $questionnaire_tree_school_id = count($questionnaire_tree_schools) ? (int)$questionnaire_tree_schools[0]["id"] : 0;
$questionnaire_tree = $questionnaire_tree_school_id > 0 ? quiz_resource_tree($questionnaire_tree_school_id) : NULL;

function questionnaire_tree_h($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
}

function questionnaire_tree_render_nodes(array $nodes, $school_id)
{
    global $Dictionnary;
    foreach ($nodes as $node)
    {
        $path = (string)$node["relative"];
        if ($node["type"] == "directory")
        {
?>
<li class="questionnaire-tree-node questionnaire-tree-folder-node"
    draggable="true"
    data-questionnaire-tree-path="<?=questionnaire_tree_h($path); ?>"
    data-questionnaire-tree-type="directory">
    <details open>
        <summary class="questionnaire-tree-row questionnaire-tree-folder" data-questionnaire-tree-drop="<?=questionnaire_tree_h($path); ?>">
            <input class="questionnaire-tree-select" type="checkbox" value="<?=questionnaire_tree_h($path); ?>" onclick="event.stopPropagation();" />
            <span class="questionnaire-tree-expander" aria-hidden="true"></span>
            <span class="questionnaire-tree-icon" aria-hidden="true">📁</span>
            <span class="questionnaire-tree-name"><?=questionnaire_tree_h($node["name"]); ?></span>
            <code class="questionnaire-tree-path"><?=questionnaire_tree_h($path); ?></code>
            <span class="questionnaire-tree-actions">
                <button type="button" onclick="event.preventDefault(); event.stopPropagation(); return questionnaireTreeChooseFolder(<?=$school_id; ?>, <?=questionnaire_tree_h(json_encode($path)); ?>);"><?=$Dictionnary["QuizResourceImportFolder"] ?? "Importer un dossier"; ?></button>
                <button type="button" onclick="event.preventDefault(); event.stopPropagation(); return questionnaireTreePromptDirectory(<?=$school_id; ?>, <?=questionnaire_tree_h(json_encode($path)); ?>);">＋ <?=$Dictionnary["QuizResourceDirectory"] ?? "dossier"; ?></button>
                <button type="button" onclick="event.preventDefault(); event.stopPropagation(); return questionnaireTreePromptFile(<?=$school_id; ?>, <?=questionnaire_tree_h(json_encode($path)); ?>);">＋ Dabsic</button>
                <button type="button" onclick="event.preventDefault(); event.stopPropagation(); return questionnaireTreeDownload(<?=$school_id; ?>, [<?=questionnaire_tree_h(json_encode($path)); ?>]);"><?=$Dictionnary["Download"] ?? "Télécharger"; ?></button>
                <button type="button" class="danger" onclick="event.preventDefault(); event.stopPropagation(); return questionnaireTreeDelete(<?=$school_id; ?>, <?=questionnaire_tree_h(json_encode($path)); ?>, true);"><?=$Dictionnary["Delete"] ?? "Supprimer"; ?></button>
            </span>
        </summary>
        <ul class="questionnaire-tree-children<?=count($node["children"]) ? "" : " empty"; ?>">
            <?php questionnaire_tree_render_nodes($node["children"], $school_id); ?>
        </ul>
    </details>
</li>
<?php
            continue ;
        }
        $quiz = is_array($node["quiz"] ?? NULL) ? $node["quiz"] : NULL;
        $kind = $node["kind"] ?? "resource";
        $editable = $kind == "dabsic";
?>
<li class="questionnaire-tree-node questionnaire-tree-file-node"
    draggable="true"
    data-questionnaire-tree-path="<?=questionnaire_tree_h($path); ?>"
    data-questionnaire-tree-type="file">
    <div class="questionnaire-tree-row questionnaire-tree-file<?=$editable ? " questionnaire-tree-editable" : ""; ?>"
         <?php if ($editable) { ?>ondblclick="if (!event.target.closest('button,a,input')) return questionnaireTreeOpenDabsic(<?=$school_id; ?>, <?=questionnaire_tree_h(json_encode($path)); ?>);"<?php } ?>>
        <input class="questionnaire-tree-select" type="checkbox" value="<?=questionnaire_tree_h($path); ?>" onclick="event.stopPropagation();" />
        <span class="questionnaire-tree-expander" aria-hidden="true"></span>
        <span class="questionnaire-tree-icon" aria-hidden="true"><?=$kind == "dabsic" ? "📄" : ($kind == "image" ? "🖼️" : "📎"); ?></span>
        <span class="questionnaire-tree-name"><?=questionnaire_tree_h($node["name"]); ?></span>
        <span class="questionnaire-tree-kind">
            <?php if ($quiz != NULL) { ?>
                <strong><?=$Dictionnary["Questionnaire"] ?? "Questionnaire"; ?></strong><?=!empty($quiz["deleted"]) ? " · ".($Dictionnary["QuestionnaireArchives"] ?? "archivé") : ""; ?>
            <?php } else { ?>
                <?=$kind == "dabsic" ? "Dabsic" : ($Dictionnary["QuizResourceResource"] ?? "Ressource"); ?>
            <?php } ?>
        </span>
        <span class="questionnaire-tree-actions">
            <?php if ($quiz != NULL && empty($quiz["deleted"])) { ?>
                <a class="button" href="index.php?p=QuestionnaireMenu&amp;a=<?=(int)$quiz["id"]; ?>"><?=$Dictionnary["Open"] ?? "Ouvrir"; ?></a>
            <?php } ?>
            <?php if ($editable) { ?>
                <button type="button" onclick="return questionnaireTreeOpenDabsic(<?=$school_id; ?>, <?=questionnaire_tree_h(json_encode($path)); ?>);">Dabsic</button>
            <?php } ?>
            <button type="button" onclick="return questionnaireTreeDownload(<?=$school_id; ?>, [<?=questionnaire_tree_h(json_encode($path)); ?>]);"><?=$Dictionnary["Download"] ?? "Télécharger"; ?></button>
            <?php if ($quiz == NULL) { ?>
                <button type="button" class="danger" onclick="return questionnaireTreeDelete(<?=$school_id; ?>, <?=questionnaire_tree_h(json_encode($path)); ?>, false);"><?=$Dictionnary["Delete"] ?? "Supprimer"; ?></button>
            <?php } ?>
        </span>
    </div>
</li>
<?php
    }
}
?>
<div class="questionnaire-tree-panel" data-questionnaire-tree-school="<?=$questionnaire_tree_school_id; ?>">
    <div class="questionnaire-tree-toolbar">
        <label>
            <span><?=$Dictionnary["School"] ?? "École"; ?></span>
            <select onchange="questionnaireTreeSwitchSchool(this.value);">
                <?php foreach ($questionnaire_tree_schools as $school) { ?>
                    <option value="<?=(int)$school["id"]; ?>"<?=$questionnaire_tree_school_id == (int)$school["id"] ? " selected" : ""; ?>><?=questionnaire_tree_h($school["name"] ?? $school["codename"]); ?></option>
                <?php } ?>
            </select>
        </label>
        <button type="button" onclick="questionnaireTreeExpandAll(true);"><?=$Dictionnary["QuizResourceExpandAll"] ?? "Tout dérouler"; ?></button>
        <button type="button" onclick="questionnaireTreeExpandAll(false);"><?=$Dictionnary["QuizResourceCollapseAll"] ?? "Tout replier"; ?></button>
        <?php if ($questionnaire_tree_school_id > 0) { ?>
            <button type="button" onclick="return questionnaireTreePromptDirectory(<?=$questionnaire_tree_school_id; ?>, '');">＋ <?=$Dictionnary["QuizResourceRootDirectory"] ?? "dossier racine"; ?></button>
            <button type="button" onclick="return questionnaireTreePromptFile(<?=$questionnaire_tree_school_id; ?>, '');">＋ Dabsic</button>
            <button type="button" onclick="return questionnaireTreeChooseFiles(<?=$questionnaire_tree_school_id; ?>, '');"><?=$Dictionnary["QuizResourceImportFiles"] ?? "Importer des fichiers"; ?></button>
            <button type="button" onclick="return questionnaireTreeChooseFolder(<?=$questionnaire_tree_school_id; ?>, '');"><?=$Dictionnary["QuizResourceImportFolder"] ?? "Importer un dossier"; ?></button>
            <button type="button" onclick="return questionnaireTreeDownloadSelection(<?=$questionnaire_tree_school_id; ?>);">⇩ <?=$Dictionnary["QuizResourceDownloadSelection"] ?? "Télécharger la sélection"; ?></button>
        <?php } ?>
        <small><?=$Dictionnary["QuizResourceTreeHelp"] ?? "Double-cliquez un .dab pour l’éditer. Les dossiers quiz/ et rubrics/ sont seulement des conventions initiales."; ?></small>
    </div>

    <input id="questionnaire-tree-files-input" type="file" multiple hidden onchange="questionnaireTreeUploadFilesInput(this);" />
    <input id="questionnaire-tree-folder-input" type="file" webkitdirectory multiple hidden onchange="questionnaireTreeUploadFolderInput(this);" />

    <?php if ($questionnaire_tree != NULL) { ?>
        <div class="questionnaire-tree-root" data-questionnaire-tree-drop="">
            <div class="questionnaire-tree-root-label">
                <span class="questionnaire-tree-icon" aria-hidden="true">🗂️</span>
                <strong>dres/quiz/<?=questionnaire_tree_h($questionnaire_tree["root"]["school"]["codename"]); ?></strong>
                <small><?=$Dictionnary["QuizResourceTreeRootHelp"] ?? "Catalogue de ressources de formulaires et questionnaires"; ?></small>
            </div>
            <ul class="questionnaire-tree questionnaire-tree-root-list">
                <?php questionnaire_tree_render_nodes($questionnaire_tree["nodes"], $questionnaire_tree_school_id); ?>
            </ul>
        </div>
    <?php } else { ?>
        <div class="questionnaire-notice questionnaire-empty"><?=$Dictionnary["QuestionnaireInvalidSchool"] ?? "Établissement invalide."; ?></div>
    <?php } ?>
</div>
