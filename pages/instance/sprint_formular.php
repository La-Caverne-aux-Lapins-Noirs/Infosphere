<?php $js = "silent_submitf(this, {tofill: 'sprint_list', clear_form: ".(isset($sprint["id"]) ? "false" : "true")."});"; ?>
<form
    id="sprint_form<?=isset($sprint["id"]) ? $sprint["id"] : ""; ?>"
    <?php if (isset($sprint["id"])) { ?>
	method="put"
    <?php } else { ?>
        method="post"
    <?php } ?>
    onsubmit="return <?=$js; ?>"
    action="/api/team/<?=$activity->user_team["id"]; ?>/sprint<?=isset($sprint["id"]) ? "/".$sprint["id"] : ""; ?>"
>
    <input
	type="input"
	name="title"
	value="<?=isset($sprint["title"]) ? $sprint["title"] : ""; ?>"
	placeholder="<?=$Dictionnary["Title"]; ?>"
	style="width: calc(100% - 40px); margin-left: 20px; font-size: large;"
    /><br />
    <br />

    <div style="width: 75%; float: right; margin-right: 20px;">
	<textarea
	    name="description"
	    style="resize: none; width: calc(100% - 80px); margin-left: 20px; height: 110px; float: left;"
	><?=isset($sprint["description"]) ? $sprint["description"] : ""; ?></textarea>
	<?php if (isset($sprint["id"])) { ?>
	    <?php $sfh = 50; ?>
	    <input
		style="width: 50px; margin-left: 10px; height: <?=$sfh; ?>px; float: right; color: red; margin-bottom: 10px;"
		type="button"
		onclick="confirm('<?=$Dictionnary["Delete"]; ?>') && (this.form.method = 'delete') && <?=$js; ?>"
		value="&#10007;"
	    />  
	<?php } else { ?>
	    <?php $sfh = 110; ?>
	<?php } ?>
	<input
	    style="width: 50px; margin-left: 10px; height: <?=$sfh; ?>px; float: right; color: green; font-weight: bold;"
	    type="button"
	    onclick="<?=$js; ?>"
	    value="<?=isset($sprint["id"]) ? "&#10003;" : "+"; ?>"
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

