<?php if (is_admin()) { ?>
        <br />
	<form method="POST" action="index.php?p=<?=$Position; ?>"  class="add_formular" enctype="multipart/form-data">
	<input type="hidden" name="action" value="add" />
	<h2><?=$Dictionnary["AddARobot"]; ?></h2><br />
	<div>
	    <input
		type="text"
		name="codename"
		placeholder="<?=$Dictionnary["CodeName"]; ?>"
		value="<?=try_get($_POST, "codename"); ?>"
	    /><br />
		<input
		    type="text"
		    name="version"
		    placeholder="<?=$Dictionnary["Version"]; ?>"
		    value="<?=try_get($_POST, "version"); ?>"
		/><br />
		<input
		    type="file"
		    name="file"
		    placeholder="<?=$Dictionnary["File"]; ?>"
		    value="<?=try_get($_POST, "file"); ?>"
		/><br />
	    <input type="submit" value="&#10003;" />
	</div>
    </form>
<?php } ?>


