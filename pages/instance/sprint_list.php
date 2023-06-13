<?php
// When used by the API
if (isset($data["id_sprint"]))
    $id_sprint = $data["id_sprint"];
else
    $id_sprint = -1;
?>
<table class="sprinttab">
    <tr style="border-bottom: solid 1px black;">
	<th>#</th>
	<th><?=$Dictionnary["Title"]; ?></th>
	<th><?=$Dictionnary["StartAt"]; ?></th>
	<th><?=$Dictionnary["DoneDate"]; ?></th>
	<th><?=$Dictionnary["Completion"]; ?></th>
	<th><?=$Dictionnary["RealTime"]; ?></th>
	<th><img src="res/cog.png" width="25" height="25" /></th>
    </tr>
    <?php $sfi = 0; ?>
    <?php if (count($activity->user_team["sprints"])) { ?>
	<?php foreach ($activity->user_team["sprints"] as $sprint) { ?>
	    <tr <?=$sfi % 2 ? 'style="background-color: lightgray;"' : ""; ?>>
		<td><?=$sprint["id"]; ?></td>
		<td><?=$sprint["title"]; ?></td>
		<td><?=datex("d/m/y", $sprint["start_date"]); ?></td>
		<td><?=datex("d/m/y", $sprint["done_date"]); ?></td>
		<td>
		    <?=$sprint["completed"]."/".$sprint["total"]; ?><br />
		    <?=$sprint["hour_completed"]."/".$sprint["hour_total"]; ?>
		    
		</td>
		<td><?=$sprint["hour_real"]; ?></th>
		<td>
		    <input
			id="edit_button_for<?=$sprint["id"]; ?>"
			type="button"
			    style="height: 100%; width: 100%;"
			<?php if ($id_sprint == $sprint["id"]) { ?>
			    value="<?=$Dictionnary["Close"]; ?>"
			<?php } else { ?>
			    value="<?=$Dictionnary["Edit/See"]; ?>"
			<?php } ?>
			onclick="toggle_edit_form(this, 'sprintedit<?=$sprint["id"]; ?>');"
		    />
		</td>
	    </tr>
	    <tr
		id="sprintedit<?=$sprint["id"]; ?>"
		style="
		    <?php if ($id_sprint == $sprint["id"]) { ?>
		    display: table-row;
		    <?php } else { ?>
		    display: none;
		    <?php } ?>
		    <?=$sfi % 2 ? "background-color: lightgray;" : ""; ?>
		    "
	    ><td colspan="7">
		<br />
		<?php require ("sprint_formular.php"); ?>
	    </td></tr>
	    <?php $sfi += 1; ?>
	<?php } ?>
    <?php } else { ?>
	<tr><td colspan="7" style="text-align: center; font-style: italic;">
	    <?=$Dictionnary["NoSprint"]; ?>
	</td></tr>
    <?php } ?>
</table>
<hr /><br />
