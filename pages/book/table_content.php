
<?php
$js = "
silent_submitf(this.parentNode, {
	tofill: 'booktable',
	toclear: 'booktable',
	clear_form: true
});
";
?>

<table class="content_table">
    <tr>
	<th><?=$Dictionnary["CodeName"]; ?></th>
	<th><?=$Dictionnary["Name"]; ?></th>
	<th><?=$Dictionnary["Author"]; ?></th>
	<th><?=$Dictionnary["Year"]; ?></th>
	<th><?=$Dictionnary["Availability"]; ?></th>
	<th><?=$Dictionnary["Status"]; ?></th>
    </tr>
    <?php foreach ($books as $book) { ?>
	<?php
	$status = db_select_all("
		book_user.*
		FROM book_user
		WHERE id_book = {$book["id"]} AND status <= 2 AND status != -1
		");
	$available = $book["nbr"];
	foreach ($status as $s) // Une boucle du fait de $filter
	    if ($s["status"] == 2 || $s["status"] == 1)
		$available -= 1;
	?>
	<tr>
	    <td>
		<?=$book["codename"]; ?>
	    </td>
	    <td>
		<?php
		$pdf = $Configuration->BookDir($book["codename"]);
		if (file_exists($pdf)) { ?>
		    <a href="<?=$pdf; ?>">
			<?=$book["name"]; ?>
		    </a>
		<?php } else { ?>
		    <?=$book["name"]; ?>
		<?php } ?>
	    </td>
	    <td><?=$book["authors"]; ?></td>
	    <td><?=$book["edition"]; ?></td>
	    <td><?=$available; ?> / <?=$book["nbr"]; ?></td>
	    <td>
		<form method="put" action="/api/book/<?=$book["id"]; ?>" onsubmit="<?=$js; ?>">
		    <input type="hidden" id="actionx" name="command" value="" />
		    <?php
		    if (($cst = db_select_all("
			* FROM book_user
			WHERE id_book = {$book["id"]} AND id_user = {$User["id"]}
			ORDER BY request_date DESC LIMIT 1
			")) != NULL)
		        $cst = $cst[0];
		    if (count($cst) == 0 || $cst["status"] == -1 || $cst["status"] == 3) { ?>
			<input
			    type="button"
			    name="ask"
			    value="<?=$Dictionnary["AskToBorrow"]; ?>"
			    onclick="actionx.value = this.name; <?=$js ; ?>"
			/>
		    <?php } else if ($cst["status"] == 0) { ?>
			<input
			    type="button"
			    name="cancel"
			    value="<?=$Dictionnary["CancelAskToBorrow"]; ?>"
			    onclick="actionx.value = this.name; <?=$js ; ?>"
			/>
		    <?php } else if ($cst["status"] == 1) { ?>
			<?=$Dictionnary["BorrowingAuthorized"]; ?><br />
			<?=$Dictionnary["YouCanHaveTheBook"]; ?><br />
			<input
			    type="button"
			    name="cancel"
			    value="<?=$Dictionnary["CancelAskToBorrow"]; ?>"
			    onclick="actionx.value = this.name; <?=$js ; ?>"
			/>
		    <?php } else if ($cst["status"] == 2) { ?>
			<?=$Dictionnary["YouHaveTheBook"]; ?>
		    <?php } ?>
		</form>
		<?php if ($available != $book["nbr"] || is_librarian()) { ?>
		    <?=$Dictionnary["Borrowers"]; ?> :<br />
		    <?php foreach ($status as $s) { ?>
			<?=display_nickname($s["id_user"]); ?>
			<?php if ($s["status"] == 2) { ?>
			    <?php if (date_to_timestamp($s["end_date"]) < now()) { ?>
				<span style="color: red; font-weight: bold;">
			    <?php } ?>
			    (<?=human_date($s["end_date"], true); ?>)
			    <?php if (date_to_timestamp($s["end_date"]) < now()) { ?>
				</span>
			    <?php } ?>
			<?php } ?>
			<?php if (is_librarian()) { ?>
			    <form method="put" action="/api/book/<?=$book["id"]; ?>">
				<input type="hidden" id="action" name="command" value="" />
				<?php if ($s["status"] == 0) { ?>
				    <input
					type="button"
				        name="accept"
				        value="<?=$Dictionnary["AcceptBorrow"]; ?>"
					onclick="action.value = this.name; <?=$js ; ?>"
				    />
				    <input
					type="button"
				        name="cancel"
				        value="<?=$Dictionnary["RefuseBorrow"]; ?>"
					onclick="action.value = this.name; <?=$js ; ?>"
				    />
				<?php } else if ($s["status"] == 1) { ?>
				    <input
					type="button"
				        name="gone"
					value="<?=$Dictionnary["DeclareTheBookAsBorrowed"]; ?>""
					onclick="action.value = this.name; <?=$js ; ?>"
				    />
				<?php } else if ($s["status"] == 2) { ?>
				    <input
					type="button"
					name="returned"
					value="<?=$Dictionnary["BookReturned"]; ?>"
					onclick="action.value = this.name; <?=$js ; ?>"
				    />
				<?php } ?>
			    </form>
			<?php } ?>
			<br />
		    <?php } ?>
		<?php } ?>
	    </td>
	</tr>
    <?php } ?>
</table>
