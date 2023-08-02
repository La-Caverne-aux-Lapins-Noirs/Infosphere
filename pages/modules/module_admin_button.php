<input
    type="button"
    class="modulebutton"
    onclick="display_panel('module', <?=$matter->id; ?>, true);"
    value="<?=$Dictionnary["ManageModule"]; ?>"
    style="cursor: pointer; width: 100%; height: 70px; font-size: large; border: 0; white-space: normal;"
/>

<?php
$link = [
    "p" => "InstancesMenu",
    "a" => $matter->id
];
?>
<input
    type="button"
    class="modulebutton"
    onclick="document.location='<?=unrollurl($link); ?>';"
    value="<?=$Dictionnary["SeeInstanceConfiguration"]; ?>"
    style="cursor: pointer; width: 100%; height: 70px; font-size: large; border: 0; white-space: normal;"
/>
