<?php
$id = try_get($_GET, "a", -1);
$unique = $id != -1;
?>

<div>
    <h2 class="alignable_blocks"><?=$Dictionnary["School"]; ?></h2>
    <?php if ($id != -1) { ?>
	<?php if (($school = fetch_school($id)) != []) { ?>
	    <h2 class="alignable_blocks">- <?=$school["name"]; ?></h2>
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

    <table class="big_top_table"><tr>
	<?php require ("display_school.phtml"); ?>
    </tr></table>
    
<?php } else { ?>

    <?php if (is_admin()) { ?>
	<table class="edit_console"><tr><td><div class="fullscreen scrollable" id="schoollist">
    <?php }?>
    <?php require ("list_school.phtml"); ?>
    <?php if (is_admin()) { ?>
	</div></td><td class="formular_slot">
            <?php require ("add_school.phtml"); ?>
	</td></tr></table>
    <?php } ?>
<?php } ?>


