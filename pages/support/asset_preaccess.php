<?php
$asset_preaccess_status = $asset_preaccess_status ?? support_asset_preaccess_status($asset, support_progress_current_user_id());
?>
<div class="support_preaccess_lock support_asset_preaccess_lock">
    <h3><?=$Dictionnary["QuizSupportAssetPreaccessLockedTitle"] ?? "Ressource verrouillée"; ?></h3>
    <p><?=$Dictionnary["QuizSupportAssetPreaccessLockedHelp"] ?? "Un questionnaire préalable doit être réussi avant d'accéder à cette ressource."; ?></p>
    <div class="support_preaccess_list">
        <?php foreach ($asset_preaccess_status["quizzes"] as $qstatus) {
            $quiz = $qstatus["quiz"];
            $loaded = questionnaire_load_model($quiz);
            $name = $loaded["ok"] ? $loaded["model"]["name"] : $quiz["codename"];
        ?>
            <div class="support_preaccess_item <?=$qstatus["passed"] ? "passed" : "pending"; ?>">
                <span><?=$qstatus["passed"] ? "✓" : "○"; ?> <?=htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></span>
                <?php if (!$qstatus["passed"] && is_array($qstatus["attempt"])) { ?>
                    <a class="button_link" href="index.php?p=QuizAttemptMenu&amp;a=<?=(int)$qstatus["attempt"]["id"]; ?>"><?=$Dictionnary["QuizAttemptResume"] ?? "Reprendre"; ?></a>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php if ($asset_preaccess_status["next"] !== NULL && !is_array($asset_preaccess_status["next"]["attempt"])) { ?>
        <form method="post" action="/api/support_category/<?=(int)$support["id_support_category"]; ?>/asset_preaccess_attempt" onsubmit="return silent_submitf(this, {after_success: function(result, msg, content) { if (content) window.location.href = content; }});">
            <input type="hidden" name="id_asset" value="<?=(int)$asset["id"]; ?>" />
            <button type="submit"><?=$Dictionnary["QuizPreaccessStart"] ?? "Commencer le questionnaire préalable"; ?></button>
        </form>
    <?php } ?>
</div>
<style>
.support_asset_preaccess_lock { max-width:760px; margin:35px auto; padding:20px; border:1px solid rgba(255,255,255,.25); border-radius:8px; box-sizing:border-box; }
.support_asset_preaccess_lock .support_preaccess_list { margin:15px 0; display:grid; gap:8px; }
.support_asset_preaccess_lock .support_preaccess_item { display:flex; justify-content:space-between; gap:15px; align-items:center; padding:8px 10px; border-radius:5px; background:rgba(255,255,255,.06); }
.support_asset_preaccess_lock .support_preaccess_item.passed { opacity:.72; }
</style>
