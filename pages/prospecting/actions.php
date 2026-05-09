<div class="action_div" data-prospect-id="<?=$p["id"]; ?>" id="actionbar<?=$p["id"]; ?>">
    <div class="actions" id="actions<?=$p["id"]; ?>">
	<?php
	$score = 0;
	$actions = fetch_prospecting_actions($id = $p["id"]);
	$actions = $actions->value;
	$done = false;
	$last_action = 0;
	require ("action.php");
	?>
    </div>

    <div class="action_edit">
        <button type="button" onclick="toggle_action_menu(this); event.stopPropagation();">
            + Ajouter
        </button>

        <div class="action_menu hidden">
	    <?php $js = "return silent_submitf(this.parentNode, {tofill: 'actions{$p["id"]}', toclear: 'actions{$p["id"]}', clear_form: true});"; ?>
            <form method="post" action="/api/prospect/<?=$p["id"]; ?>/paction" onsubmit="<?=$js; ?>">
		<input type="hidden" name="id_user" value="<?=$p["id"]; ?>" />
		<select name="id_action" class="bigselect" data-column="3" data-popup-width="900">
		    <?php foreach (db_select_all("* FROM action") as $type) { ?>
			<option
			    value="<?=$type["id"]; ?>"
			    style="background-color: <?=$type["color"]; ?>; color: black;"
			>
			    <?=$Dictionnary[$type["name"]]; ?>
			</option>
		    <?php } ?>
		</select>
                <input type="text" name="comment" />
                <input type="button" value="+" onclick="<?=$js; ?>" />
            </form>
        </div>
    </div>
</div>

