<?php $js = "silent_submit(this, 'robotlist');"; ?>
<style>
 .robottab input
 {
     margin: 5px 5px 5px 5px;
     padding: 5px 5px 5px 5px;
 }
</style>
<h2 class="alignable_blocks"><?=$Dictionnary["Robot"]; ?></h2>
<table><tr><td style="width: 80%;" id="robotlist" class="robottab">
    <?php require_once ("robots.phtml"); ?>
</td><td>
    <form
	method="post"
	onsubmit="return <?=$js; ?>"
	action="/api/robot"
	class="formular_slot"
	style="padding: 10px 10px 10px 10px; margin-left: 10px;"
    >
	<h3><?=$Dictionnary["UploadAFile"]; ?></h3>
	<br />
	<br /><br />
	<input type="file" name="file" require />
	<br /><br />
	<input
	    type="button"
	    onclick="return <?=$js; ?>"
	    value="<?=$Dictionnary["UploadAFile"]; ?>"
	/>
    </form>
</td></tr></table>
