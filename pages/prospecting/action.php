<?php foreach ($actions as $action) { ?>
    <?php $js = "silent_submitf(this, {tofill: 'actions$id', toclear: 'actions$id', clear_form: true});"; ?>
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
	    data-tooltip="<?=datex("d/m/Y H:i", date_to_timestamp($action["action_date"])); ?> (<?=$action["codename"]; ?>): <?=htmlentities($action["comment"]); ?>"
	    style="background-image: url('/res/prospecting/<?=$action["name"]; ?>.png')"
	    onclick="return xconfirm('act<?=$action["id"]; ?>') && <?=$js; ?>"

	></button>
    </form>
    <?php $score += [
	"lost" => -100,
	"transformed" => +100,
	"still" => 0,
	"progress" => 1,
	"regress" => -1,
    ][$action["consequence"]]; ?>
<?php } ?>
<style>
 #actionbar<?=$id; ?> {
     background-color: rgba(<?=score_color_table($score); ?>); /* <?=$score; ?> */
 }
</style>
