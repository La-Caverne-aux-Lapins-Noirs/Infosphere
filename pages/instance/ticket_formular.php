<?php
$ticket_id = isset($ticket["id"]) ? (int)$ticket["id"] : NULL;
$ticket_title = htmlspecialchars(isset($ticket["title"]) ? $ticket["title"] : "", ENT_QUOTES);
$ticket_description = htmlspecialchars(isset($ticket["description"]) ? $ticket["description"] : "", ENT_QUOTES);
$ticket_estimated_time = isset($ticket["estimated_time"]) ? (int)$ticket["estimated_time"] : "";
$ticket_status = isset($ticket["status"]) ? (int)$ticket["status"] : 0;
$ticket_sprint_id = isset($sprint["id"]) ? (int)$sprint["id"] : -1;
$ticket_form_config = "{tofill: 'ticket_list".$ticket_sprint_id."', clear_form: ".($ticket_id ? "false" : "true")."}";
$js = "silent_submitf(this, $ticket_form_config);";
?>
<form
    id="ticket_form<?=$ticket_sprint_id; ?>_<?=$ticket_id ? $ticket_id : "new"; ?>"
    <?php if ($ticket_id) { ?>
	method="put"
    <?php } else { ?>
        method="post"
    <?php } ?>
    onsubmit="return <?=$js; ?>"
    action="/api/team/<?=$activity->user_team["id"]; ?>/ticket<?=$ticket_id ? "/".$ticket_id : ""; ?>"
>
    <input type="hidden" name="real_time" value="0" />
    <input type="hidden" name="id_sprint" value="<?=$sprint["id"]; ?>" />
    <input
	type="text"
	name="title"
	value="<?=$ticket_title; ?>"
	placeholder="<?=$Dictionnary["Title"]; ?>"
	style="width: calc(100% - 40px); margin-left: 20px; font-size: large;"
    /><br />
    <br />

    <div style="width: 75%; float: right; margin-right: 20px;">
	<textarea
	    name="description"
	    style="resize: none; width: calc(100% - 80px); margin-left: 20px; float: left; height: <?=$ticket_id ? "160" : "110"; ?>px;"
	><?=$ticket_description; ?></textarea>
	<?php if ($ticket_id) { ?>
	    <?php $sfh = 75; ?>
	    <input
		style="width: 50px; margin-left: 10px; height: <?=$sfh; ?>px; float: right; color: red; margin-bottom: 10px;"
		type="button"
		onclick="return confirm('<?=$Dictionnary["Delete"]; ?>') && scrum_submit(this, 'delete', <?=$ticket_form_config; ?>);"
		value="&#10007;"
	    />  
	<?php } else { ?>
	    <?php $sfh = 110; ?>
	<?php } ?>
	<input
	    style="width: 50px; margin-left: 10px; height: <?=$sfh; ?>px; float: right; color: green; font-weight: bold;"
	    type="button"
	    onclick="return scrum_submit(this, '<?=$ticket_id ? "put" : "post"; ?>', <?=$ticket_form_config; ?>);"
	    value="<?=$ticket_id ? "&#10003;" : "+"; ?>"
	/>
    </div>
    
    <div style="width: calc(25% - 40px); float: left; margin-left: 20px;">
	<?=$Dictionnary["EstimatedTime"]; ?> (h) :<br />
	<input type="number" min="0" name="estimated_time" value="<?=$ticket_estimated_time; ?>" style="width: 90%;" /><br />

	<?=$Dictionnary["AttributeTo"]; ?> :<br />
	<select name="id_user" style="width: 90%;">
	    <option value=""><?=$Dictionnary["DoNotAttributeYet"]; ?></option>
	    <?php foreach ($activity->user_team["user"] as $usr) { ?>
		<option
		    value="<?=$usr["id"]; ?>"
		    <?php if (isset($ticket["user"]) && $ticket["user"] && $usr["id"] == $ticket["user"]["id"]) { ?>
			selected="selected"
		    <?php } ?>
		><?=htmlspecialchars($usr["codename"], ENT_QUOTES); ?></option>
	    <?php } ?>
	</select>
	
	<?php if ($ticket_id) { ?>
	    <?=$Dictionnary["Status"]; ?> :<br />
	    <select name="status" style="width: 90%;">
		<?php foreach ($TicketStatus as $k => $v) { ?>
		    <option
			value="<?=$k; ?>"
			<?php if ($ticket_status == $k) { ?>
			    selected="selected"
			<?php } ?>
		    ><?=$v; ?></option>
		<?php } ?>
	    </select><br />
	    <?=$Dictionnary["LogSpentTime"]; ?> :<br />
	    <input type="number" min="0" name="real_time" value="0" style="width: 90%;" />
	<?php } ?>
    </div>
</form>
