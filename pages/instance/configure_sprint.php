<div id="sprint_list">
    <?php require (__DIR__."/sprint_list.php"); ?>
</div>

<h3 style="text-align: center;">
    <?=$Dictionnary["CreateASprint"]; ?>
</h3>
<br />
<?php if (isset($sprint)) unset($sprint); ?>
<?php require (__DIR__."/sprint_formular.php");

