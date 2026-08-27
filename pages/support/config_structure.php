<?php
$structure_type = isset($structure_type) ? (string)$structure_type : "";
$is_support = ($structure_type === "support");
$entity = $is_support ? ($support ?? NULL) : ($category ?? NULL);
if (!is_array($entity))
    return ;

$category_id = $is_support ? (int)$entity["id_support_category"] : (int)$entity["id"];
$entity_id = (int)$entity["id"];
$action = $is_support
    ? "/api/support_category/".$category_id."/support/".$entity_id
    : "/api/support_category/".$entity_id;
$panel_page = $is_support ? "support" : "category";
$title = $is_support
    ? ($Dictionnary["EditAClass"] ?? "Modifier le chapitre")
    : ($Dictionnary["EditACategory"] ?? "Modifier la section");
$preaccess_quizzes = $is_support ? support_quiz_entrypoint_quizzes($entity) : [];
$preaccess_ids = array_map(function($quiz) { return ((int)$quiz["id"]); }, $preaccess_quizzes);
$available_quizzes = $is_support ? questionnaire_list() : [];
$js = "silent_submitf(this, {after_success: function() { support_after_structure_edit('".$panel_page."', ".$entity_id."); }});";
?>
<br />
<form
    method="put"
    action="<?=$action; ?>"
    class="support_add_formular support_structure_edit_form"
    onsubmit="return <?=$js; ?>"
>
    <input type="hidden" name="edit_definition" value="1" />
    <h3><?=$title; ?></h3>
    <?php
    forge_language_formular(
        ["name" => "text", "description" => "textarea"],
        $entity,
        "_300pxw language_entry"
    );
    ?>
    <?php if ($is_support) { ?>
        <div class="_300pxw language_entry support_asset_preaccess_form" style="vertical-align: top;">
            <label for="support_preaccess_quizzes_<?=$entity_id; ?>">
                <?=$Dictionnary["QuizSupportPreaccessAdminTitle"] ?? "Pré-accès du chapitre"; ?>
            </label><br />
            <select
                id="support_preaccess_quizzes_<?=$entity_id; ?>"
                name="preaccess_quizzes[]"
                multiple
                size="5"
                class="_300pxw"
                title="<?=$Dictionnary["QuizSupportPreaccessAdminHelp"] ?? "Tous les questionnaires sélectionnés devront être réussis avant l'accès au chapitre."; ?>"
            >
                <?php foreach ($available_quizzes as $available_quiz) {
                    $loaded = questionnaire_load_model($available_quiz);
                    $label = $loaded["ok"] ? $loaded["model"]["name"] : $available_quiz["codename"];
                ?>
                    <option
                        value="<?=(int)$available_quiz["id"]; ?>"
                        <?=in_array((int)$available_quiz["id"], $preaccess_ids, true) ? "selected" : ""; ?>
                    ><?=htmlspecialchars($label." — ".($available_quiz["school_codename"] ?? "")."/".$available_quiz["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></option>
                <?php } ?>
            </select><br />
            <small><?=$Dictionnary["QuizSupportPreaccessAdminHelp"] ?? "Tous les questionnaires sélectionnés devront être réussis avant l'accès au chapitre. Laisser la sélection vide supprime la directive de pré-accès."; ?></small>
        </div>
    <?php } ?>
    <div class="_300pxw language_entry" style="vertical-align: top;">
        <label for="support_structure_codename_<?=$structure_type; ?>_<?=$entity_id; ?>"><?=$Dictionnary["CodeName"]; ?></label><br />
        <input
            id="support_structure_codename_<?=$structure_type; ?>_<?=$entity_id; ?>"
            type="text"
            name="codename"
            class="_300pxw"
            value="<?=htmlspecialchars((string)$entity["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
            readonly
            title="Le nom de code est conservé afin de ne pas déplacer implicitement les ressources déjà publiées."
        /><br />
        <small>Le nom de code reste inchangé lors de cette modification.</small><br />
        <input
            type="button"
            onclick="<?=$js; ?>"
            style="height: 40px; line-height: 40px; margin-top: 8px;"
            class="_300pxw"
            value="✓"
        />
    </div>
</form>
