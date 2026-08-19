<?php

require_once ("tools/questionnaire.php");
require_once ("tools/quiz_resources.php");

if (!questionnaire_can_access_page())
{
    http_response_code(404);
    die();
}

function questionnaire_page_message($error, $details = "")
{
    global $Dictionnary;
    $fallback = [
        "QuestionnaireForbidden" => "Vous n’avez pas les droits nécessaires sur cet établissement.",
        "QuestionnaireInvalidCodename" => "Le nom de code du questionnaire est invalide.",
        "QuestionnaireInvalidSchool" => "L’établissement est invalide.",
        "QuestionnaireCannotCreate" => "Impossible de créer le questionnaire.",
        "QuestionnaireAlreadyExists" => "Un questionnaire avec ce nom de code existe déjà pour cet établissement.",
        "QuestionnaireNotFound" => "Questionnaire introuvable.",
        "QuestionnaireCannotDelete" => "Impossible de supprimer le questionnaire.",
        "QuestionnaireCannotRestore" => "Impossible de restaurer le questionnaire.",
        "QuestionnaireInvalidDabsic" => "La définition Dabsic du questionnaire est invalide.",
        "QuestionnaireMissingDefinition" => "Le scope [Questionnaire] est absent.",
        "QuestionnaireAdvancedSource" => "La définition contient du Dabsic avancé : utilisez l’éditeur source pour éviter toute perte.",
        "QuestionnaireCodenameMismatch" => "Le Codename Dabsic ne correspond plus à l’identité du questionnaire.",
        "DabsicEditorConflict" => "Le fichier a changé depuis son ouverture. Rechargez la page avant d’enregistrer.",
        "DabsicEditorSyntaxError" => "Le Dabsic généré est invalide.",
        "DabsicEditorMergeconfUnavailable" => "mergeconf n’est pas disponible pour valider le Dabsic.",
    ];
    $message = $Dictionnary[$error] ?? ($fallback[$error] ?? (string)$error);
    if (trim((string)$details) != "")
        $message .= " — ".trim((string)$details);
    return ($message);
}

$questionnaire_page_notice = "";
$questionnaire_page_error = "";
$questionnaire_page_storage_warnings = questionnaire_migrate_legacy_storage();
$questionnaire_page_selected = isset($_GET["a"]) ? (int)$_GET["a"] : 0;

if (isset($_POST["action"]))
{
    $action = (string)$_POST["action"];
    $result = NULL;
    if ($action == "create")
    {
        $result = questionnaire_create(
            (int)($_POST["id_school"] ?? 0),
            $_POST["codename"] ?? "",
            $_POST["name"] ?? "",
            $_POST["description"] ?? "",
            $_POST["directory"] ?? "quiz"
        );
        if ($result["ok"])
        {
            $questionnaire_page_selected = (int)$result["id"];
            $questionnaire_page_notice = $Dictionnary["QuestionnaireCreated"] ?? "Questionnaire créé.";
        }
    }
    else if ($action == "save_visual")
    {
        $questionnaire_page_selected = (int)($_POST["questionnaire_id"] ?? 0);
        $model = json_decode((string)($_POST["model"] ?? ""), true);
        if (!is_array($model))
            $result = ["ok" => false, "error" => "QuestionnaireInvalidData"];
        else
            $result = questionnaire_save_visual(
                $questionnaire_page_selected,
                $model,
                $_POST["expected_hash"] ?? ""
            );
        if ($result["ok"])
            $questionnaire_page_notice = $Dictionnary["QuestionnaireSaved"] ?? "Questionnaire enregistré.";
    }
    else if ($action == "duplicate")
    {
        $result = questionnaire_duplicate(
            (int)($_POST["questionnaire_id"] ?? 0),
            $_POST["codename"] ?? "",
            $_POST["name"] ?? ""
        );
        if ($result["ok"])
        {
            $questionnaire_page_selected = (int)$result["id"];
            $questionnaire_page_notice = $Dictionnary["QuestionnaireDuplicated"] ?? "Questionnaire dupliqué.";
        }
    }
    else if ($action == "delete")
    {
        $id = (int)($_POST["questionnaire_id"] ?? 0);
        $result = questionnaire_delete($id);
        if ($result["ok"])
        {
            $questionnaire_page_selected = 0;
            $questionnaire_page_notice = $Dictionnary["QuestionnaireDeleted"] ?? "Questionnaire archivé.";
        }
    }
    else if ($action == "restore")
    {
        $result = questionnaire_restore((int)($_POST["questionnaire_id"] ?? 0));
        if ($result["ok"])
        {
            $questionnaire_page_selected = (int)$result["id"];
            $questionnaire_page_notice = $Dictionnary["QuestionnaireRestored"] ?? "Questionnaire restauré.";
        }
    }
    if (is_array($result) && empty($result["ok"]))
        $questionnaire_page_error = questionnaire_page_message($result["error"] ?? "QuestionnaireError", $result["details"] ?? "");
}

