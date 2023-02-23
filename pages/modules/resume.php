<?php
// Cette page affiche les matières du cycle en cours le plus avancé.
// Les blocs sont illustrés par les wallpaper des modules

$already_done = [];
foreach ($user->sublayer as $cycle)
{
    if (now() > date_to_timestamp($cycle->last_day))
	continue ;
    $max_credit = 0;
    $min_credit = 0;
    $min_mandatory_credit = 0;
    $max_mandatory_credit = 0;
    $mandatory = 0;
    $total = 0;
    $nmatters = [];
    $matters = db_select_all("
       activity.{$Language}_name as name, activity.*
       FROM activity_cycle
       LEFT JOIN activity ON activity_cycle.id_activity = activity.id
       WHERE activity_cycle.id_cycle = {$cycle->id}
       AND (activity.parent_activity IS NULL OR activity.parent_activity = -1)
    ");

    $acquired_credits = 0; // Pas encore construit.
    foreach ($matters as $nact)
    {
	$nact = (object)$nact;
	if (!isset($already_done[$nact->id]))
	    $already_done[$nact->id] = 1;
	else
	    continue ;

	$outact = NULL;
	foreach ($user->merged_sublayers as $mcyc)
	{
	    foreach ($mcyc->matters as $mmatt)
	    {
		if ($mmatt->id == $nact->id)
		{
		    $outact = $mmatt;
		}
	    }
	}
	if ($outact == NULL)
	{
	    $id = $nact->id;
	    ($nact = new FullActivity)->build($id, false, false);    
	    $nact->registered = false;
	}
	else
	{
	    $nact = $outact;
	    $nact->registered = true;
	}
	$max_credit += $nact->credit_a;
	$min_credit += $nact->credit_d;
	if ($nact->subscription != FullActivity::MANUAL_SUBSCRIPTION)
	{
	    $max_mandatory_credit += $nact->credit_a;
	    $min_mandatory_credit += $nact->credit_d;
	    $mandatory += 1;
	}
	$total += 1;
	$nmatters[] = $nact;
    }
    $matters = $nmatters;
?>

<table>
    <tr><td colspan="3" style="text-align: center;">
	<br />
	<h1 style="width: 100%;">
	    <?=$Dictionnary["Cycle"]; ?> <?=strlen($cycle->name) ? $cycle->name : $cycle->codename; ?>
	</h1>
	<br /><br />
    </td></tr>
    <tr><td>
	<!-- <?=$Dictionnary["AcquiredCredits"]; ?> : <?=$acquired_credits; ?> -->
	<a href="<?=unrollurl(["p" => "CycleMenu", "a" => $cycle["id"]]); ?>">
	    <?=$Dictionnary["SeeSubscribedList"]; ?>
	</a><br />
    </td><td>
	<?=$Dictionnary["AvailableCredits"]; ?> : <?=$min_credit; ?> - <?=$max_credit; ?><br />
	<?=$Dictionnary["MandatoryCredits"]; ?> : <?=$min_mandatory_credit; ?> - <?=$max_mandatory_credit; ?><br />
	<?=$Dictionnary["OptionCredits"]; ?> : <?=$min_credit - $min_mandatory_credit; ?> - <?=$max_credit - $max_mandatory_credit; ?>
    </td><td>
	<?=$Dictionnary["Matters"]; ?> : <?=$total; ?><br />
	<?=$Dictionnary["MandatoryMatters"]; ?> : <?=$mandatory; ?><br />
	<?=$Dictionnary["OptionalMatters"]; ?> : <?=$total - $mandatory; ?>
    </td></tr>
</table>

<?php foreach ($matters as $act) { ?>
    <?php require ("module_tab.php"); ?>
<?php } ?>

<?php
}
?>
