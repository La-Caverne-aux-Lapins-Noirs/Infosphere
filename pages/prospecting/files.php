<?php
$fbid = "file_browser".$p["id"];
$nocd = true;
$language = "";
$type = "file";
$page = "user";
$id = $p["id"];
$path = "admin/subscription/";
$target = $Configuration->UsersDir($p["codename"]).$path;
?>

<div style="width: 100%;">
    <?php $js = "silent_submit(this, '$fbid');"; ?>
    <form
	method="post"
	onsubmit="return <?=$js; ?>;"
	action="/api/user/<?=$p["id"]; ?>/file"
    >
	<input type="hidden" name="fbid" value="<?=$fbid; ?>" />
	<input type="hidden" name="nocd" value="<?=$nocd ? 1 : 0; ?>" />
	<input id="path<?=$p["id"]; ?>" type="hidden" name="path" value="<?=$path; ?>" />
	<input
	    type="file"
	    name="file"
	    multiple="true"
	    onchange="
		  document.getElementById('path<?=$p["id"]; ?>').value = document.getElementById('path<?=$fbid.$language; ?>').value; <?=$js; ?>
		  "
	/>
    </form>

    <?php require ("./tools/template/path_browser.phtml"); ?>
</div>
