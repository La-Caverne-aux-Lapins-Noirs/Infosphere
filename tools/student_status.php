
<?php
$span = $logcursor[2] - $logcursor[1];
if ($span <= 0)
    $span = 1;
$current = (float)$logcursor[0];
$required = (float)$logcursor[3];
$curpos = (($current - $logcursor[1]) / $span) * 100;
$refpos = (($required - $logcursor[1]) / $span) * 100;
$curpos = min(100, max(0, $curpos));
$refpos = min(100, max(0, $refpos));
$required_label = isset($Dictionnary["RequiredValue"]) ? $Dictionnary["RequiredValue"] : "Seuil";

$clamp = function ($value, $min, $max)
{
    if ($value < $min)
        return ($min);
    if ($value > $max)
        return ($max);
    return ($value);
};

$mix = function ($a, $b, $ratio) use ($clamp)
{
    return ((int)round($a + ($b - $a) * $clamp($ratio, 0.0, 1.0)));
};

$to_hex = function ($r, $g, $b)
{
    return (sprintf("#%02x%02x%02x", $r, $g, $b));
};

if ($required <= 0)
    $progress_ratio = 1.0;
else
    $progress_ratio = $current / $required;
$progress_ratio = $clamp($progress_ratio, 0.0, 1.35);

if ($progress_ratio < 0.5)
{
    $ratio = $progress_ratio / 0.5;
    $r = 230;
    $g = $mix(65, 150, $ratio);
    $b = $mix(65, 20, $ratio);
}
else if ($progress_ratio < 1.0)
{
    $ratio = ($progress_ratio - 0.5) / 0.5;
    $r = $mix(230, 90, $ratio);
    $g = $mix(150, 190, $ratio);
    $b = $mix(20, 60, $ratio);
}
else
{
    $ratio = min(1.0, $progress_ratio - 1.0);
    $r = $mix(90, 40, $ratio);
    $g = $mix(190, 220, $ratio);
    $b = $mix(60, 90, $ratio);
}

$fill_dark = $to_hex($r, $g, $b);
$fill_light = $to_hex(
    $mix($r, 255, 0.35),
    $mix($g, 255, 0.35),
    $mix($b, 255, 0.35)
);
$track_glow = $to_hex(
    $mix($r, 255, 0.55),
    $mix($g, 255, 0.55),
    $mix($b, 255, 0.55)
);
?>
<div class="logcursor">
    <div class="label"><?=$label; ?></div>
    <div
	class="gauge_track"
	style="border-color: <?=$fill_dark; ?>; box-shadow: inset 0px 0px 6px rgba(0, 0, 0, 0.7), 0px 0px 5px <?=$track_glow; ?>;"
    >
	<div
	    class="gauge_fill"
	    style="width: <?=$curpos; ?>%; background: linear-gradient(to right, <?=$fill_dark; ?>, <?=$fill_light; ?>);"
	>
	    <span class="current_text">
		<?=(int)$current; ?>
	    </span>
	</div>
	<div
	    class="threshold_marker"
	    style="left: <?=$refpos; ?>%;"
	    title="<?=htmlentities($required_label.": ".(int)$required); ?>"
	></div>
    </div>
    <div
	class="threshold_label"
	style="left: <?=$refpos; ?>%;"
    >
	<?=$required_label; ?> <?=(int)$required; ?>
    </div>
</div>

