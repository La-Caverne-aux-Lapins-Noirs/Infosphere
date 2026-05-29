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
	 zis.value = "<?=$Dictionnary["Edit/See"]; ?>";
	 f.style.display = "none";
     }
 }

 function scrum_submit(button, method, config)
 {
     var form = button;

     while (form != null && form.tagName.toLowerCase() != "form")
	 form = form.parentNode;
     if (form == null)
	 return (false);
     if (method)
	 form.setAttribute("method", method);
     return (silent_submitf(form, config));
 }
</script>
<?php

$sprints[$Dictionnary["ConfigureSprint"]] = __DIR__."/configure_sprint.php";
$tab_data = [-1];
$requested_sprint = isset($_GET["c"]) && is_number($_GET["c"]) ? (int)$_GET["c"] : -1;
$requested_ticket = isset($_GET["d"]) && is_number($_GET["d"]) ? (int)$_GET["d"] : -1;
$default = $Dictionnary["ConfigureSprint"];
$found_requested_sprint = false;
foreach ($activity->user_team["sprints"] as $sprint)
{
    $label = "#".$sprint["id"]." ".datex("d/m", $sprint["start_date"])." - ".datex("d/m", $sprint["done_date"]);
    $sprints[$label] = __DIR__."/configure_tickets.php";
    $tab_data[] = [
	"id_sprint" => $sprint["id"],
	"id_ticket" => $sprint["id"] == $requested_sprint ? $requested_ticket : -1,
    ];
    if (!$found_requested_sprint)
	$default = $label;
    if ($sprint["id"] == $requested_sprint)
    {
	$default = $label;
	$found_requested_sprint = true;
    }
}

// Les liens directs depuis l'assistant utilisent c/d pour ouvrir le sprint/ticket visé.
// tabpanel privilégie normalement le dernier onglet stocké en localStorage, donc on force
// la sélection avant son initialisation quand une cible explicite est fournie.
if ($requested_sprint != -1 && $found_requested_sprint)
{
?>
    <script>
     localStorage.setItem('<?=addslashes($Position.$activity->id."sprints"); ?>', '<?=md5($default); ?>');
    </script>
<?php
}

tabpanel(
    $sprints,
    $Position.$activity->id."sprints",
    $default,
    "bottomlist",
    "round_box",
    $tab_data
);
