<?php
if (is_admin()) {
    require_once("fetch_activities.php");
    require_once("fetch_rate.php");
?>
    <br>
    <form method="POST" action="index.php?p=<?=$Position; ?>"  class="add_formular">
	<input type="hidden" name="action" value="add_endpoint" />
	<input type="hidden" name="id" value="<?=$category["id"]; ?>" />
	<div>
	    <input
		type="text"
		name="codename"
		placeholder="<?=$Dictionnary["CodeName"]; ?>"
		value="<?=try_get($_POST, "codename"); ?>"
	    /><br>
	    <input
		type="number"
		name="range"
		min="0"
		placeholder="<?=$Dictionnary["Range"]; ?>"
		value="<?=try_get($_POST, "range"); ?>"
	    />
	    <br>
	    <input type="submit" value="&#10003;" />
	</div>
    </form>
<?php } ?>
