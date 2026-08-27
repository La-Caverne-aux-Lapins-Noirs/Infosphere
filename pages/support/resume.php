<?php require_once (__DIR__."/menu_helpers.php"); ?>
<?php if (can_edit_supports()) { ?>
    <?php
    $js = "silent_submitf(this, ".
	  "{".
	  "tofill: 'resume_class_list".
	  ($selcat ? $category["id"] : "").
	  "'}".
	  ") ; setTimeout(silent_submitf, 500, document.getElementById('fetch_menu_form'), {tofill: 'main_menu'});".
	  ";";
    ?>
    <form
	method="post"
	action="/api/support_category<?php isset($category["id"]) ? "/".$category["id"] : ""; ?>"
	class="support_add_formular"
	onsubmit="<?=$js; ?>"
    >
	<?php if ($selcat) { ?>
	    <h2><?=$Dictionnary["AddAClass"]; ?></h2>
	<?php } else { ?>
	    <h2><?=$Dictionnary["AddACategory"]; ?></h2>
	<?php } ?>
	<?php
	forge_language_formular(
	    ["name" => "text", "description" => "textarea"],
	    [],
	    "_300pxw language_entry"
	);
	?>
	<div class="_300pxw language_entry" style="vertical-align: top;">
	    <input
		style="margin-top: 35px;"
		type="text"
		name="codename"
		class="_300pxw"
		placeholder="<?=$Dictionnary["CodeName"]; ?>"
	    /><br />
	    <select
		name="id_support_category"
		class="_300pxw"
		style="height: 40px;"
	    >
		<?php if ($selcat) { ?>
		    <option value="<?=$category["id"]; ?>">
			<?=$Dictionnary["Category"]; ?>
			<?=$category["name"]; ?>
			(<?=$category["codename"]; ?>)
		    </option>
		<?php } else { ?>
		    <option value="">
			<?=$Dictionnary["NewSupportCategory"]; ?>
		    </option>
		    <?php foreach ($categories as $cat) { ?>
			<option value="<?=$cat["id"]; ?>">
			    <?=$Dictionnary["Category"]; ?>
			    <?=$cat["name"]; ?>
			    (<?=$cat["codename"]; ?>)
			</option>
		    <?php } ?>
		<?php } ?>
	    </select>
	    <br />
            <?php if ($selcat) { ?>
                <label for="support_preaccess_quizzes_new_<?=$category["id"]; ?>">
                    <?=$Dictionnary["QuizSupportPreaccessAdminTitle"] ?? "Pré-accès du chapitre"; ?>
                </label><br />
                <select
                    id="support_preaccess_quizzes_new_<?=$category["id"]; ?>"
                    name="preaccess_quizzes[]"
                    multiple
                    size="5"
                    class="_300pxw"
                    title="<?=$Dictionnary["QuizSupportPreaccessAdminHelp"] ?? "Tous les questionnaires sélectionnés devront être réussis avant l'accès au chapitre."; ?>"
                >
                    <?php foreach (questionnaire_list() as $pre_quiz) {
                        $pre_loaded = questionnaire_load_model($pre_quiz);
                        $pre_label = $pre_loaded["ok"] ? $pre_loaded["model"]["name"] : $pre_quiz["codename"];
                    ?>
                        <option value="<?=(int)$pre_quiz["id"]; ?>"><?=htmlspecialchars($pre_label." — ".($pre_quiz["school_codename"] ?? "")."/".$pre_quiz["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></option>
                    <?php } ?>
                </select>
                <small style="display:block; margin:4px 0 8px 0;"><?=$Dictionnary["QuizSupportPreaccessCreateHelp"] ?? "Optionnel : tous les questionnaires sélectionnés devront être réussis avant l'accès au chapitre. Laisser la sélection vide ne crée aucune directive."; ?></small>
            <?php } ?>
	    <input
		type="button"
		onclick="<?=$js; ?>"
		style="height: 60px;"
		class="_300pxw"
		value="+"
	    />
	</div>
    </form>
<?php } ?>

<?php if (can_edit_supports() && isset($selcat) && $selcat) { ?>
    <?php support_pedagogical_render_category_panel($category); ?>
<?php } ?>

<div id="resume_class_list<?=$selcat ? $category["id"] : ""; ?>">
    <?php foreach ($categories as $category) { ?>
	<?php if ($category["selected"] == false) continue ; ?>
	<?php require ("resume_class.php"); ?>
    <?php } ?>
</div>
