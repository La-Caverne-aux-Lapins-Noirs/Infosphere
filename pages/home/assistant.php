<h2 style="text-align: center;"><?=$Dictionnary["PersonnalAssistant"]; ?></h2>
<br />

<?php
$alerts = "";

ob_start();
// Ici c'est les alertes.
if ($User["greatest_cycle"] != -1)
{
    $cyc = $User["greatest_cycle_data"]["id_cycle"];
    $cycle = db_select_one("objective FROM cycle WHERE id = $cyc");
    $matters = db_select_all("
	      activity.credit_a as aa, template.credit_a as ta, activity.codename
	      FROM activity
	      LEFT JOIN activity as template ON activity.id_template = template.id
	      LEFT JOIN activity_cycle ON activity.id = activity_cycle.id_activity
	      LEFT JOIN team ON team.id_activity = activity.id
	      LEFT JOIN user_team ON team.id = user_team.id_team
	      WHERE activity_cycle.id_cycle = $cyc
	      AND user_team.id_user = {$User["id"]}
	      AND (activity.parent_activity = -1 || activity.parent_activity IS NULL)
	      AND activity.is_template = 0
	      AND activity.deleted IS NULL
	      ");
    $creds = 0;
    foreach ($matters as $c)
    {
	if ($c["aa"] != NULL)
	    $creds += $c["aa"];
	else
	    $creds += $c["ta"];
    }
    if ($creds < $cycle["objective"])
	echo '<b style="color: red;">'.
	     $Dictionnary["NotEnoughCredits"]." ".
	     "($creds/{$cycle["objective"]})".
	     "</b><br />"
	     ;
}

$alerts = ob_get_clean();
if ($alerts != "")
{
    echo "<h3>".$Dictionnary["Alerts"]."</h3>";
    echo '<p style="text-align: center; font-size: small;">';
    echo $alerts;
    echo '</p><br />';
}
?>

<h3><?=$Dictionnary["CurrentTickets"]; ?></h3>
<ul>
    <?php if (isset($User["tickets"])) { ?>
	<?php foreach ($User["tickets"] as $ticket) { ?>
	    <li>
		<a href="<?=unrollurl([
			 "p" => "InstanceMenu",
			 "a" => $ticket["activity_id"],
			 "c" => $ticket["id"]
			 ]); ?>">
		    <?=$ticket["name"]; ?><br />
		    <i><?=$Dictionnary["From"]; ?> <?=$ticket["activity_name"]; ?></i>
		</a>
	    </li>
	<?php } ?>
    <?php } else { ?>
	<li><i><?=$Dictionnary["NoTicket"]; ?></i></li>
    <?php } ?>
</ul>
<br />

<h3><?=$Dictionnary["TodoList"]; ?></h3>
<ul>
    <div id="todolist">
	<?php require_once ("todolist.php"); ?>
    </div>
    <li style="padding-top: 5px;">
	<form
	    action="/api/user/<?=$User["id"]; ?>/todolist"
	    method="post"
	    style="position: relative;"
	>
	    <input
		type="text"
		id="addtodo"
		name="content"
		value=""
		style="position: absolute; width: calc(100% - 25px); height: 20px;"
		placeholder="<?=$Dictionnary["AddAnEntry"]; ?>"
	    />
	    <input
		type="button"
		onclick="silent_submit(this, 'todolist', null, 'addtodo');"
		value="+"
		style="position: absolute; right: 0px; width: 20px; height: 20px;"
	    />
	</form>
    </li>
</ul>
<br />

<h3><?=$Dictionnary["ObjectivesAndNotes"]; ?></h3>
<form
    action="/api/user/<?=$User["id"]; ?>/properties"
    method="put"
    style="position: relative; height: 120px;"
>
    <textarea
	name="objectives"
	id="objectives"
	oninput="delay_before_submit(1000, this, 'objectives');"
	style="width: calc(100% - 0px); height: 100px; resize: none; position: absolute;"
    ><?=strlen(@$User["objectives"]) ? $User["objectives"] : ""; ?></textarea>
</form>
