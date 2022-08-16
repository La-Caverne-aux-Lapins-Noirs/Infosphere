<h4><?=$Dictionnary["AppointmentSlots"]; ?></h4>
<?php
$firstdate = NULL;
$psess = 0;
foreach ($activity->unique_session->slot as $p)
{
    if ($firstdate == NULL)
	$firstdate = $p["begin_date"];
    else if ($firstdate != $p["begin_date"])
	break ;
    $psess += 1;
}
$i = 0;
$slots = [];
foreach ($activity->unique_session->slot as $s)
{
    if (!isset($slots[$i][0]))
	$slots[$i] = [];
    $slots[$i][] = $s;
    if (count($slots[$i]) == $psess)
	$i += 1;
}

$chess = 0;
$pair = ($psess + 1) % 2 ? 0 : 1;

?>
<table style="text-align: center;">
    <tr style="background-color: rgba(0, 0, 0, 0.6); height: 30px;">
	<th style="background-color: rgba(255, 255, 255, <?=$chess++ % 2 ? 0.6 : 0; ?>)">
	    <?=$Dictionnary["Hour"]; ?>
	</th>
	<?php for ($i = 0; $i < $psess; ++$i) { ?>
	    <th style="background-color: rgba(255, 255, 255, <?=$chess++ % 2 ? 0.6 : 0; ?>)">
		<?=$Dictionnary["Appointment"]; ?> <?=$i + 1; ?>
	    </th>
	<?php } ?>
    </tr>
    <?php
    $chess += $pair;
    foreach ($slots as $slot)
    {
    ?>
	<tr style="height: 60px;">
	    <td style="background-color: rgba(255, 255, 255, <?=$chess++ % 2 ? 0.6 : 0; ?>)">
		<?=datex("H:i", date_to_timestamp($slot[0]["begin_date"])); ?>
	    </td>
	    <?php foreach ($slot as $s) { ?>
		<?php
		if ($s["id_team"] != -2)
		    $color = "255, 255, 255";
		else
		    $color = "0, 0, 0";
		?>
		<td style="background-color: rgba(<?=$color; ?>, <?=$chess++ % 2 ? 0.6 : 0; ?>)">
		    <?php require ("single_slot.php"); ?>
		</td>
	    <?php } ?>
	</tr>
	<?php
	$chess += $pair;
    }
    ?>
</table>



