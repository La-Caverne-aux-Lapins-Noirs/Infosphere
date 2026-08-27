<br />
<?php
$js = "
silent_submitf(this, {
  tofill: 'support".$support["id"]."'
});
  ";
if (isset($asset) && $asset != NULL)
{
    $asset_id = "/asset/".$asset["id"];
    $asset_codename = $asset["codename"];
    $prefil = $asset;
}
else
{
    $asset_id = "";
    $asset_codename = "";
    $prefil = [];
}
$asset_preaccess_quizzes = isset($asset) && is_array($asset)
    ? support_asset_quiz_entrypoint_quizzes($asset)
    : [];
$asset_preaccess_ids = array_map(function($quiz) { return ((int)$quiz["id"]); }, $asset_preaccess_quizzes);
$asset_available_quizzes = questionnaire_list();
?>
<form
    method="post"
    action="/api/support_category/<?=$support["id_support_category"]; ?>/support/<?=$support["id"]; ?><?=$asset_id; ?>"
    enctype="multipart/form-data"
    data-direct-file-upload="1"
    class="support_add_formular"
    onsubmit="return <?=$js; ?>,"
>
    <?php if ($asset_id != "") { ?>
	<h3><?=$Dictionnary["EditAsset"]; ?></h3>
    <?php } else { ?>
	<h3><?=$Dictionnary["AddAsset"]; ?></h3>
    <?php } ?>
    <?php
    forge_language_formular(
	["name" => "text", "content" => "file"],
	$prefil,
	"_300pxw language_entry"
    );
    ?>
    <div class="_300pxw language_entry support_asset_preaccess_form" style="vertical-align: top;">
        <label for="asset_preaccess_quizzes_<?=isset($asset["id"]) ? (int)$asset["id"] : 0; ?>">
            <?=$Dictionnary["QuizSupportAssetPreaccessAdminTitle"] ?? "Pré-accès de la ressource"; ?>
        </label><br />
        <select
            id="asset_preaccess_quizzes_<?=isset($asset["id"]) ? (int)$asset["id"] : 0; ?>"
            name="preaccess_quizzes[]"
            multiple
            size="5"
            class="_300pxw"
            title="<?=$Dictionnary["QuizSupportAssetPreaccessAdminHelp"] ?? "Tous les questionnaires sélectionnés devront être réussis avant l'ouverture de cette ressource."; ?>"
        >
            <?php foreach ($asset_available_quizzes as $available_quiz) {
                $loaded = questionnaire_load_model($available_quiz);
                $label = $loaded["ok"] ? $loaded["model"]["name"] : $available_quiz["codename"];
            ?>
                <option
                    value="<?=(int)$available_quiz["id"]; ?>"
                    <?=in_array((int)$available_quiz["id"], $asset_preaccess_ids, true) ? "selected" : ""; ?>
                ><?=htmlspecialchars($label." — ".($available_quiz["school_codename"] ?? "")."/".$available_quiz["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></option>
            <?php } ?>
        </select><br />
        <small><?=$Dictionnary["QuizSupportAssetPreaccessAdminHelp"] ?? "Tous les questionnaires sélectionnés devront être réussis avant l'ouverture de cette ressource."; ?></small>
    </div>
    <div class="_300pxw language_entry" style="vertical-align: top;">
	<input
	    style="margin-top: 35px;"
	    type="text"
	    name="codename"
	    class="_300pxw"
	    <?php if ($asset_id != "" && isset($asset_codename)) { ?>
		value="<?=$asset_codename; ?>"
	    <?php } ?>
	    placeholder="<?=$Dictionnary["CodeName"]; ?>"
	/><br />
	<input
	    type="button"
	    onclick="<?=$js; ?>"
	    style="height: 40px; line-height: 40px;"
	    class="_300pxw"
	    value="+"
	/>
    </div>
</form>

