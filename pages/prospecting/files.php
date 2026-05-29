<?php
$fbid = "file_browser".$p["id"];
$nocd = false;
$hide_path_browser = true;
$path_browser_can_cd = true;
$language = "";
$type = "subscription_file";
$page = "user";
$id = $p["id"];
$path = "admin/subscription/";
$locked_path = $path;
$target = $Configuration->UsersDir($p["codename"]).$path."/";
?>

<div class="prospect_file_browser" data-prospect-id="<?=$p["id"]; ?>">
    <?php $js = "silent_submit(this, '$fbid');"; ?>
    <form
	method="post"
	onsubmit="return <?=$js; ?>;"
	action="/api/user/<?=$p["id"]; ?>/subscription_file"
    >
	<input type="hidden" name="fbid" value="<?=$fbid; ?>" />
	<input type="hidden" name="nocd" value="<?=$nocd ? 1 : 0; ?>" />
	<input type="hidden" name="path_browser_can_cd" value="<?=$path_browser_can_cd ? 1 : 0; ?>" />
	<input type="hidden" name="language" value="<?=$language; ?>" />
	<input id="path<?=$p["id"]; ?>" type="hidden" name="path" value="<?=$path; ?>" />
	<input
	    type="file"
	    name="file"
	    multiple="true"
	    onchange="
		  path_browser_sync_upload_path(this); <?=$js; ?>
		  "
	/>
    </form>

    <?php require ("./tools/template/path_browser.phtml"); ?>
</div>
