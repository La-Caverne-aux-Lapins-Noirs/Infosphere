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
	    <?php require ("screen.php"); ?>
	</td>
    </tr>
</table>

