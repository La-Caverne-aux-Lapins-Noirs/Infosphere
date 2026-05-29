<?php
if (isset($tab_data) && is_array($tab_data))
    $scrum_ticket_list_sprint_id = (int)($tab_data["id_sprint"] ?? -1);
else if (isset($tab_data))
    $scrum_ticket_list_sprint_id = (int)$tab_data;
else
    $scrum_ticket_list_sprint_id = -1;
?>
<div id="ticket_list<?=$scrum_ticket_list_sprint_id; ?>">
    <?php require (__DIR__."/ticket_list.php"); ?>
</div>

<h3 style="text-align: center;">
    <?=$Dictionnary["AddATicket"]; ?>
</h3>
<br />
<?php if (isset($ticket)) unset($ticket); ?>
<?php require (__DIR__."/ticket_formular.php");

