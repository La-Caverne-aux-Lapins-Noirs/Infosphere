<?php
$sprint_id = isset($sprint["id"]) ? (int)$sprint["id"] : NULL;
$sprint_title = htmlspecialchars(isset($sprint["title"]) ? $sprint["title"] : "", ENT_QUOTES);
$sprint_description = htmlspecialchars(isset($sprint["description"]) ? $sprint["description"] : "", ENT_QUOTES);
$sprint_form_config = "{tofill: 'sprint_list', clear_form: ".($sprint_id ? "false" : "true").", after_success: refresh}";
$js = "silent_submitf(this, $sprint_form_config);";
?>
<form
    id="sprint_form<?=$sprint_id ? $sprint_id : ""; ?>"
    <?php if ($sprint_id) { ?>
	method="put"
    <?php } else { ?>
        method="post"
    <?php } ?>
    onsubmit="return <?=$js; ?>"
    action="/api/team/<?=$activity->user_team["id"]; ?>/sprint<?=$sprint_id ? "/".$sprint_id : ""; ?>"
>
    <input
	type="text"
	name="title"
	value="<?=$sprint_title; ?>"
	placeholder="<?=$Dictionnary["Title"]; ?>"
	style="width: calc(100% - 40px); margin-left: 20px; font-size: large;"
    /><br />
    <br />

    <div style="width: 75%; float: right; margin-right: 20px;">
	<textarea
	    name="description"
	    style="resize: none; width: calc(100% - 80px); margin-left: 20px; height: 110px; float: left;"
	><?=$sprint_description; ?></textarea>
	<?php if ($sprint_id) { ?>
	    <?php $sfh = 50; ?>
	    <input
		style="width: 50px; margin-left: 10px; height: <?=$sfh; ?>px; float: right; color: red; margin-bottom: 10px;"
		type="button"
		onclick="return confirm('<?=$Dictionnary["Delete"]; ?>') && scrum_submit(this, 'delete', <?=$sprint_form_config; ?>);"
		value="&#10007;"
	    />  
	<?php } else { ?>
	    <?php $sfh = 110; ?>
	<?php } ?>
	<input
	    style="width: 50px; margin-left: 10px; height: <?=$sfh; ?>px; float: right; color: green; font-weight: bold;"
	    type="button"
	    onclick="return scrum_submit(this, '<?=$sprint_id ? "put" : "post"; ?>', <?=$sprint_form_config; ?>);"
	    value="<?=$sprint_id ? "&#10003;" : "+"; ?>"
	/>
    </div>
    
    <div style="width: calc(25% - 40px); float: left; margin-left: 20px;">
	<?=$Dictionnary["StartAt"]; ?> :<br />
	<input
	    type="date"
	    name="start_date"
	    <?php if (isset($sprint["start_date"])) { ?>
		value="<?=str_replace("T00:00:00", "", db_form_date($sprint["start_date"])); ?>"
	    <?php } else { ?>
	        value="<?=str_replace("T00:00:00", "", db_form_date(first_day_of_week(now()), true)); ?>"
	    <?php } ?>
	/><br />
	<br />
	<?=$Dictionnary["DoneDate"]; ?> :<br />
	<input
	    type="date"
	    name="done_date"
	    <?php if (isset($sprint["done_date"])) { ?>
		value="<?=str_replace("T00:00:00", "", db_form_date($sprint["done_date"])); ?>"
	    <?php } else { ?>
	        value="<?=str_replace("T00:00:00", "", db_form_date(first_day_of_week(now() + 7 * 24 * 60 * 60), true)); ?>"
	    <?php } ?>
	/><br />
	<br />
    </div>
</form>