$questionnaire_page_schools = questionnaire_manageable_schools();
$questionnaire_page_row = $questionnaire_page_selected > 0 ? questionnaire_get($questionnaire_page_selected) : NULL;
if ($questionnaire_page_row != NULL && !questionnaire_can_manage($questionnaire_page_row))
    $questionnaire_page_row = NULL;
$questionnaire_page_loaded = NULL;
$questionnaire_page_model = NULL;
if ($questionnaire_page_row != NULL)
{
    $questionnaire_page_loaded = questionnaire_load_model($questionnaire_page_row);
    if ($questionnaire_page_loaded["ok"])
        $questionnaire_page_model = $questionnaire_page_loaded["model"];
    else
        $questionnaire_page_error = questionnaire_page_message(
            $questionnaire_page_loaded["error"] ?? "QuestionnaireError",
            $questionnaire_page_loaded["details"] ?? ""
        );
}

?>
<style><?php require (__DIR__."/style.css"); ?></style>

<div class="questionnaire-admin">
    <div class="questionnaire-heading">
        <div>
            <h2><?=$Dictionnary["Questionnaire"] ?? "Questionnaires"; ?></h2>
            <p><?=$Dictionnary["QuestionnairePageHelp"] ?? "Définitions Dabsic de questionnaires, QCM, enquêtes et barèmes. Leur exploitation sera rattachée séparément aux activités, supports et autres objets."; ?></p>
        </div>
        <?php if ($questionnaire_page_row != NULL) { ?>
            <a class="questionnaire-secondary questionnaire-back" href="index.php?p=QuestionnaireMenu">← <?=$Dictionnary["QuestionnaireAll"] ?? "Tous les questionnaires"; ?></a>
        <?php } ?>
    </div>

    <?php if ($questionnaire_page_notice != "") { ?>
        <div class="questionnaire-notice questionnaire-notice-success"><?=htmlspecialchars($questionnaire_page_notice, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></div>
    <?php } ?>
    <?php if ($questionnaire_page_error != "") { ?>
        <div class="questionnaire-notice questionnaire-notice-error"><?=nl2br(htmlspecialchars($questionnaire_page_error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")); ?></div>
    <?php } ?>

    <?php if (count($questionnaire_page_storage_warnings)) { ?>
        <div class="questionnaire-notice questionnaire-notice-error">
            <?=htmlspecialchars($Dictionnary["QuizResourceMigrationWarning"] ?? "Certaines anciennes ressources n’ont pas pu être déplacées vers dres/quiz.", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>
            <code><?=htmlspecialchars(implode(", ", $questionnaire_page_storage_warnings), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
        </div>
    <?php } ?>

    <?php if ($questionnaire_page_row == NULL) { ?>
        <section class="questionnaire-create">
            <h3><?=$Dictionnary["QuestionnaireCreate"] ?? "Créer un questionnaire"; ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="create" />
                <div class="questionnaire-create-grid">
                    <label class="questionnaire-field">
                        <span><?=$Dictionnary["School"] ?? "École"; ?></span>
                        <select name="id_school" required>
                            <?php foreach ($questionnaire_page_schools as $school) { ?>
                                <option value="<?=(int)$school["id"]; ?>"><?=htmlspecialchars($school["name"] ?? $school["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <label class="questionnaire-field">
                        <span><?=$Dictionnary["QuizResourceDirectory"] ?? "Dossier"; ?></span>
                        <input type="text" name="directory" value="quiz" placeholder="quiz" />
                        <small><?=$Dictionnary["QuizResourceDirectoryHelp"] ?? "Chemin dans dres/quiz/&lt;école&gt;. rubrics est prévu pour les barèmes."; ?></small>
                    </label>
                    <label class="questionnaire-field">
                        <span><?=$Dictionnary["CodeName"] ?? "Nom de code"; ?></span>
                        <input type="text" name="codename" pattern="[A-Za-z0-9_-]+" placeholder="pointeurs_base" required />
                    </label>
                    <label class="questionnaire-field">
                        <span><?=$Dictionnary["Name"] ?? "Nom"; ?></span>
                        <input type="text" name="name" placeholder="Pré-requis : pointeurs" required />
                    </label>
                    <label class="questionnaire-field questionnaire-field-wide">
                        <span><?=$Dictionnary["Description"] ?? "Description"; ?></span>
                        <textarea name="description" rows="2"></textarea>
                    </label>
                    <div class="questionnaire-field">
                        <span>&nbsp;</span>
                        <button type="submit" class="questionnaire-primary">+ <?=$Dictionnary["QuestionnaireCreateAction"] ?? "Créer et ouvrir"; ?></button>
                    </div>
                </div>
            </form>
        </section>

        <?php
        $questionnaire_page_catalog_usages = questionnaire_activity_usages();
        $questionnaire_catalog_panels = [
            $Dictionnary["QuestionnaireCatalog"] ?? "Catalogue" => __DIR__."/catalog.php",
            $Dictionnary["QuizResourceTree"] ?? "Arborescence" => __DIR__."/tree.php",
            $Dictionnary["QuestionnaireUsages"] ?? "Utilisations" => __DIR__."/usages.php",
            $Dictionnary["QuestionnaireArchives"] ?? "Archives" => __DIR__."/archives.php",
        ];
        tabpanel(
            $questionnaire_catalog_panels,
            "questionnaire-catalog-tabs",
            $Dictionnary["QuestionnaireCatalog"] ?? "Catalogue"
        );
        ?>
    <?php } else if ($questionnaire_page_model != NULL) { ?>
        <?php $questionnaire_page_counts = questionnaire_model_counts($questionnaire_page_model); ?>
        <section class="questionnaire-detail-heading">
            <h2><?=htmlspecialchars($questionnaire_page_model["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></h2>
            <div class="questionnaire-detail-meta">
                <span><strong><?=$Dictionnary["School"] ?? "École"; ?> :</strong> <?=htmlspecialchars($questionnaire_page_row["school_codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span>
                <span><strong>Codename :</strong> <code><?=htmlspecialchars($questionnaire_page_row["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code></span>
                <span><strong><?=$Dictionnary["QuizResourcePath"] ?? "Ressource"; ?> :</strong> <code><?=htmlspecialchars((string)$questionnaire_page_row["reference"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code></span>
                <span><strong><?=$Dictionnary["QuestionnaireQuestions"] ?? "Questions"; ?> :</strong> <?=$questionnaire_page_counts["questions"]; ?></span>
                <span><strong><?=$Dictionnary["QuestionnaireGroups"] ?? "Groupes"; ?> :</strong> <?=$questionnaire_page_counts["groups"]; ?></span>
            </div>
        </section>

        <?php
        $questionnaire_panels = [
            $Dictionnary["QuestionnaireDefinition"] ?? "Définition" => __DIR__."/definition.php",
            $Dictionnary["QuestionnaireUsages"] ?? "Utilisations" => __DIR__."/usage.php",
            $Dictionnary["QuestionnaireSource"] ?? "Source Dabsic" => __DIR__."/source.php",
            $Dictionnary["QuestionnairePreview"] ?? "Aperçu" => __DIR__."/preview.php",
        ];
        tabpanel(
            $questionnaire_panels,
            "questionnaire-".(int)$questionnaire_page_row["id"]."-tabs",
            $Dictionnary["QuestionnaireDefinition"] ?? "Définition"
        );
        ?>

        <section class="questionnaire-create questionnaire-maintenance">
            <h3><?=$Dictionnary["QuestionnaireMaintenance"] ?? "Gestion"; ?></h3>
            <div class="questionnaire-create-grid">
                <?php if (empty($questionnaire_page_model["advanced"])) { ?>
                    <form method="post" class="questionnaire-field questionnaire-field-wide">
                        <input type="hidden" name="action" value="duplicate" />
                        <input type="hidden" name="questionnaire_id" value="<?=(int)$questionnaire_page_row["id"]; ?>" />
                        <span><?=$Dictionnary["QuestionnaireDuplicate"] ?? "Dupliquer"; ?></span>
                        <div class="questionnaire-list-actions">
                            <input type="text" name="codename" pattern="[A-Za-z0-9_-]+" value="<?=htmlspecialchars($questionnaire_page_row["codename"]."_copy", ENT_QUOTES); ?>" required />
                            <input type="text" name="name" value="<?=htmlspecialchars($questionnaire_page_model["name"]." (copie)", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>" required />
                            <button type="submit" class="questionnaire-secondary"><?=$Dictionnary["QuestionnaireDuplicateAction"] ?? "Créer la copie"; ?></button>
                        </div>
                    </form>
                <?php } ?>
                <form method="post" class="questionnaire-field" onsubmit="return confirm('<?=addslashes($Dictionnary["QuestionnaireDeleteConfirm"] ?? "Archiver ce questionnaire ?"); ?>');">
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="questionnaire_id" value="<?=(int)$questionnaire_page_row["id"]; ?>" />
                    <span><?=$Dictionnary["QuestionnaireArchive"] ?? "Archivage"; ?></span>
                    <button type="submit" class="questionnaire-danger-button"><?=$Dictionnary["QuestionnaireDelete"] ?? "Archiver le questionnaire"; ?></button>
                </form>
            </div>
        </section>
    <?php } ?>
</div>

<?php
$questionnaire_builder_i18n = [];
foreach (["QuestionnaireBuilderMoveUp", "QuestionnaireBuilderMoveDown", "QuestionnaireBuilderDelete", "QuestionnaireBuilderDeleteConfirm", "QuestionnaireBuilderCorrect", "QuestionnaireBuilderChoice", "QuestionnaireBuilderQuestion", "QuestionnaireBuilderQuestionKind", "QuestionnaireBuilderGroupKind", "QuestionnaireBuilderDabsicId", "QuestionnaireBuilderLabel", "QuestionnaireBuilderType", "QuestionnaireBuilderRequired", "QuestionnaireBuilderPoints", "QuestionnaireBuilderErrorPolicy", "QuestionnaireBuilderPenalty", "QuestionnaireBuilderQuestionMedals", "QuestionnaireBuilderOneMedal", "QuestionnaireBuilderChoices", "QuestionnaireBuilderChoicesHelp", "QuestionnaireBuilderAddChoice", "QuestionnaireBuilderExpected", "QuestionnaireBuilderExpectedHelp", "QuestionnaireBuilderNewGroup", "QuestionnaireBuilderGroup", "QuestionnaireBuilderGroupName", "QuestionnaireBuilderGroupThreshold", "QuestionnaireBuilderGroupMedals", "QuestionnaireBuilderGroupMedalsHelp", "QuestionnaireBuilderAddQuestion", "QuestionnaireBuilderAddGroup", "QuestionnaireBuilderNameRequired", "QuestionnaireBuilderTypeText", "QuestionnaireBuilderTypeTextarea", "QuestionnaireBuilderTypeRadio", "QuestionnaireBuilderTypeCheckbox", "QuestionnaireBuilderPolicyExact", "QuestionnaireBuilderPolicyPenalty", "QuestionnaireBuilderNewQuestion"] as $key)
    $questionnaire_builder_i18n[$key] = $Dictionnary[$key] ?? $key;
?>
<?php
$questionnaire_tree_i18n = [
    "directoryPrompt" => $Dictionnary["QuizResourceDirectoryPrompt"] ?? "Nom du nouveau dossier :",
    "filePrompt" => $Dictionnary["QuizResourceFilePrompt"] ?? "Nom du nouveau fichier Dabsic :",
    "error" => $Dictionnary["QuizResourceError"] ?? "Échec de l’opération.",
    "uploadError" => $Dictionnary["QuizResourceUploadError"] ?? "Échec de l’import.",
    "nothingImportable" => $Dictionnary["QuizResourceNothingImportable"] ?? "Aucun fichier importable n’a été trouvé.",
    "nothingImportableFolder" => $Dictionnary["QuizResourceNothingImportableFolder"] ?? "Le dossier ne contient aucun fichier importable.",
    "selectSomething" => $Dictionnary["QuizResourceSelectSomething"] ?? "Sélectionnez au moins un fichier ou un dossier.",
    "deleteDirectory" => $Dictionnary["QuizResourceDeleteDirectoryConfirm"] ?? "Supprimer ce dossier et toutes ses ressources ?",
    "deleteFile" => $Dictionnary["QuizResourceDeleteFileConfirm"] ?? "Supprimer ce fichier ?",
];
?>
<script>window.QuestionnaireBuilderI18n = <?=json_encode($questionnaire_builder_i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
<script>window.QuestionnaireTreeI18n = <?=json_encode($questionnaire_tree_i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
<script><?php require (__DIR__."/script.js"); ?></script>
<?php if ($questionnaire_page_selected > 0) { ?>
<script>
if (window.history && window.history.replaceState)
    window.history.replaceState({}, "", "index.php?p=QuestionnaireMenu&a=<?=(int)$questionnaire_page_selected; ?>");
</script>
<?php } ?>
