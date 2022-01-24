<?php

// Sens du tableau:
// 0: Présences
// 1: Absences
// 2: Non inscrit

$now = time();
$nbr_days = 21;
$duration = $one_day * $nbr_days;
$before = db_form_date($now - $duration);

// Présences et absences
$info[0]["label"] = $Dictionnary["Presence"];
$info[0]["dir"] = 1;
$info[0]["color"][0] = 0;
$info[0]["color"][1] = 255;
$info[0]["color"][2] = 0;
$info[0]["color"][3] = 0;

$info[1]["label"] = $Dictionnary["Missing"];
$info[1]["dir"] = -1;
$info[1]["color"][0] = 255;
$info[1]["color"][1] = 0;
$info[1]["color"][2] = 0;
$info[1]["color"][3] = 0;

$info[2]["label"] = $Dictionnary["NotSubscribed"];
$info[2]["dir"] = -1;
$info[2]["color"][0] = 255;
$info[2]["color"][1] = 0;
$info[2]["color"][2] = 255;
$info[2]["color"][3] = 0;

for ($i = 0; $i < $nbr_days; ++$i)
{
    $info[0]["data"][$i] = 0;
    $info[1]["data"][$i] = 0;
    $info[2]["data"][$i] = 0;
    foreach ($data->sublayer as $cycle)
    {
	foreach ($cycle->sublayer as $module)
	{
	    foreach ($module->sublayer as $activity)
	    {
		$dt = date_to_timestamp($activity->begin_date);
		$dt = date("d/m", $dt);
		$dx = date("d/m", ($now - $duration) + $i * $one_day);
		if ($dt == $dx)
		{
		    if ($activity->present->cumulated > 0 || $activity->late->cumulated > 0)
			$info[0]["data"][$i] += 1;
		    if ($activity->missing->cumulated == -2)
			$info[1]["data"][$i] += 1;
		    if ($activity->registered == false)
			$info[2]["data"][$i] += 1;
		}
	    }
	}
    }
}

$w = 900;
$h = 270;
$info["data"] = $info;
$info["start_date"] = $now - $duration;
?>
<div class="final_box" style="width: 100%; height: <?=$h + 10; ?>px; overflow-y: auto; background-color: black !important; background-image: url('./tools/plot.php?w=<?=$w; ?>&amp;h=<?=$h; ?>&amp;data=<?=base64url_encode(json_encode($info, JSON_UNESCAPED_SLASHES)); ?>'); background-size: contain; background-repeat: no-repeat;">
</div>
<br />
<div class="final_box" style="width: 100%; height: <?=$h + 1; ?>px; overflow-x: hidden; overflow-y: hidden; background-color: gray !important;">
    <?php
    $max = 21;
    $now = date_to_timestamp(db_form_date(now(), true));
    for ($i = $max; $i >= 0; --$i)
    {
	$duration = get_student_log($user, $now - $one_day * $i);
	$dur = $duration / (60 * 60);
	if ($dur < 3)
	    $col = "red";
	else if ($dur < 6)
	    $col = "orange";
	else if ($dur < 8)
	    $col = "yellow";
	else if ($dur < 10)
	    $col = "#00FF00";
	else if ($dur < 12)
	    $col = "#00CC00";
	else if ($dur < 14)
	    $col = "#00AA00";
	else if ($dur < 16)
	    $col = "#008800";
	else
	    $col = "#005500";
    ?>
	<div style="width: <?=100.0 / ($max + 1); ?>%; float: left; height: 100%; text-align: center; position: relative;">
	    <span style="font-size: x-small; z-index: 1; position: absolute; top: 10px; width: 100%; left: 0px;">
		<?=datex("d/m", $now - $one_day * $i); ?>
	    </span>
	    <?php
	    if (($height = ($duration / (60 * 60 * 20))) > 0.85)
		$height = 0.85;
	    ?>
	    <div style="height: <?=100.0 * $height; ?>%; background-color: <?=$col; ?>; position: absolute; bottom: 0px; left: 0px; width: 80%; font-size: x-small; left: 10%; background-image: url('./res/double_rays.png'); background-repeat: repeat; background-size: 25px; border: 1px solid rgba(0, 0, 0, 0.3);">
		<span style="position: absolute; top: -15px; left: 0px; width: 100%;">
		    <?=datex("H:i", $duration); ?>
		</span>
	    </div>
	</div>
    <?php
    }
    ?>
</div>
