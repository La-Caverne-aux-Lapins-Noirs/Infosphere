<?php
if ($cteam["late_time"] != NULL)
{
    $late = date_to_timestamp($cteam["late_time"]) / 60;
    if ($late > 60)
	$late = sprintf("%.1fh", $late / 60);
    else
	$late = sprintf("%dm", $late);
    $late = " ($late)";
}
else
    $late = "";
?>

<?php if (!$activity->is_assistant && $activity->unique_session != NULL) { ?>
    <div style="position: absolute; width: 5%; left: 30%;">
	<?php if ($cteam["present"] == 1) { ?>
	    <span style="color: green;"><?=$Dictionnary["Present"]; ?></span>
	<?php } else if ($cteam["present"] == -1) { ?>
	    <span style="color: orange;">
		<?=$Dictionnary["Late"]; ?><?=$late; ?>
	    </span>
	<?php } else if ($cteam["present"] == -2) { ?>
	    <span style="color: red;"><?=$Dictionnary["Missing"]; ?></span>
	<?php } ?>
    </div>
<?php } ?>

<?php if ($activity->is_assistant) { ?>    
    <?php if ($activity->unique_session != NULL && $activity->reference_activity == -1) { ?>
	<div style="
		    position: absolute;
		    width: 20%; left: 30%;
		    text-align: center;
		    "
	>
	    <?=$Dictionnary["Presence"]; ?>:&nbsp;
	    <form
		method="put"
		<?php $js = "silent_submitf(this, {tofill: 'team_presence{$cteam["id"]}'});"; ?>
		action="/api/instance/<?=$activity->id; ?>/declare/<?=$cteam["id"]; ?>/present"
		onsubmit="return <?=$js; ?>"
		style="display: inline-block;"
	    >
		<input type="button"
		       onclick="<?=$js; ?>"
		       value="O"
		       style="color: green; width: 30px; height: 30px;
			     <?php if ($cteam["present"] == 1) { ?>
			     border: 3px solid green;
			     <?php } ?>
			     "
		/>
	    </form>
	    <form
		method="put"
		action="/api/instance/<?=$activity->id; ?>/declare/<?=$cteam["id"]; ?>/late"
		onsubmit="return <?=$js; ?>"
		style="display: inline-block; position: relative;"
	    >
		<div style="z-index: 1; position: absolute; top: 30px; color: orange;">
		    <?=$late; ?>
		</div>
		<input type="button"
		       onclick="<?=$js; ?>"
		       value="~"
		       style="color: orange; font-weight: bold; width: 30px; height: 30px;
			     <?php if ($cteam["present"] == -1) { ?>
			     border: 3px solid orange;
			     <?php } ?>
			     "
		/>
	    </form>
	    <form
		method="put"
		action="/api/instance/<?=$activity->id; ?>/declare/<?=$cteam["id"]; ?>/missing"
		onsubmit="return <?=$js; ?>"
		style="display: inline-block;"
	    >
		<input type="submit"
		       onclick="<?=$js; ?>"
		       value="&#216;"
		       style="color: red; width: 30px; height: 30px;
			     <?php if ($cteam["present"] == -2) { ?>
			     border: 3px solid red;
			     <?php } ?>
			     "
		/>
	    </form>
	</div>
    <?php } ?>
<?php } ?>
