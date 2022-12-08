<?php
$books = db_select_all("
  * FROM book ORDER BY name ASC
");
?>
<table class="content_table">
    <tr>
	<th><?=$Dictionnary["Name"]; ?></th>
	<th><?=$Dictionnary["Authors"]; ?></th>
	<th><?=$Dictionnary["Year"]; ?></th>
	<th><?=$Dictionnary["Status"]; ?></th>
    </tr>
    <?php foreach ($books as $book) { ?>
	<tr>
	    <td><?=$book["name"]; ?></td>
	    <td><?=$book["authors"]; ?></td>
	    <td><?=$book["edition"]; ?></td>
	    <td>
		<?php
		$status = db_select_all("
		   user.*,
		   book_user.status,
		   book_user.start_date,
		   book_user.end_date
		   FROM book_user
		   LEFT JOIN user ON book_user.id_user = user.id
		   WHERE id_book = {$book["id"]}
		   ");
		?>
		<?php if (is_admin()) { ?>

		<?php } else { ?>
		    
		<?php } ?>
	    </td>
	</tr>
    <?php } ?>
</table>
