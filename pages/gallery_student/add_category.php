<?php
if (is_admin()) {
    require_once("fetch_activities.php");
    require_once("fetch_rate.php");
?>
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script>
     $(document).ready(function(){
	 $('input[name="type"]').click(function(){
	     var inputValue = $(this).attr("value");
	     var targetBox = $("." + inputValue);
	     $(".add").not(targetBox).hide();
	     $(targetBox).show();
	 });
     });
     $(document).ready(function(){
	 $('input[name="import_rate"]').click(function(){
	     var inputValue = $(this).attr("value");
	     var targetBox = $("." + inputValue);
	     $(".import_rate").not(targetBox).hide();
	     $(targetBox).show();
	 });
     });
     $(document).ready(function(){
	 $('input[name="rate"]').click(function(){
	     var inputValue = $(this).attr("value");
	     var targetBox = $("." + inputValue);
	     $(".rate").not(targetBox).hide();
	     $(targetBox).show();
	 });
     });
    </script>
    <br />
    <div class="radioselect">
	<h2><?=$Dictionnary["AddCategory"]; ?></h2><br />
	<input type="radio" name="type" value="import"/>
	<label for="import">Importer depuis une activité existante</label>
	<input type="radio" name="type" value="manual"/>
	<label for="manual">Créer manuellement</label>
    </div>
    <form method="POST" action="index.php?p=<?=$Position; ?>"  class="add_formular manual add">
	<input type="hidden" name="action" value="add_category" />
	<div>
	    <div id="manual">
		<input
		    type="text"
		    name="codename"
		    placeholder="<?=$Dictionnary["CodeName"]; ?>"
		    value="<?=try_get($_POST, "codename"); ?>"
		/>
		<br>
		<?php foreach ($LanguageList as $k => $v) { ?>
		    <div class="language_entry">
			<span><?=$v; ?></span><br />
			<input
			    type="text"
				  name="<?=$k; ?>_name"
				  placeholder="<?=$Dictionnary["Name"]; ?>"
				  value="<?=try_get($_POST, $k."_name"); ?>"
			/><br />
			<textarea
			    class="normal_text"
				   name="<?=$k; ?>_description"
				   placeholder="<?=$Dictionnary["Description"]; ?>"
			><?=try_get($_POST, $k."_description"); ?></textarea>
		    </div>
		<?php } ?>
	    </div>
	    <br>
	    <input class="radio" type="radio" name="rate" value="new" checked/>
	    <label class="radio" for="new">Créer un nouveau barème</label>
	    <input class="radio" type="radio" name="rate" value="existing"/>
	    <label class="radio" for="existing">Utiliser un barème existant</label>
	    <br>
	    <div class="existing rate">
		<select name="select_rate">
		    <?php foreach (fetch_rate() as $y) { ?>
			<option value="<?=$y['id']; ?>"><?=$y["id"]; ?></option>
		    <?php } ?>
		</select>
	    </div>
	    <br>
	    <input type="submit" value="&#10003;" />
	</div>
    </form>
    <form method="POST" action="index.php?p=<?=$Position; ?>"  class="add_formular import add">
	<input type="hidden" name="action" value="import_category" />
	<div>
	    <select name="activity">
		<?php foreach (fetch_activities() as $y) { ?>
		    <option value="<?=$y['id']; ?>"><?=$y[$Language."_name"]; ?></option>
		<?php } ?>
	    </select>
	    <br>
	    <input class="radio" type="radio" name="import_rate" value="import_new" checked/>
	    <label class="radio" for="new">Créer un nouveau barème</label>
	    <input class="radio" type="radio" name="import_rate" value="import_existing"/>
	    <label class="radio" for="existing">Utiliser un barème existant</label>
	    <br>
	    <div class="import_existing import_rate">
		<select name="select_rate">
		    <?php foreach (fetch_rate() as $y) { ?>
			<option value="<?=$y['id']; ?>"><?=$y["id"]; ?></option>
		    <?php } ?>
		</select>
	    </div>
	    <br>
	    <input type="submit" value="&#10003;" />
	</div>
    </form>
<?php } ?>
