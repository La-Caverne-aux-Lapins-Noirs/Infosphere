<?php
$id = try_get($_GET, "a", -1);
$unique = $id != -1;
?>

<div>
    <h2 class="alignable_blocks"><?=$Dictionnary["School"]; ?></h2>
    <?php if ($id != -1) { ?>
	<?php if (($school = fetch_school($id)) != []) { ?>
	    <?php $school = $school[array_key_first($school)]; ?>
	    <h2 class="alignable_blocks">- <?=$school["codename"]; ?></h2>
	<?php } ?>
    <?php } else { ?>
	<form
	    class="alignable_blocks searchbar"
	    method="get"
	    onsubmit="return use_search('/api/<?=$page; ?>', this, 'schoollist');"
	>
	    <input
		type="text"
		id="searchbar"
		placeholder="<?=$Dictionnary["Search"]; ?>"
	    />
	</form>
    <?php } ?>
</div>

<?php if ($unique) { ?>

<?php } else { ?>

    <?php if (is_admin()) { ?>
	<table class="edit_console"><tr><td><div class="fullscreen scrollable" id="cyclelist">
    <?php }?>
    <?php require ("list_school.phtml"); ?>
    <?php if (is_admin()) { ?>
	</div></td><td class="formular_slot">
            <?php require ("add_school.phtml"); ?>
	</td></tr></table>
    <?php } ?>
<?php } ?>


