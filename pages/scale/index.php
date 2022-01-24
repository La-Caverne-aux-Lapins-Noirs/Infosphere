<?php

if (file_exists(__DIR__."/handle_request.php")
    && is_admin()
    && isset($_POST["action"]))
    require_once (__DIR__."/handle_request.php");

if (file_exists(__DIR__."/handle_parameters.php"))
    require_once (__DIR__."/handle_parameters.php");

require_once (__DIR__."/../error_net.php");

?>

<h2><?=$Dictionnary["Scale"]; ?></h2>

<?php
$scale = db_select_all("
  *, ${Language}_name as name
  FROM scale
  WHERE deleted = 0
  ORDER BY {$Language}_name
");

?>

<table class="content_table">
    <tr>
	<th>
	    X
	</th>
	<th>
	    A
	</th>
	<th>
	    B
	</th>
    </tr>
    <?php foreach ($scale as $sc) { ?>
	<tr>
	    <td><?=$sc["id"]; ?></td>
	    <td><?=$sc["codename"]; ?></td>
	    <td><?=$sc["name"]; ?></td>
	</tr>
    <?php } ?>
</table>
