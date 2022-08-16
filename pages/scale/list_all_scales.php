<?php
$scale = db_select_all("
  id,
  codename,
  tag,
  ${Language}_name as name,
  last_edit_date
  FROM scale
  WHERE deleted = 0
  ORDER BY {$Language}_name
");
?>

<form method="post" action"<?=unrollurl(); ?>" class="add_formular">
    <h2><?=$Dictionnary["AddAScale"]; ?></h2>
    <div>
	<input type="hidden" name="action" value="add_scale" />
	<input type="text"
	       name="codename"
	       placeholder="<?=$Dictionnary["CodeName"]; ?>"
	       value="<?=try_get($_POST, "codename"); ?>"
	/><br />
	<input
	    type="text"
	    name="tags"
	    placeholder="<?=$Dictionnary["Tags"]; ?>"
	    value="<?=try_get($_POST, "tags"); ?>"
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
	<th class="medium_column"><?=$Dictionnary["CodeName"]; ?></th>
	<th class="small_column"><?=$Dictionnary["Tags"]; ?></th>
	<th class="wide_column"><?=$Dictionnary["Name"]; ?></th>
	<th class="medium_column"><?=$Dictionnary["Date"]; ?></th>
	<th class="wide_column">
	    <img src="./res/configuration.png" style="width: 30px; height: 30px;" />
	</th>
    </tr>
    <?php foreach ($scale as $sc) { ?>
	<?php $URL = unrollurl(["a" => $sc["id"]]); ?>
	<tr id="line_<?=$sc["id"]; ?>">
	    <td <?=clickable($URL); ?>><?=$sc["id"]; ?></td>
	    <td><?=$sc["codename"]; ?></td>
	    <td <?=clickable($URL); ?>><?=$sc["tag"]; ?></td>
	    <td <?=clickable($URL); ?>><?=$sc["name"]; ?></td>
	    <td <?=clickable($URL); ?>><?=$sc["last_edit_date"]; ?></td>
	    <td>
		<br />
		<form method="post" action="<?=unrollurl(); ?>#<?="line_".$sc["id"]; ?>">
		    <input type="hidden" name="action" value="edit_codename" />
		    <input type="hidden" name="id" value="<?=$sc["id"]; ?>" />
		    <input
			type="text"
			name="codename"
			placeholder="<?=$Dictionnary["CodeName"]; ?>"
		    />
		    <input type="submit" value="&#10003;" />
		</form><br />
		<form method="post" action="<?=unrollurl(); ?>#<?="line_".$sc["id"]; ?>">
		    <input type="hidden" name="action" value="edit_tags" />
		    <input type="hidden" name="id" value="<?=$sc["id"]; ?>" />
		    <input
			type="text"
			name="tags"
			placeholder="<?=$Dictionnary["EditTags"]; ?>"
		    />
		    <input type="submit" value="&#10003;" />
		</form><br />
		<?php foreach ($LanguageList as $k => $v) { ?>
		    <form method="post" action="<?=unrollurl(); ?>#<?="line_".$sc["id"]; ?>">
			<input type="hidden" name="action" value="edit_name" />
			<input type="hidden" name="id" value="<?=$sc["id"]; ?>" />
			<input
			    type="text"
			    name="<?=$k; ?>_name"
			    placeholder="<?=$v; ?>"
			/>
			<input type="submit" value="&#10003;" />
		    </form><br />
		<?php } ?>
	    </td>
	</tr>
    <?php } ?>
</table>
