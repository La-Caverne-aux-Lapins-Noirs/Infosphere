
<form
    method="post"
    action="index.php?p=<?=$Position; ?>"
    class="add_formular"
    enctype="multipart/form-data"
>
    <input type="hidden" name="action" value="add_school" />
    <h2><?=$Dictionnary["AddASchool"]; ?></h2>
    <div>
	<input
	    type="text"
	    name="codename"
	    placeholder="<?=$Dictionnary["CodeName"]; ?>"
	    value="<?=try_get($_POST, "codename"); ?>"
	/><br />
	<input type="hidden" name="MAX_FILE_SIZE" value="<?=1024 * 1024; ?>" />
	<input
	    type="file"
	    name="icon"
	    accept="image/png"
	    placeholder="<?=$Dictionnary["Icon"]; ?>"
	/><br />
	<?php forge_language_formular(["name" => "text"], $_POST); ?>
	<br />
	<input type="submit" value="&#10003;" />
    </div>
</form>
<br />

<table class="content_table">
    <tr>
	<th class="tiny_column">#</th>
	<th class="small_column"></th>
	<th class="small_column"><?=$Dictionnary["CodeName"]; ?></th>
	<th class="small_column"><?=$Dictionnary["Name"]; ?></th>
	<th class="medium_column"><?=$Dictionnary["Director"]; ?></th>
	<?php if (is_admin()) { ?>
	    <th class="medium_column"><img src="res/cog.png" /></th>
	<?php } ?>
    </tr>
    <?php foreach ($schools as $school) { ?>
	<?php $URL = unrollurl(["a" => $school["id"]]); ?>
	<tr class="content_<?=$cnt++ % 2 ? "even" : "odd"; ?>">
	    <td <?=clickable($URL); ?>><?=$school["id"]; ?></td>
	    <td <?=clickable($URL); ?>><?php print_icon("SchoolsDir", $school["codename"]); ?></td>
	    <td <?=clickable($URL); ?>><?=$school["codename"]; ?></td>
	    <td <?=clickable($URL); ?>><?=$school["name"]; ?></td>
	    <td>
		<?php
		$directors = db_select_all("
                  * FROM user_school LEFT JOIN user ON user_school.id_user = user.id
                  WHERE user_school.id_school = {$school["id"]} AND user_school.authority = 1
		");
		?>
		<?php foreach ($directors as $director) { ?>
		    <?php display_nickname($director); ?>
		<?php } ?>
	    </td>
	    <?php if (is_admin()) { ?>
		<td>
		    <form method="post" action="<?=unrollurl(); ?>">
			<input type="hidden" name="action" value="edit_school_director" />
			<input type="text" name="user" placeholder="<?=$Dictionnary["CodeName"]; ?>" />
			<input type="hidden" name="school" value="<?=$school["id"]; ?>" />
			<input type="submit" value="+" />
		    </form>
		    <br />
		    <form method="post" action="<?=unrollurl(); ?>">
			<input type="hidden" name="action" value="delete_school" />
			<input type="hidden" name="school" value="<?=$school["id"]; ?>" />
			<input type="submit" value="&#10007;" style="color: red;" />
		    </form>
		</td>
	    <?php } ?>
	</tr>
    <?php } ?>
</table>
