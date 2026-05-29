<?php
// Les informations sur le sprint
if (!isset($tab_data) || $tab_data == NULL)
    return ;
if (is_array($tab_data))
{
    $id_sprint = $tab_data["id_sprint"] ?? -1;
    $id_ticket = $tab_data["id_ticket"] ?? -1;
}
else
{
    $id_sprint = $tab_data;
    $id_ticket = -1;
}
if (!isset($activity->user_team["sprints"][$id_sprint]["tickets"]))
    return ;
$sprint = $activity->user_team["sprints"][$id_sprint];

if (!function_exists("scrum_user_label"))
{
    function scrum_user_label($activity, $id_user)
    {
	if ($id_user == NULL || $id_user == 0 || $id_user == -1)
	    return ("/");
	if (isset($activity->user_team["user"][$id_user]))
	    return (display_nickname($activity->user_team["user"][$id_user]));
	if (($user = resolve_codename("user", $id_user, "id", true))->is_error())
	    return ("#".$id_user);
	return (display_nickname($user->value));
    }
}
?>

<div style="width: 100%; margin-top: 10px; margin-bottom: 20px;">
    <h2 style="float: left; width: 36%; text-align: center; border-radius: 5px; background-color: rgba(0, 0, 0, 0.1); margin-right: 2%; margin-left: 2%">
	<?=htmlspecialchars($sprint["title"], ENT_QUOTES); ?>
    </h2>
    <p style="float: right; width: 56%; text-align: justify; margin-left: 2%; margin-right: 2%; background-color: white; border-radius: 5px;">
	<?=nl2br(htmlspecialchars($sprint["description"], ENT_QUOTES)); ?>
    </p>
</div>

<?php
$tickets = $sprint["tickets"];
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
	    <?php $status = isset($ticket["status"]) ? (int)$ticket["status"] : 0; ?>
	    <tr class="small_titles">
		<td><?=$ticket["id"]; ?></td>
		<td><?=htmlspecialchars($ticket["title"], ENT_QUOTES); ?></td>
		<td>
		    <?=scrum_user_label($activity, $ticket["id_author"]); ?>
		</td>
		<td>
		    <?=scrum_user_label($activity, $ticket["id_user"]); ?>
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
		    <?php if ($status < 0) { ?>
			color: red;
		    <?php } else { ?>
		        color: <?=(["black", "blue", "orange", "green"][$status] ?? "black"); ?>;
		    <?php } ?>
	            <?php if ($status == 2) { ?>
			font-weight: bold;
		    <?php } ?>
			   "
		>
		    <?=$TicketStatus[$status] ?? $TicketStatus[0]; ?>
		</td>
		<td>
		    <?php if ($ticket["creation_date"] != NULL) { ?>
			<?=datex("d/m", $ticket["creation_date"]); ?>
		    <?php } else { ?>
			/
		    <?php } ?>
		</td>
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
	<tr><td colspan="10" style="text-align: center; font-style: italic;">
	    <?=$Dictionnary["NoTicket"]; ?>
	</td></tr>
    <?php } ?>
</table>
<hr /><br />
