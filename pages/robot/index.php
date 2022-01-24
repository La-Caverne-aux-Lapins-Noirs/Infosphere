<?php

require_once ("fetch_robot.php");
require_once ("handle_request.php");

?>

<h2><?=$Dictionnary["Robot"]; ?></h2>

<table class="content_table">
    <tr>
	<th>#</th>
	<th><?=$Dictionnary["CodeName"]; ?></th>
	<th><?=$Dictionnary["File"]; ?></th>
	<th><?=$Dictionnary["Version"]; ?></th>
	<th><?=$Dictionnary["Complaint"]; ?></th>
	<th><?=$Dictionnary["Edition"]; ?></th>
    </tr>
    <?php
    $cnt = 0;
    foreach (fetch_robot() as $y) { if ($y["deleted"] == 0) {
    ?>
	<tr
	    class="content_<?=$cnt++ % 2 ? "even" : "odd"; ?>"
	>
	    <td><?=$y["id"]; ?></td>
	    <td><?=$y["codename"]; ?></td>
	    <td><a href="<?=$y["file"]; ?>">Lien</a></td>
	    <td><?=$y["version"]; ?></td>
	    <td>
		<?=$y["complaint"]; ?>
		<form method="post" action="index.php?p=<?=$Position;?>">
		    <input type="hidden" name="action" value="reset" />
		    <input type="hidden" name="id" value="<?=$y['id']; ?>" />
		    <input type="submit" value="Reset" style="color: blue;" />
		</form>
	    </td>
	    <td>
		<form method="post" action="index.php?p=<?=$Position;?>">
		    <input type="hidden" name="action" value="remove" />
		    <input type="hidden" name="id" value="<?=$y['id']; ?>" />
		    <input type="submit" value="&#10007;" style="color: red;" />
		</form>
		<?php if (is_admin()) { ?>
		    <form method="post" action="index.php?p=<?=$Position;?>">
			<input type="hidden" name="action" value="superremove" />
			<input type="hidden" name="id" value="<?=$y['id']; ?>" />
			<input type="submit" value="Super &#10007;" style="color: red;" />
		    </form>
		<?php } ?>
	    </td>
	</tr>
	    </a>
    <?php }} ?>
</table>
<?php require_once("add.php"); ?>
