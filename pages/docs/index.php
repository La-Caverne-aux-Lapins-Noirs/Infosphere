<?php
require_once ("get_docs.php");
?>
<h2 class="alignable_blocks"><?=$Dictionnary["Documents"]; ?></h2>

<div style="width: 95%; margin-left: 2.5%; height: 85%; margin-top: 2.5%">
    <div style="width: 50%; float: left; height: 100%;">
	<?php $js = "silent_submit(this, 'file_browser');"; ?>
	<form
	    method="post"
		    onsubmit="return <?=$js; ?>;"
		    action="/api/doc"
	>
	    <label for="file"><?=$Dictionnary["File"]; ?></label><br />
	    <input id="path2" type="hidden" name="path" value="" />
	    <input
		type="file"
		name="file"
		multiple="true"
		onchange="document.getElementById('path2').value = document.getElementById('pathfile_browser').value; <?=$js; ?>"
	    />
	</form>

	<?php
	$language = "";
	$type = "file";
	$page = "doc";
	$id = 0;
	$path = "";
	$target = $Configuration->DocDir();
	require ("./tools/template/path_browser.phtml");
	?>
    </div>

    <form method="post" target="_blank" action="/api/doc/0/generate" style="width: 49%; height: 100%; float: left; margin-top: 2.5%;">
	<input type="submit" value="<?=$Dictionnary["GenerateDocument"]; ?>" style="width: 100%; height: 50px;" />
	<input type="text" value="" name="fields" placeholder="<?=$Dictionnary["DabsicFields"]; ?>" style="width: 100%; height: 50px;" />
	<div class="doclist">
	    <?php foreach (get_docs() as $doc) { ?>
		<input type="hidden" id="<?=$doc; ?>" name="<?=$doc; ?>" value="0" />
		<div onclick="
			document.getElementById('<?=$doc; ?>').value = document.getElementById('<?=$doc; ?>').value == '0' ? '1' : '0';
			this.classList.toggle('selected');
		">
		    <?=$doc; ?>
		</div>
	    <?php } ?>
	</div>
    </form>
</div>
