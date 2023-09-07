<div
    class="resume_module"
    id="resume_module_<?=$category["type"].$category["id"]; ?>"
>
    <h3 style="width: 70%;">
	<?=strlen($category["name"]) ? $category["name"] : $category["codename"]; ?>
    </h3>
    <?php if (is_teacher()) { ?>
	<?php $js = "silent_submitf(this, {toremove: 'resume_module_".$category["type"].$category["id"]."'});"; ?>
	<form
	    method="delete"
	    <?php if (isset($category["id_support_category"])) { ?>
		action="/api/support_category/<?=$category["id_support_category"]; ?>/support/<?=$category["id"]; ?>"
	    <?php } else { ?>
		action="/api/support_category/<?=$category["id"]; ?>"
	    <?php } ?>
	    onsubmit="return window.confirm('<?=$Dictionnary["Confirm"]; ?>') && <?=$js; ?>"
	    style="position: absolute; right: 0px; top: 0px;"
	>
	    <input
		type="button"
		onclick="window.confirm('<?=$Dictionnary["Confirm"]; ?>') && <?=$js; ?>"
		class="delete_wide"
		value="&#10007;"
		style="
		      width: 30px;
		      height: 30px;
		      color: red;
		      border-top-right-radius: 15px;
		      border-bottom-right-radius: 3px;
		      border-bottom-left-radius: 3px;
		      "
	    />
	</form>
    <?php } ?>
    <input
	type="button"
	value="&rarr;"
	onclick="display_panel('<?=
	      $category["type"] == "category" ? "category" : "support"; ?>',
	      <?=$category["id"]; ?>
	      );"
	style="
	      position: absolute;
	      right: 0px;
	      bottom: 0px;
	      width: 30px;
	      <?php if (is_teacher()) { ?>
	      height: calc(100% - 31px);
	      border-top-left-radius: 3px;
	      border-top-right-radius: 3px;
	      <?php } else { ?>
	      height: calc(100%);
	      border-top-right-radius: 15px;
	      <?php } ?>
	      border-bottom-right-radius: 15px;
	      color: yellow;
	      "
	/>
    <br />
    <p
	class="module_description"
	style="
	       text-indent: 40px;
	       width: calc(100% - 50px);
	       height: calc(100% - 75px);
	       "
    >
	<?=markdown($category["description"], true); ?>
    </p>
    <div style="position: absolute; bottom: 10px;">
	<?php
	if ($category["type"] == "category")
	{
	    $sub = $category["support"];
	    $alen = 0;
	    foreach ($sub as $support)
		$alen += count($support["asset"]);
	    $slen = count($sub);
	}
	else
	{
	    $sub = $category["asset"];
	    $slen = 0;
	    $alen = count($sub);
	}
	?>
	<?php if ($alen == 0) { ?>
	    <i><?=$Dictionnary["CurrentlyNotAvailable"]; ?></i>
	<?php } else { ?>
	    <b><?=$Dictionnary["Length"]; ?></b> :
	    <?php if ($slen > 0) { ?>
		<?=$slen; ?>
		<?php if ($slen > 1) { ?>
		    <?=$Dictionnary["topics"]; ?>,
		<?php } else { ?>
		    <?=$Dictionnary["topic"]; ?>,
		<?php } ?>
	    <?php } ?>
	    <?=$alen; ?>
	    <?php if ($alen > 1) { ?>
		<?=$Dictionnary["chapters"]; ?>
	    <?php } else { ?>
		<?=$Dictionnary["chapter"]; ?>
	    <?php } ?>
	<?php } ?>
    </div>
</div>
