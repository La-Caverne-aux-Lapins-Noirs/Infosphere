
<h2 class="alignable_blocks"><?=$Dictionnary["Library"]; ?></h2>

<form
    method="get"
    action="/api/book"
    onsubmit="return use_search('/api/book', this, 'booktable');"
>
    <input
	type="text"
	id="searchbar"
    />
</form>
<div class="booktable is_admin">
    <div class="content">
	<?php
	$books = fetch_books()->value;
	require_once ("table_content.php");
	?>
    </div>
    <div class="history">
	<?php foreach (db_select_all("
		* FROM book_user LEFT JOIN book ON book.id = book_user.id_book
		WHERE id_user = {$User["id"]} ORDER BY last_update DESC
		") as $book) { ?>
	    <p style="text-align: center;">
		<b><?=$book["name"]; ?></b><br />
		<?php
		if ($book["status"] == 0)
		{
		    echo $Dictionnary["YourRequestIsBeingTreated"]."<br />";
		    echo $Dictionnary["YouSubmittedYourRequestOn"]." ".human_date($book["last_update"], true).".";
		}
		else if ($book["status"] == 1)
		{
		    echo $Dictionnary["BorrowingAuthorized"]."<br />";
		    echo $Dictionnary["YouCanHaveTheBook"]."<br />";
		    echo $Dictionnary["ComeBeforeToTakeIt"]." ".human_date(date_to_timestamp($book["last_update"]) + 7 * 24 * 60 * 60);
		}
		else if ($book["status"] == 2)
		{
		    echo $Dictionnary["YouHaveTheBook"]."<br />";
		    echo $Dictionnary["ReturnedDateIsBefore"]." ".human_date($book["end_date"], true);
		}
		else if ($book["status"] == 3)
		{
		    echo $Dictionnary["YouHaveReturnedTheBookOn"]." ".human_date($book["last_update"], true);
		}
		else if ($book["status"] == -1)
		{
		    if ($book["id_last_user"] != $book["id_user"] && $book["id_last_user"] != NULL)
		    {
			echo $Dictionnary["YouRequestWasDeniedBy"]."<br />";
			display_nickname($book["id_last_user"]);
		    }
		    else
			echo $Dictionnary["YouCanceledYourRequest"];
		}
		?>
		<br /><br />
	    </p>
	<?php } ?>
    </div>
    <?php if (is_admin()) { ?>
	<form method="post" action="/api/book" class="admin">
	    <input type="text" name="codename" placeholder="<?=$Dictionnary["CodeName"]; ?>" /><br />
	    <input type="text" name="name" placeholder="<?=$Dictionnary["Name"]; ?>" /><br />
	    <input type="text" name="author" placeholder="<?=$Dictionnary["Author"]; ?>" /><br />
	    <input type="text" name="edition" placeholder="<?=$Dictionnary["Year"]; ?>" /><br />
	    <input type="button" onclick="<?=$js; ?>" value="<?=$Dictionnary["Add"]; ?>" />
	</form>
    <?php } ?>
</div>
