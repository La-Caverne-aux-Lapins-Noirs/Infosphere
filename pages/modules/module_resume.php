<?php
// Cette page affiche les matières du cycle en cours le plus avancé.
// Les blocs sont illustrés par les wallpaper des modules

get_user_promotions($User);
if (!count($User["cycle"]))
    return ;
$cycle = $User["cycle"][array_key_first($User["cycle"])];
require_once ("fetch_matters.php");
$matters = fetch_matters($User["id"], $cycle["id"]);
$max_credit = 0;
$min_credit = 0;
$min_mandatory_credit = 0;
$max_mandatory_credit = 0;
$mandatory = 0;
$total = 0;
foreach ($matters as $act)
{
    $max_credit += $act->credit_a;
    $min_credit += $act->credit_d;
    if ($act->subscription != FullActivity::MANUAL_SUBSCRIPTION)
    {
	$max_mandatory_credit += $act->credit_a;
	$min_mandatory_credit += $act->credit_d;
	$mandatory += 1;
    }
    $total += 1;
}
?>

<table>
    <tr><td colspan="3">
	<h1><?=$Dictionnary["Cycle"]; ?> <?=$cycle["name"] ?: $cycle["codename"]; ?></h1>
    </td></tr><tr><td>
	<a href="<?=unrollurl(["p" => "CycleMenu", "a" => $cycle["id"]]); ?>">
	    <?=$Dictionnary["SeeSubscribedList"]; ?>
	</a><br />
    </td><td>
	<?=$Dictionnary["AvailableCredits"]; ?> : <?=$min_credit; ?> - <?=$max_credit; ?><br />
	<?=$Dictionnary["MandatoryCredits"]; ?> : <?=$min_mandatory_credit; ?> - <?=$max_mandatory_credit; ?><br />
	<?=$Dictionnary["OptionCredits"]; ?> : <?=$min_credit - $min_mandatory_credit; ?> - <?=$ax_credit - $max_mandatory_credit; ?>
    </td><td>
	<?=$Dictionnary["Matters"]; ?> : <?=$total; ?><br />
	<?=$Dictionnary["MandatoryMatters"]; ?> : <?=$mandatory; ?><br />
	<?=$Dictionnary["OptionalMatters"]; ?> : <?=$total - $mandatory; ?>
    </td></tr>
</table>

<?php foreach ($matters as $act) { ?>
    <?php require ("module_tab.php"); ?>
<?php } ?>
