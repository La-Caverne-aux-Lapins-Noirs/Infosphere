<?php
$matter_preaccess_quizzes = activity_quiz_entrypoint_quizzes($matter->full_activity, "preaccess");
$matter_preaccess_own = activity_quiz_managed_quizzes($matter->full_activity, "preaccess");
$matter_preaccess_own_ids = array_map(function($quiz) { return ((int)$quiz["id"]); }, $matter_preaccess_own);
$matter_preaccess_all_ids = array_map(function($quiz) { return ((int)$quiz["id"]); }, $matter_preaccess_quizzes);
$matter_preaccess_catalog = questionnaire_list();
$matter_preaccess_path = activity_quiz_managed_entrypoint_path($matter->full_activity, "preaccess");
?>
<div class="matter_preaccess_admin">
    <strong>🔒 <?=$Dictionnary["QuizMatterPreaccessAdminTitle"] ?? "Pré-accès à l'inscription"; ?></strong>
    <small><?=$Dictionnary["QuizMatterPreaccessAdminHelp"] ?? "Sur une matière, preaccess.dab ne masque pas la matière : il bloque uniquement l'inscription jusqu'à réussite de tous les questionnaires référencés."; ?></small>
    <?php foreach ($matter_preaccess_quizzes as $pre_quiz) {
        $loaded = questionnaire_load_model($pre_quiz);
        $label = $loaded["ok"] ? $loaded["model"]["name"] : $pre_quiz["codename"];
        $own = in_array((int)$pre_quiz["id"], $matter_preaccess_own_ids, true);
    ?>
        <div class="matter_preaccess_admin_row">
            <span><?=htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?> <code><?=htmlspecialchars(($pre_quiz["school_codename"] ?? "")."/".$pre_quiz["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code></span>
            <?php if ($own) { ?>
                <form method="delete" action="/api/module/<?=(int)$matter->id; ?>/preaccess" onsubmit="return silent_submitf(this, {after_success: refresh});">
                    <input type="hidden" name="id_quiz" value="<?=(int)$pre_quiz["id"]; ?>" />
                    <input type="button" class="support_admin_button support_delete_button" value="×" onclick="silent_submitf(this, {after_success: refresh});" />
                </form>
            <?php } else { ?>
                <small><?=$Dictionnary["QuizPreaccessInherited"] ?? "hérité"; ?></small>
            <?php } ?>
        </div>
    <?php } ?>
    <form method="post" action="/api/module/<?=(int)$matter->id; ?>/preaccess" onsubmit="return silent_submitf(this, {after_success: refresh});">
        <select name="id_quiz" required>
            <option value=""><?=$Dictionnary["QuizMatterPreaccessAdd"] ?? "Ajouter un questionnaire…"; ?></option>
            <?php foreach ($matter_preaccess_catalog as $pre_quiz) {
                if (in_array((int)$pre_quiz["id"], $matter_preaccess_all_ids, true))
                    continue ;
                $loaded = questionnaire_load_model($pre_quiz);
                $label = $loaded["ok"] ? $loaded["model"]["name"] : $pre_quiz["codename"];
            ?>
                <option value="<?=(int)$pre_quiz["id"]; ?>"><?=htmlspecialchars($label." — ".($pre_quiz["school_codename"] ?? "")."/".$pre_quiz["codename"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></option>
            <?php } ?>
        </select>
        <input type="button" value="+" onclick="silent_submitf(this, {after_success: refresh});" />
    </form>
    <?php if ($matter_preaccess_path !== NULL) { ?>
        <small><code><?=htmlspecialchars(questionnaire_relative_project_path($matter_preaccess_path), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code></small>
    <?php } ?>
</div>
<style>
.matter_preaccess_admin { margin:0 0 18px 0; padding:10px; border:1px solid rgba(255,255,255,.18); border-radius:6px; display:grid; gap:7px; }
.matter_preaccess_admin_row { display:flex; gap:10px; justify-content:space-between; align-items:center; }
.matter_preaccess_admin form { margin:0; }
.matter_preaccess_admin > form { display:flex; gap:6px; }
.matter_preaccess_admin select { flex:1 1 auto; min-width:220px; }
</style>
