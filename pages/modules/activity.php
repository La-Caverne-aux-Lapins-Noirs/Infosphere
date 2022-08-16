<?php
$activities = [];
foreach ($cmodule->sublayer as $act)
{
    $activity = new FullActivity;
    $activity->build($act->id, false, true);
    $activities[] = $activity;
}
function retrievedate($a, &$out = NULL)
{
    global $Dictionnary;

    if (count($a->session))
    {
	if ($out !== NULL)
	    $out = $Dictionnary["Session"];
	$min = $a->session[0]->begin_date;
	foreach ($a->session as $s)
	{
	    if ($s->begin_date < $min)
		$min = $s->begin_date;
	}
	return ($min);
    }
    if ($a->subject_appeir_date != NULL)
    {
	if ($out !== NULL)
	    $out = $Dictionnary["SubjectAppeirDate"];
	$ad = $a->subject_appeir_date;
    }
    else if ($a->registration_date != NULL)
    {
	if ($out !== NULL)
	    $out = $Dictionnary["RegistrationDate"];
	$ad = $a->registration_date;
    }
    else if ($a->emergence_date != NULL)
    {
	if ($out !== NULL)
	    $out = $Dictionnary["EmergenceDate"];
	$ad = $a->emergence_date;
    }
    else if ($a->pickup_date != NULL)
    {
	if ($out !== NULL)
	    $out = $Dictionnary["PickupDate"];
	$ad = $a->pickup_date;
    }
    else if ($a->close_date != NULL)
    {
	if ($out !== NULL)
	    $out = $Dictionnary["CloseDate"];
	$ad = $a->close_date;
    }
    else
	$ad = NULL;
    return ($ad);
}
function sortactivity($a, $b)
{
    if (($ad = retrievedate($a)) == NULL)
	return (1);
    if (($bd = retrievedate($b)) == NULL)
	return (-1);
    return ($ad - $bd);
}
usort($activities, "sortactivity");
foreach ($activities as $act)
{
    require ("activity.phtml");
}
?>


<?php
return ;
    $timeline = true;
    $slot = true;
    $perc = "50"."%";

    if ($activity->registration_date == NULL || $activity->close_date == NULL)
        $timeline = false;
    if ($activity->maximum_subscription == -1 || $activity->subscription == 0)
        $slot = false;
?>
    <div class="panel panel-primary">
	<div lass="panel-body"><h4 style="margin-left: 10px" ><?=$activity->name; ?></h4>
	</div>
	<div class="panel-footer">
	    <form method="post" action="index.php?<?=unrollget(); ?>">
		<?php if (!$activity->registered) { ?>
		    <input type="hidden" name="action" value="subscribe" />
		    <input type="hidden" name="module" value="<?=$activity->id; ?>" />
		    <input type="submit"  value="<?=$Dictionnary["Subscribe"]; ?>" />
		<?php } else { ?>
		    <input type="hidden" name="action" value="unsubscribe" />
		    <input type="hidden" name="module" value="<?=$activity->id; ?>" />
		    <input type="submit"  value="<?=$Dictionnary["Unsubscribe"];?>" />
		<?php } ?>

		<span><a  style="float: centre" href="index.php?p=ActivityMenu&amp;a=<?=$activity->id; ?>&amp;b=-1">Rejoindre la page d'activité  </a></span> <?php if ($slot != false) { ?>
		    <span style="float: right" >il reste <?=$activity_info["maximum_subscription"] - $activity_info["subscription"]; ?> place</span>  <?php } if ($timeline != false){ ?><span> <br> <br><div style="float: centre; width: 50%;" class="progress">
			<div class="progress-bar" role="progressbar" style="<?=$perc;?>" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
		    </div></span><?php } ?>    </form>

	</div>
    </div>
    <br>
