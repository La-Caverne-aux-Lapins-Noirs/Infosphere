<?php require_once (__DIR__."/menu_helpers.php"); ?>
<?php
$js = "
silent_submitf(this, {
  tofill: 'support".$support["id"]."'
});
";
?>
<h2>
    <?php support_compact_label($support); ?>
    <?php if (can_edit_supports()) { ?>
	<?php support_copy_codename_button($support["codename"]); ?>
    <?php } ?>
</h2>
<br />
<?php if (can_edit_supports()) { ?>
    <a
	class="asset_link"
	id="asset<?=$support["id"]; ?>button"
	onclick="switch_asset(
	       this,
	       'screen_<?=$support["id"]; ?>',
	       'data',
	       '<?=base64_encode(requirex(__DIR__."/config_asset.php", ["support" => $support, "asset" => NULL])); ?>'
	       );"
	style="cursor: pointer;"
    >
	<?=$Dictionnary["AddAsset"]; ?>
    </a>
    <br /><br />
<?php } ?>
<ul>
    <?php foreach ($support["asset"] as $asset) { ?>
	<?php if ($asset["selected"] == false) continue ; ?>
	<li>
	    <?php if (can_edit_supports()) { ?>
		<form
		    method="put"
		    action="/api/support_category/<?=$support["id_support_category"]; ?>/support/<?=$support["id"]; ?>/asset/<?=$asset["id"]; ?>"
		    style="display: inline-block;"
		>
		    <input type="button" name="up" value="&uarr;" onclick="<?=$js; ?>" class="support_admin_button" title="Monter" />
		</form>
		<form
		    method="put"
		    action="/api/support_category/<?=$support["id_support_category"]; ?>/support/<?=$support["id"]; ?>/asset/<?=$asset["id"]; ?>"
		    style="display: inline-block;"
		>
		    <input type="button" name="down" value="&darr;" onclick="<?=$js; ?>" class="support_admin_button" title="Descendre" />
		</form>
		<input
		    type="button"
		    value="E"
		    class="support_admin_button"
		    title="Éditer"
		    onclick="switch_asset(
		       this,
		       'screen_<?=$support["id"]; ?>',
		       'data',
		       '<?=base64_encode(requirex(__DIR__."/config_asset.php", [
			   "support" => $support,
			   "asset" => $asset
			])); ?>'
		       );"
		/>
		<form
		    method="delete"
		    action="/api/support_category/<?=$support["id_support_category"]; ?>/support/<?=$support["id"]; ?>/asset/<?=$asset["id"]; ?>"
		    style="display: inline-block;"
		>
		    <input
			type="button"
			name="up"
			value="&#10007;"
			onclick="window.confirm('<?=$Dictionnary["ConfirmDeletion"]; ?>') && <?=$js; ?>;"
			class="support_admin_button support_delete_button"
			title="Supprimer"
		    />
		</form>
	    <?php } else { echo " - "; } ?>
	    <?php if (can_edit_supports()) { ?>
		<?php support_copy_codename_button($asset["codename"], "#"); ?>
	    <?php } ?>
	    <?php
		$video_class = isset($asset["video_status"]) ? " support_video_".$asset["video_status"] : "";
		$video_title = "";
		if (isset($asset["video_status"]))
		{
		    if ($asset["video_status"] == "pending")
			$video_title = "Encodage vidéo en attente";
		    else if ($asset["video_status"] == "running")
			$video_title = "Encodage vidéo en cours";
		    else if ($asset["video_status"] == "error")
			$video_title = "Erreur pendant l'encodage vidéo";
		}
		$empty_class = trim(support_asset_empty_menu_class($asset).$video_class);
		$empty_title = trim(support_asset_empty_menu_title($asset)." ".$video_title);
	    ?>
	    <a
		class="asset_link<?=(!can_edit_supports() && !empty($asset["unseen"])) ? " support_unseen_asset_link" : ""; ?>"
		id="asset<?=$asset["id"]; ?>button"
		data-support-asset-id="<?=$asset["id"]; ?>"
		data-support-id="<?=$support["id"]; ?>"
		data-support-asset-progress="<?=isset($asset["view_progress"]) ? (int)$asset["view_progress"] : 0; ?>"
		onclick="switch_asset(
		       this,
		       'screen_<?=$support["id"]; ?>',
		       '<?=$asset["type"]; ?>',
		       '<?=addslashes($asset["content"]); ?>',
		       '<?=isset($asset["hls_content"]) ? addslashes($asset["hls_content"]) : ""; ?>'
		       );"
		style="cursor: pointer;"
	    >
		<?php if (!can_edit_supports() && !empty($asset["unseen"])) { ?>
		    <span class="support_unseen_asset_marker" title="Support non consulté">◆</span>
		<?php } ?>
		<?php support_compact_label($asset, $empty_class, $empty_title, "#"); ?>
	    </a>
	</li>
    <?php } ?>
</ul>
