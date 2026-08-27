<?php
$support_preaccess_status = NULL;
$support_preaccess_user_id = support_progress_current_user_id();
if (!can_edit_supports() && $support_preaccess_user_id > 0)
    $support_preaccess_status = support_preaccess_status($support, $support_preaccess_user_id);
if (is_array($support_preaccess_status)
    && !empty($support_preaccess_status["configured"])
    && empty($support_preaccess_status["passed"]))
{
?>
<div class="support_preaccess_lock">
    <h3><?=$Dictionnary["QuizSupportPreaccessLockedTitle"] ?? "Support verrouillé"; ?></h3>
    <p><?=$Dictionnary["QuizSupportPreaccessLockedHelp"] ?? "Un questionnaire préalable doit être réussi avant d'accéder au contenu de ce support."; ?></p>
    <div class="support_preaccess_list">
        <?php foreach ($support_preaccess_status["quizzes"] as $qstatus) {
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
    <?php if ($support_preaccess_status["next"] !== NULL && !is_array($support_preaccess_status["next"]["attempt"])) { ?>
        <form method="post" action="/api/support_category/<?=(int)$support["id_support_category"]; ?>/preaccess_attempt" onsubmit="return silent_submitf(this, {after_success: function(result, msg, content) { if (content) window.location.href = content; }});">
            <input type="hidden" name="id_support" value="<?=(int)$support["id"]; ?>" />
            <button type="submit"><?=$Dictionnary["QuizPreaccessStart"] ?? "Commencer le questionnaire préalable"; ?></button>
        </form>
    <?php } ?>
</div>
<style>
.support_preaccess_lock { max-width: 760px; margin: 35px auto; padding: 20px; border: 1px solid rgba(255,255,255,.25); border-radius: 8px; }
.support_preaccess_list { margin: 15px 0; display: grid; gap: 8px; }
.support_preaccess_item { display: flex; justify-content: space-between; gap: 15px; align-items: center; padding: 8px 10px; border-radius: 5px; background: rgba(255,255,255,.06); }
.support_preaccess_item.passed { opacity: .72; }
</style>
<?php
    return ;
}
?>
<script>
 // Servira a faire du parcours automatique de vidéo - plus tard
 var support<?=$support["id"]; ?> = [
     <?php foreach ($support["asset"] as $asset) { ?>
     {
	 type: '<?=$asset["type"]; ?>',
	 src: '<?=$asset["content"]; ?>'
     },
     <?php } ?>
 ];
</script>
<table class="submenu support_view_layout">
    <tr class="submenutr">
	<td class="submenutd support_asset_cell">
	    <div
		class="module_menubar asset_menubar module_scroll_menu support_asset_menubar"
		id="support<?=$support["id"]; ?>"
	    >
		<?php require ("support_menu.php"); ?>
	    </div>
	</td>
	<td class="submenutd support_screen_cell">
	    <div
		id="support_asset_workspace_<?=$support["id"]; ?>"
		class="support_asset_workspace support_asset_workspace_support"
		data-support-id="<?=$support["id"]; ?>"
		style="display: flex; flex-direction: column; gap: 10px; align-items: stretch; height: calc(100vh - 115px); width: 100%; box-sizing: border-box;"
	    >
		<div class="support_asset_display_pane" style="min-width: 0; min-height: 260px; flex: 2 1 0; overflow: hidden;">
		    <?php require ("screen.php"); ?>
		</div>
		<div class="support_asset_intercom_pane" style="min-width: 0; min-height: 240px; flex: 1 1 0; display: flex; flex-direction: column; gap: 6px; overflow: hidden;">
		    <div class="support_asset_intercom_toolbar">
			<span>Discussion</span>
			<input type="button" value="Support ++" onclick="support_set_asset_layout(<?=$support["id"]; ?>, 'support_max');" />
			<input type="button" value="Support +" onclick="support_set_asset_layout(<?=$support["id"]; ?>, 'support');" />
			<input type="button" value="Intercom +" onclick="support_set_asset_layout(<?=$support["id"]; ?>, 'intercom');" />
			<input type="button" value="Intercom ++" onclick="support_set_asset_layout(<?=$support["id"]; ?>, 'intercom_max');" />
		    </div>
		    <div class="support_asset_intercom_box" style="position: relative; flex: 1 1 auto; min-height: 0; overflow: hidden;">
			<div
			    id="support_asset_intercom_<?=$support["id"]; ?>"
			    class="support_asset_intercom_content"
			    style="position: relative; height: 100%; overflow: hidden;"
			>
			    <div class="support_asset_intercom_placeholder">
				Sélectionne un élément de support pour ouvrir sa discussion.
			    </div>
			</div>
		    </div>
		</div>
	    </div>
	</td>
    </tr>
</table>
<script>
 support_prepare_asset_layout(<?=$support["id"]; ?>);
</script>
