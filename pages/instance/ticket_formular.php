<?php $js = "silent_submitf(this, {tofill: 'ticket_list', clear_form: ".(isset($ticket["id"]) ? "false" : "true")."});"; ?>
<form
    id="ticket_form<?=isset($ticket["id"]) ? $ticket["id"] : ""; ?>"
    <?php if (isset($ticket["id"])) { ?>
	method="put"
    <?php } else { ?>
        method="post"
    <?php } ?>
    onsubmit="return <?=$js; ?>"
    action="/api/team/<?=$activity->user_team["id"]; ?>/ticket<?=isset($ticket["id"]) ? "/".$ticket["id"] : ""; ?>"
>
    <input type="hidden" name="real_time" value="0" />
    <input type="hidden" name="id_sprint" value="<?=$sprint["id"]; ?>" />
    <input
	type="input"
	name="title"
	value="<?=isset($ticket["title"]) ? $ticket["title"] : ""; ?>"
	placeholder="<?=$Dictionnary["Title"]; ?>"
	style="width: calc(100% - 40px); margin-left: 20px; font-size: large;"
    /><br />
    <br />

    <div style="width: 75%; float: right; margin-right: 20px;">
	<textarea
	    name="description"
	    style="resize: none; width: calc(100% - 80px); margin-left: 20px; float: left; height: <?=isset($ticket["id"]) ? "160" : "110"; ?>px;"
	><?=isset($ticket["description"]) ? $ticket["description"] : ""; ?></textarea>
	<?php if (isset($ticket["id"])) { ?>
	    <?php $sfh = 75; ?>
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
	    value="<?=isset($ticket["id"]) ? "&#10003;" : "+"; ?>"
	/>
    </div>
    
    <div style="width: calc(25% - 40px); float: left; margin-left: 20px;">
	<?=$Dictionnary["EstimatedTime"]; ?> (h) :<br />
	<input type="number" name="estimated_time" value="<?=isset($ticket["estimated_time"]) ? $ticket["estimated_time"] : ""; ?>" style="width: 90%;" /><br />

	<?=$Dictionnary["AttributeTo"]; ?> :<br />
	<select name="id_user" style="width: 90%;">
	    <option value=""><?=$Dictionnary["DoNotAttributeYet"]; ?></option>
	    <?php foreach ($activity->user_team["user"] as $usr) { ?>
		<option
		    value="<?=$usr["id"]; ?>"
		    <?php if (isset($ticket) && $ticket["user"] && $usr["id"] == $ticket["user"]["id"]) { ?>
			selected="selected"
		    <?php } ?>
		><?=$usr["codename"]; ?></option>
	    <?php } ?>
	</select>
	
	<?php if (isset($ticket["id"])) { ?>
	    <?=$Dictionnary["Status"]; ?> (h) :<br />
	    <select name="status" style="width: 90%;">
		<?php foreach ($TicketStatus as $k => $v) { ?>
		    <option
			value="<?=$k; ?>"
			<?php if ($ticket["status"] == $k) { ?>
			    selected="selected"
			<?php } ?>
		    ><?=$v; ?></option>
		<?php } ?>
	    </select><br />
	    <?=$Dictionnary["LogSpentTime"]; ?> :<br />
	    <input type="number" name="real_time" value="0" style="width: 90%;" />
	<?php } ?>
    </div>
</form>

