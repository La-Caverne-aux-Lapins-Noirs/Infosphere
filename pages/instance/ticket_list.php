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
if (isset($data["id_ticket"]))
    $id_ticket = $data["id_ticket"];
else
    $id_ticket = -1;
?>
<style>
 .small_titles > th
 {
     font-size: small;
 }
</style>
<table class="sprinttab">
    <tr style="border-bottom: solid 1px black;" class="small_titles">
	<th>#</th>
	<th style="width: 40%;"><?=$Dictionnary["Title"]; ?></th>
	<th><?=$Dictionnary["Creator"]; ?></th>
	<th><?=$Dictionnary["Responsible"]; ?></th>
	<th><?=$Dictionnary["EstimatedTime"]; ?></th>
	<th><?=$Dictionnary["TimeSpent"]; ?></th>
	<th><?=$Dictionnary["Status"]; ?></th>
	<th><?=$Dictionnary["CreationDate"]; ?></th>
	<th><?=$Dictionnary["DoneDate"]; ?></th>
    </tr>
    <?php $sfi = 0; ?>
    <?php if (count($tickets)) { ?>
	<?php foreach ($tickets as $ticket) { ?>
	    <tr>
		<td><?=$ticket["id"]; ?></td>
		<td><?=$ticket["title"]; ?></td>
		<td><?=$activity->user_team["user"][$ticket["id_author"]]; ?></td>
		<td><?=$activity->user_team["user"][$ticket["id_user"]]; ?></td>
		<td><?=$ticket["estimated_time"]; ?></td>
		<td><?=$ticket["real_time"]; ?></td>
		<td><?=[
		    $Dictionnary["Canceled"],
		    $Dictionnary["Refused"],
		    $Dictionnary["Awaiting"],
		    $Dictionnary["InProgress"],
		    $Dictionnary["ToReview"],
		    $Dictionnary["Done"],
		    ][$ticket["status"] + 2];
		    ?>
		</td>
		<td><?=$ticket["creation_date"]; ?></td>
		<td><?=$ticket["done_date"]; ?></td>
	    </tr>
	    <tr
		id="ticketedit<?=$ticket["id"]; ?>"
		style="
		<?php if ($id_ticket == $ticket["id"]) { ?>
		    display: table-row;
		<?php } else { ?>
		    display: none;
		<?php } ?>
		<?=$sfi % 2 ? "background-color: lightgray;" : ""; ?>
		    "
	    ><td colspan="9">
		<br />
		<?php require ("ticket_formular.php"); ?>
	    </td></tr>
	    <?php $sfi += 1; ?>
	<?php } ?>
    <?php } else { ?>
	<tr><td colspan="9" style="text-align: center;" font-size: italic;">
	    <?=$Dictionnary["NoTicket"]; ?>
	</td></tr>
    <?php } ?>
</table>
<hr /><br />

