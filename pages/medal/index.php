<div>
    <h2 class="alignable_blocks"><?=$Dictionnary["Medals"]; ?></h2>
    <form
	class="alignable_blocks searchbar"
	method="get"
	onsubmit="return use_search('/api/medal', this, 'medallist');"
    >
	<input
	    type="text"
	    id="searchbar"
	    placeholder="<?=$Dictionnary["Search"]; ?>"
	/>
    </form>
</div>
<br />
<?php if (is_teacher()) { ?>
    <script>
     function clear_formular(form)
     {
	 form.reset();
	 return ;
     }
     function load_form(form, data)
     {
	 var fields = form.elements;
	 
	 for (name in fields)
	 {
	     if (typeof fields[name].name === "undefined")
		 continue ;
	     if (typeof data[name] === "undefined")
		 continue ;
	     console.log(fields[name].type);
	     if (fields[name].type == "checkbox")
		 fields[name].checked = data[name];
	     else if (fields[name].type == "text")
		 fields[name].value = data[name];
	     else if (fields[name].type == "textarea")
		 fields[name].value = data[name];
	     else if (fields[name].type == "select-one")
		 fields[name].value = data[name];
	 }
     }
    </script>
<?php } ?>

<?php if (is_teacher()) { ?>
    <table class="afullscreen"><tr><td class="formular_slot" style="width: 65%;">
<?php } ?>
<div class="fullscreen scrollable" id="medallist">
    <?php $medals = fetch_medal(); ?>
    <?php require_once ("list_medal.phtml"); ?>
</div>
<?php if (is_teacher()) { ?>
    </td><td>
	<table class="fullscreen"><tr><td class="formular_slot">

	    <?php if (is_teacher()) { ?>
		<?php $js = "silent_submit(this, 'medallist');"; ?>
		<form
		    method="post"
		    onsubmit="return <?=$js; ?>;";
		    action="/api/medal"
		    id="medal_formular"
		    style="position: relative;"
		>
		    <label for="edit" style="position: absolute; top: 0px; right: 50px; text-align: right;">
			<?=$Dictionnary["Edit"]; ?>
		    </label>
		    <input type="checkbox" name="edit" style="position: absolute; top: 0px; right: 25px; width: 20px; height: 20px;" />
		    <input type="button" value="&#x1F9F9;&#xFE0E;" onclick="clear_formular(document.getElementById('medal_formular'));" style="position: absolute; top: 0px; right: 0px; width: 20px; height: 20px; color: white;" />
		    
		    <label for="codename"><?=$Dictionnary["CodeName"]; ?><br />
		    <input
			type="text"
			name="codename"
			placeholder="<?=$Dictionnary["CodeName"]; ?>"
		    /><br />
		    <label for="tags"><?=$Dictionnary["Tags"]; ?><br />
		    <input
			type="text"
			name="tags"
			placeholder="<?=$Dictionnary["Tags"]; ?>"
		    /><br />
		    <label for="type"><?=$Dictionnary["Type"]; ?><br />
		    <select name="type">
			<option value="0"><?=$Dictionnary["PositiveMedal"]; ?></option>
			<option value="1"><?=$Dictionnary["NegativeMedal"]; ?></option>
			<option value="2"><?=$Dictionnary["CriticalNegativeMedal"]; ?></option>
		    </select><br />
		    <br />

		    <?=$Dictionnary["PictureBasedMedal"]; ?>
		    <input
			type="file"
			name="icon"
			accept="image/png"
			placeholder="<?=$Dictionnary["Icon"]; ?>"
			value="<?=try_get($_POST, "icon"); ?>"
		    /><br />
		    <br />

		    <?=$Dictionnary["ConfigurationBasedMedal"]; ?>
		    <input type="text" name="specificator" placeholder="<?=$Dictionnary["Specificator"]; ?>" />
		    <select name="shape">
			<option value="1">
			    <?=$Dictionnary["BandShape"]; ?>
			</option>
			<option value="0">
			    <?=$Dictionnary["RoundShape"]; ?>
			</option>
		    </select><br />
		    <select name="configuration">
			<option value=""><?=$Dictionnary["NoConfiguration"]; ?></option>
			<?php foreach (all_configuration_files($Configuration->MedalsDir(".ressources")) as $list) { ?>
			    <option value="<?=$list; ?>"><?=$list; ?></option>
			<?php } ?>
		    </select><br />
		    <?=$Dictionnary["ConfigurationBasedMedalIcon"]; ?>
		    <select name="picture">
			<option value=""><?=$Dictionnary["NoIcon"]; ?></option>
			<?php foreach (all_files($Configuration->MedalsDir(".ressources"), ["png"]) as $list) { ?>
			    <option value="<?=$list; ?>"><?=$list; ?></option>
			<?php } ?>
		    </select><br />
		    <br />

		    <?php forge_language_formular([
			"name" => "text",
			"description" => "textarea"
		    ], []); ?>
		    <br />
		    <br />

		    <input
			type="button"
			value="&#10003;"
			onclick="<?=$js; ?>"
		    />
		</form>
	    <?php } ?>
	    
	</td><td class="formular_slot">
	    <?php $fbid = "medalres_browser"; ?>
	    <?php if (is_admin()) { ?>
		<?php $js = "silent_submit(this, '$fbid');"; ?>
		<form
		    method="post"
		    onsubmit="return <?=$js; ?>;"
		    action="/api/medal/-1/ressource"
		>
		    <label for="file"><?=$Dictionnary["PictureOrConfiguration"]; ?></label><br />
		    <input id="path2" type="hidden" name="path" value="" />
		    <input
			type="file"
			name="file"
			multiple="true"
			onchange="document.getElementById('path2').value = document.getElementById('path<?=$fbid; ?>').value; <?=$js; ?>"
		    />
		</form>
	    <?php } ?>
	    <div id="medalres" style="height: calc(100% - 170px);">
		<?php
		$page = "medal";
		$language = "";
		$file_browser_height = "calc(100% - 35px)";
		$target = $Configuration->MedalsDir(".ressources");
		$path = "/";
		$type = "ressource";
		$id = "-1";
		require ("./tools/template/path_browser.phtml");
		?>
	    </div>
	    <div style="height: 70px; width: calc(100% - 10px); margin-left: 5px;">
		<form method="put" onsubmit="return <?=$js; ?>;" action="/api/medal">
		    <input type="text" name="old_codename" value="" placeholder="<?=$Dictionnary["PreviousCodeName"]; ?>" />
		    <input type="text" name="new_codename" value="" placeholder="<?=$Dictionnary["NewCodeName"]; ?>" />
		    <input type="button" onclick="return <?=$js; ?>;" value="&#10003;" />
		</form>
	    </div>
	</td></tr></table>
    </td></tr></table>
<?php } ?>

