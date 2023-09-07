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
<table class="submenu" style="height: 100%;">
    <tr class="submenutr">
	<td class="submenutd" style="
		   <?php if (is_teacher()) { ?>
		   width: 420px;
		   <?php } else { ?>
		   width: 220px;
		   <?php } ?>
		   "
	>
	    <div
		class="module_menubar asset_menubar"
		id="support<?=$support["id"]; ?>"
	    >
		<?php require ("support_menu.php"); ?>
	    </div>
	</td>
	<td class="submenutd">
	    <?php require ("screen.php"); ?>
	</td>
    </tr>
</table>

