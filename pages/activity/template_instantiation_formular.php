<div>
    <label for="suffix"><?=$Dictionnary["Instantiate"]; ?></label>
    <input type="text" name="suffix" placeholder="<?=$Dictionnary["Suffix"]; ?>" />
</div>
<div>
    <label for="first_day"><?=$Dictionnary["FirstDay"]; ?></label>
    <input type="datetime-local" name="first_day" />
</div>
<?php if ($activity->parent_activity != -1) { ?>
    <div>
	<label for="parent"><?=$Dictionnary["Parent"]; ?></label>
	<?php
	$parents = db_select_all("
	  id,
	  codename
	  FROM activity
	  WHERE id_template = {$activity->parent_activity}
	  AND deleted = 0
	");
	?>
	<select name="parent">
	    <?php foreach ($parents as $p) { ?>
		<option value="<?=$p["id"]; ?>"><?=$p["codename"]; ?></option>
	    <?php } ?>
	</select>
    </div>
<?php } ?>
<div>
    <input type="button" onclick="" value="<?=$Dictionnary["Instantiate"]; ?>" />
</div>

<?php
$instances = db_select_all("
  id,
  codename,
  template_link
  FROM activity
  WHERE id_template = $activity->id
  AND (done_date IS NULL OR done_date > NOW()) AND deleted = 0
");
?>
<?php foreach ($instances as $i) { ?>
    <div
	class="form_entry download_button"
	style="width: 100%;"
	onclick="window.open('index.php?p=InstancesMenu?a=<?=$i["id"]; ?>', '_blank');"
    >
	<?=$i["codename"]; ?>
    </div>
<?php } ?>
