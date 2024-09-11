<?php
$js = "
silent_submitf(this, {
  tofill: 'support".$support["id"]."'
});
";
?>
<h2>
    <?php if (@strlen($support["name"])) { ?>
	<?=$support["name"]; ?>
	<?php if (is_teacher()) { ?>
	    (<?=$support["codename"]; ?>)
	<?php } ?>
    <?php } else { ?>
	<?=$support["codename"]; ?>
    <?php } ?>
</h2>
<br />
<?php if (is_teacher()) { ?>
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
	    <?php if (is_teacher()) { ?>
		<form
		    method="put"
		    action="/api/support_category/<?=$support["id_support_category"]; ?>/support/<?=$support["id"]; ?>/asset/<?=$asset["id"]; ?>"
		    style="display: inline-block;"
		>
		    <input type="button" name="up" value="&uarr;" onclick="<?=$js; ?>" style="width: 30px;" />
		</form>&nbsp;
		<form
		    method="put"
		    action="/api/support_category/<?=$support["id_support_category"]; ?>/support/<?=$support["id"]; ?>/asset/<?=$asset["id"]; ?>"
		    style="display: inline-block;"
		>
		    <input type="button" name="down" value="&darr;" onclick="<?=$js; ?>" style="width: 30px;" />
		</form>&nbsp;
		<input
		    type="button"
		    value="E"
		    style="width: 30px;"
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
			style="width: 30px; color: red;"
		    />
		</form>
	    <?php } ?>
	    <a
		class="asset_link"
		id="asset<?=$asset["id"]; ?>button"
		onclick="switch_asset(
		       this,
		       'screen_<?=$support["id"]; ?>',
		       '<?=$asset["type"]; ?>',
		       '<?=addslashes($asset["content"]); ?>'
		       );"
		style="cursor: pointer;"
	    >
		<?php if (@strlen($asset["name"])) { ?>
		    <?=$asset["name"]; ?>
		    <?php if (is_teacher()) { ?>
			(#<?=$asset["codename"]; ?>) <?=$asset["id"]; ?>
		    <?php } ?>
		<?php } else { ?>
		    <?=$asset["codename"]; ?>
		<?php } ?>
	    </a>
	</li>
    <?php } ?>
</ul>
