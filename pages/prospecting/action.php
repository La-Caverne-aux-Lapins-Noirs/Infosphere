<?php $def_action = 0; ?>
<span class="action_items">
<?php foreach ($actions as $action) { ?>
    <?php $js = "silent_submitf(this, {tofill: 'actions$id', toclear: 'actions$id', clear_form: true, after_success: prospecting_after_action_update, after_success_parameter: $id});"; ?>
    <form
	method="delete"
	action="/api/prospect/<?=$id; ?>/paction/<?=$action["id"]; ?>"
	onsubmit="return <?=$js; ?>"
	style="display: inline-block;"
	id="act<?=$action["id"]; ?>"
    >
	<button
	    type="button"
	    class="action"
	    data-tooltip="<?=datex("d/m/Y H:i", date_to_timestamp($action["action_date"])); ?> (<?=$action["codename"]; ?>)&#10;<?=$action["name"]; ?>&#10;<?=htmlentities($action["comment"]); ?>"
	    style="background-image: url('/res/prospecting/<?=$action["name"]; ?>.png');"
	    onclick="return xconfirm('act<?=$action["id"]; ?>') && <?=$js; ?>"
	></button>
    </form>
    <?php $score += [
	"lost" => -1000,
	"transformed" => +100,
	"still" => 0,
	"progress" => 1,
	"regress" => -1,
    ][$action["consequence"]];
    if ($action["consequence"] == "lost")
	$done = true;
    else if ($action["consequence"] == "transformed")
    {
	if (($def_action += 1) >= 2)
	    $done = true;
    }
    if ($last_action < date_to_timestamp($action["action_date"]))
	$last_action = date_to_timestamp($action["action_date"]);
    ?>
<?php } ?>
</span>
<?php if ($done && $score > 0) { ?>
    <span class="prospect_status_marker prospect_status_completed" aria-hidden="true"></span>
<?php } else if ($done) { ?>
    <span class="prospect_status_marker prospect_status_lost" aria-hidden="true"></span>
<?php } ?>
<span class="action_alert">
<style>
 #actionbar<?=$id; ?> {
     <?php if ($done && $score > 0) { ?> 
     background: linear-gradient(to right, red, orange, yellow, green, blue, purple);
     <?php } else { ?>
     background-color: rgba(<?=score_color_table($score); ?>); /* <?=$score; ?> */
     <?php } ?>
 }
</style>

<?php if (!$done && $last_action < now() - 30 * $one_day) { ?>
    <button type="button" class="alert" style="color: red;">
	&#9888;
    </button>
<?php } else if (!$done && $last_action < now() - 15 * $one_day) { ?>
    <button type="button" class="alert" style="color: orange;">
	&#9888;
    </button>
<?php } else if (!$done && $last_action < now() - 7 * $one_day) { ?>
    <button type="button" class="alert" style="color: yellow;">
	&#9888;
    </button>
<?php } else if (!$done && $last_action < now() - 3 * $one_day) { ?>
    <button type="button" class="alert" style="color: white;">
	&#9888;
    </button>
<?php } ?>


</span>
