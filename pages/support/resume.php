<?php if (is_teacher()) { ?>
    <?php
    $js = "silent_submitf(this, ".
	  "{".
	  "tofill: 'resume_class_list".
	  ($selcat ? $category["id"] : "").
	  "'}".
	  ") ; setTimeout(silent_submitf, 500, document.getElementById('fetch_menu_form'), {tofill: 'main_menu'});".
	  ";";
    ?>
    <form
	method="post"
	action="/api/support_category<?php isset($category["id"]) ? "/".$category["id"] : ""; ?>"
	style="
		text-align: center;
		background-color: lightgray;
		margin-left: 10px;
		border-radius: 10px;
		padding-top: 5px;
		padding-bottom: 5px;
		"
	onsubmit="<?=$js; ?>"
    >
	<?php if ($selcat) { ?>
	    <h2><?=$Dictionnary["AddAClass"]; ?></h2>
	<?php } else { ?>
	    <h2><?=$Dictionnary["AddACategory"]; ?></h2>
	<?php } ?>
	<?php
	forge_language_formular(
	    ["name" => "text", "description" => "textarea"],
	    [],
	    "_300pxw language_entry"
	);
	?>
	<div class="_300pxw language_entry" style="vertical-align: top;">
	    <input
		style="margin-top: 35px;"
		type="text"
		name="codename"
		class="_300pxw"
		placeholder="<?=$Dictionnary["CodeName"]; ?>"
	    /><br />
	    <select
		name="id_support_category"
		class="_300pxw"
		style="height: 40px;"
	    >
		<?php if ($selcat) { ?>
		    <option value="<?=$category["id"]; ?>">
			<?=$Dictionnary["Category"]; ?>
			<?=$category["name"]; ?>
			(<?=$category["codename"]; ?>)
		    </option>
		<?php } else { ?>
		    <option value="">
			<?=$Dictionnary["NewSupportCategory"]; ?>
		    </option>
		    <?php foreach ($categories as $cat) { ?>
			<option value="<?=$cat["id"]; ?>">
			    <?=$Dictionnary["Category"]; ?>
			    <?=$cat["name"]; ?>
			    (<?=$cat["codename"]; ?>)
			</option>
		    <?php } ?>
		<?php } ?>
	    </select>
	    <br />
	    <input
		type="button"
		onclick="<?=$js; ?>"
		style="height: 60px;"
		class="_300pxw"
		value="+"
	    />
	</div>
    </form>
<?php } ?>

<div id="resume_class_list<?=$selcat ? $category["id"] : ""; ?>">
    <?php foreach ($categories as $category) { ?>
	<?php if ($category["selected"] == false) continue ; ?>
	<?php require ("resume_class.php"); ?>
    <?php } ?>
</div>
