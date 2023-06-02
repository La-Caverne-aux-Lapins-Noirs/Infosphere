<div id="ticket_list">
    <?php require (__DIR__."/ticket_list.php"); ?>
</div>

<h3 style="text-align: center;">
    <?=$Dictionnary["AddATicket"]; ?>
</h3>
<br />
<?php if (isset($ticket)) unset($ticket); ?>
<?php require (__DIR__."/ticket_formular.php");

