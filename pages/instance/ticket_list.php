<?php
// Les informations sur le sprint
if (!isset($tab_data) || $tab_data == NULL)
    return ;
$id_sprint = $tab_data;
if (!isset($activity->user_team["sprints"][$id_sprint]["tickets"]))
    return ;
$sprint = $activity->user_team["sprints"][$id_sprint];
?>

<div style="width: 100%; margin-top: 10px; margin-bottom: 20px;">
    <h2 style="float: left; width: 36%; text-align: center; border-radius: 5px; background-color: rgba(0, 0, 0, 0.1); margin-right: 2%; margin-left: 2%">
	<?=$sprint["title"]; ?>
    </h2>
    <p style="float: right: width: 56%; text-align: justify; margin-left: 2%; margin-right: 2%; background-color: white; border-radius: 5px;">
	<?=$sprint["description"]; ?>
    </p>
</div>

<?php
$tickets = $sprint["tickets"];
// When used by the API
if (isset($data["id_sprint"]))
    $id_ticket = $data["id_sprint"];
else
    $id_ticket = -1;
?>

<style>
 .small_titles > th
 {
     font-size: small;
 }
 .small_titles > td
 {
     font-size: x-small;
 }
</style>
<table class="sprinttab" style="width: 100%;">
    <tr style="border-bottom: solid 1px black;" class="small_titles">
	<th style="width: 30px; font-size: xx-small;">#</th>
	<th style="width: 30%;"><?=$Dictionnary["Title"]; ?></th>
	<th><?=$Dictionnary["Creator"]; ?></th>
	<th><?=$Dictionnary["Responsible"]; ?></th>
	<th><?=$Dictionnary["EstimatedTime"]; ?></th>
	<th><?=$Dictionnary["TimeSpent"]; ?></th>
	<th><?=$Dictionnary["Status"]; ?></th>
	<th><?=$Dictionnary["CreationDate"]; ?></th>
	<th><?=$Dictionnary["DoneDate"]; ?></th>
	<th><img src="res/cog.png" width="25" height="25" /></th>
    </tr>
    <?php $sfi = 0; ?>
    <?php $total_estimated = 0; ?>
    <?php $total_real_time = 0; ?>
    <?php if (count($tickets)) { ?>
	<?php foreach ($tickets as $ticket) { ?>
	    <tr class="small_titles">
		<td><?=$ticket["id"]; ?></td>
		<td><?=$ticket["title"]; ?></td>
		<td>
		    <?=display_nickname($activity->user_team["user"][$ticket["id_author"]]); ?>
		</td>
		<td>
		    <?php if ($ticket["id_user"] != NULL
			      && $ticket["id_user"] != 0
			      && $ticket["id_user"] != -1) { ?>
			<?=display_nickname($activity->user_team["user"][$ticket["id_user"]]); ?>
		    <?php } else { ?>
			/
		    <?php } ?>
		</td>
		<td>
		    <?=$ticket["estimated_time"]; ?>
		    <?php $total_estimated += $ticket["estimated_time"]; ?>
		</td>
		<td>
		    <?=$ticket["real_time"]; ?>
		    <?php $total_real_time += $ticket["real_time"]; ?>
		</td>
		<td
		    style="
		    <?php if ($ticket["status"] < 0) { ?>
			color: red;
		    <?php } else { ?>
		        color: <?=["black", "blue", "orange", "green"][$ticket["status"]]; ?>;
		    <?php } ?>
	            <?php if ($ticket["status"] == 2) { ?>
			font-weight: bold;
		    <?php } ?>
			   "
		>
		    <?=$TicketStatus[$ticket["status"]]; ?>
		</td>
		<td><?=datex("d/m", $ticket["creation_date"]); ?></td>
		<td>
		    <?php if ($ticket["done_date"] != NULL) { ?>
			<?=datex("d/m", $ticket["done_date"]); ?>
		    <?php } else { ?>
			/
		    <?php } ?>
		</td>
		<td>
		    <input
			id="edit_button_for_ticket<?=$ticket["id"]; ?>"
			type="button"
			    style="height: 100%; width: 100%;"
			<?php if ($id_ticket == $ticket["id"]) { ?>
			    value="<?=$Dictionnary["Close"]; ?>"
			<?php } else { ?>
			    value="<?=$Dictionnary["Edit/See"]; ?>"
			<?php } ?>
			onclick="toggle_edit_form(this, 'ticketedit<?=$ticket["id"]; ?>');"
		    />
		</td>
	    </tr>
	    <tr
		id="ticketedit<?=$ticket["id"]; ?>"
		style="height: 260px;
		<?php if ($id_ticket == $ticket["id"]) { ?>
		    display: table-row;
		<?php } else { ?>
		    display: none;
		<?php } ?>
		<?=$sfi % 2 ? "background-color: lightgray;" : ""; ?>
		    "
	    ><td colspan="10">
		<br />
		<?php require ("ticket_formular.php"); ?>
	    </td></tr>
	    <?php $sfi += 1; ?>
	<?php } ?>
	<tr style="border-top: 1px solid lightgray;">
	    <td colspan="4" style="text-align: right;">
		<b><?=$Dictionnary["Total"]; ?> :</b>
	    </td><td>
		<?=$total_estimated; ?>
	    </td><td>
		<?=$total_real_time; ?>
	    </td><td colspan="4">
	    </td>
	</tr>
    <?php } else { ?>
	<tr><td colspan="10" style="text-align: center;" font-size: italic;">
	    <?=$Dictionnary["NoTicket"]; ?>
	</td></tr>
    <?php } ?>
</table>
<hr /><br />

