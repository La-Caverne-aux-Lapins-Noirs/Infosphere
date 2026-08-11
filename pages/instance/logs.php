<style>
 #log_table, #log_table td
 {
     border: 1px solid white;
 }
 #log_table td, #log_table th
 {
     text-align: center;
     border: 1px solid white;
 }
 #log_table .log_message
 {
     text-align: left;
 }
 #log_table .log_context
 {
     font-size: x-small;
 }
</style>
<table id="log_table">
    <tr>
	<th style="width: 17%;"><?=$Dictionnary["Date"]; ?></th>
	<th style="width: 10%;"><?=$Dictionnary["User"]; ?></th>
	<th style="width: 8%;">Type</th>
	<th><?=$Dictionnary["Message"]; ?></th>
	<th style="width: 18%;">Contexte</th>
    </tr>
<?php
$contexts = [["activity", $activity->id]];
if ($activity->unique_session && isset($activity->unique_session->id))
    $contexts[] = ["session", $activity->unique_session->id];
$logs = fetch_contextual_logs($contexts, 300);
foreach ($logs as $l)
{
    ?>
    <tr>
	<td><?=human_date($l["log_date"]); ?></td>
	<td><?=($l["codename"]); ?></td>
	<td><?=($l["type"]); ?></td>
	<td class="log_message"><?=htmlentities($l["message"]); ?></td>
	<td class="log_context"><?=htmlentities($l["contexts"] ? $l["contexts"] : $l["url"]); ?></td>
    </tr>
<?php
}
?>
</table>
