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
