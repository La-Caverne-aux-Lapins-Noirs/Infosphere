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
</style>
<table id="log_table">
    <tr>
	<th style="width: 20%;"><?=$Dictionnary["Date"]; ?></th>
	<th style="width: 10%;"><?=$Dictionnary["User"]; ?></th>
	<th><?=$Dictionnary["Message"]; ?></th>
    </tr>
<?php
$url = "instance".$activity->id;
$urlhash = crc32($url);
$logs = db_select_all("
   id_user, log_date, message, user.codename
   FROM log
   LEFT JOIN user ON log.id_user = user.id
   WHERE type = 0
   AND urlhash = $urlhash
   AND url = '$url'
   ORDER BY log_date DESC
");
foreach ($logs as $l)
{
    ?>
    <tr>
	<td><?=human_date($l["log_date"]); ?></td>
	<td><?=($l["codename"]); ?></td>
	<td><?=($l["message"]); ?></td>
    </tr>
<?php
}
?>
</table>
