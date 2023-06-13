<style>
 .sprinttab td
 {
     text-align: center;
 }
</style>
<script>
 function toggle_edit_form(zis, id)
 {
     var f = document.getElementById(id);

     if (!f)
	 return ;
     if (f.style.display == "none")
     {
	 zis.value = "<?=$Dictionnary["Close"]; ?>";
	 f.style.display = "table-row";
     }
     else
     {
	 zis.value = "<?=$Dictionnary["Edit"]; ?>";
	 f.style.display = "none";
     }
 }
</script>
<?php

$sprints[$Dictionnary["ConfigureSprint"]] = __DIR__."/configure_sprint.php";
$tab_data = [-1];
foreach ($activity->user_team["sprints"] as $sprint)
{
    $sprints["#".$sprint["id"]." ".datex("d/m", $sprint["start_date"])." - ".datex("d/m", $sprint["done_date"])] = __DIR__."/configure_tickets.php";
    $tab_data[] = $sprint["id"];
}

$end = array_keys($sprints);
$end = end($end);
tabpanel(
    $sprints,
    $Position.$activity->id."sprints",
    $end,
    "bottomlist",
    "round_box",
    $tab_data
);
