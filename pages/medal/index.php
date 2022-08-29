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

<?php if (is_teacher()) { ?>
    <table class="afullscreen"><tr><td class="formular_slot">
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
		>
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
		    <select name="configuration">
			<?php foreach (sort(all_configuration_files($Configuration->MedalsDir("_ressources"))) as $list) { ?>
			    <option value="<?=$list; ?>"><?=$list; ?></option>
			<?php } ?>
		    </select>
		    <input
			type="file"
			name="picture"
			accept="image/png"
			placeholder="<?=$Dictionnary["Icon"]; ?>"
			value="<?=try_get($_POST, "icon"); ?>"
		    /><br />
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
		    action="/api/medal"
		>
		    <label for="file"><?=$Dictionnary["PictureOrConfiguration"]; ?></label><br />
		    <input
			type="file"
			name="file"
			multiple="true"
			onchange="<?=$js; ?>"
		    />
		</form>
	    <?php } ?>
	    <div id="medalres" style="height: calc(100% - 100px);">
		<?php
		$page = "medal";
		$language = "";
		$file_browser_height = "calc(100% - 35px)";
		$target = $Configuration->MedalsDir("_ressources");
		$path = "/";
		$type = NULL;
		$id = NULL;
		require ("./tools/template/path_browser.phtml");
		?>
	    </div>
	</td></tr></table>
    </td></tr></table>
<?php } ?>

