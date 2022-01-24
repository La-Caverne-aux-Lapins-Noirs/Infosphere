<?php

require_once("fetch_category.php");
require_once("handle_parameters.php");
require_once("handle_request.php");

?>

<h2><?=$Dictionnary["StudGallery"]; ?></h2>

<?php if ($category == NULL) { ?>
    <table class="content_table">
	<tr>
	    <th>#</th>
	    <th><?=$Dictionnary["CodeName"]; ?></th>
	    <th><?=$Dictionnary["Name"]; ?></th>
	    <th><?=$Dictionnary["Description"]; ?></th>
	    <th><?=$Dictionnary["RateID"]; ?></th>
	</tr>
	<?php
	$cnt = 0;
	foreach (fetch_category() as $y) {
	?>
	    <tr
		class="content_<?=$cnt++ % 2 ? "even" : "odd"; ?>"
	    >
		<td><a href="index.php?p=<?=$Position; ?>&amp;a=<?=$y["id"]; ?>"><?=$y["id"]; ?></a></td>
		<td><a href="index.php?p=<?=$Position; ?>&amp;a=<?=$y["id"]; ?>"><?=$y["codename"]; ?></a></td>
		<td><?=$y["catname"]; ?></td>
		<td><?=$y["catdesc"]; ?></td>
		<td><?=$y["id_rate"]; ?></td>
	    </tr>
		</a>
	<?php } ?>
    </table>
    <?php require_once("add_category.php"); ?>
<?php } else { ?>
    <h3><?=$Dictionnary["Class"]; ?>&nbsp;:&nbsp;<?=$category["catname"]; ?></h3>
    <a href="index.php?p=<?=$Position; ?>">&larr;</a>
    <table class="content_table">
	<tr>
	    <th>#</th>
	    <th><?=$Dictionnary["Name"]; ?></th>
	    <th><?=$Dictionnary["Range"]; ?></th>
	</tr>
	<?php foreach ($category["endpoint"] as $y) {?>
	    <tr
		class="content_<?=$cnt++ % 2 ? "even" : "odd"; ?>"
	    >
		<td><?=$y["id"]; ?></td>
		<td><?=$y["codename"]; ?></td>
		<td><?=$y["valrange"]; ?></td>
	    </tr>
	<?php } ?>
    </table>
    <?php require_once("add_endpoint.php"); ?>
<?php } ?>

