<?php
// Cette page affiche les matières du cycle en cours le plus avancé.
// Les blocs sont illustrés par les wallpaper des modules

get_user_promotions($User);
if (!count($User["cycle"]))
    return ;
$FUK = true;
$User["cycle"] = merge_cycles($User["cycle"]);

foreach ($User["cycle"] as $cycle)
{
    // $matters = fetch_matters($User["id"], $cycle["id_cycle"]);
    $matters = $cycle["matters"];
    $max_credit = 0;
    $min_credit = 0;
    $min_mandatory_credit = 0;
    $max_mandatory_credit = 0;
    $mandatory = 0;
    $total = 0;
    $nmatters = [];
    foreach ($matters as $act)
    {
	($nact = new FullActivity)->build($act["id"], false, false);
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
	<h1 style="width: 100%;"><?=$Dictionnary["Cycle"]; ?> <?=$cycle["name"] ?: $cycle["codename"]; ?></h1>
	<br /><br />
    </td></tr>
    <tr><td>
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
