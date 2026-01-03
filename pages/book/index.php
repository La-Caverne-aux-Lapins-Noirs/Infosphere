
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
<div class="booktable is_admin" id="booktable">
    <?php require_once ("booktable.php"); ?>
</div>
