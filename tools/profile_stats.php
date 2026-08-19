<?php
require_once ("profile_stats_func.php");

$fnt = __DIR__."/../res/futura.ttf";

while (error_get_last())
    error_clear_last();

$dbg = false;
$nopic = false;
$w = $_GET["w"];
$h = $_GET["h"];
if (!isset($_GET["s"]) || !isset($_GET["e"]))
    $startday = ($endday = (int)now()) - 7 * 60 * 60 * 24;
else
{
    $startday = $_GET["s"]; // Start
    $endday = $_GET["e"]; // End
}

if (isset($_GET["lm"]))
    $landmark = $_GET["lm"]; // "b" pour bottom, "m" pour middle
else
    $landmark = "b";
if ($w < 600 || $h < 300)
    die();
if ($startday > $endday)
{
    $t = $startday;
    $startday = $endday;
    $endday = $t;
}
$startday = (int)($startday / 60 / 60 / 24);
$endday = (int)($endday / 60 / 60 / 24);
$len = $endday - $startday + 2;
if ($len % 2 ? 0 : 1)
{
    $len += 1;
    $startday -= 1;
}
$data = [];
if (isset($_GET["d"]))
    $data = json_decode(base64_decode($_GET["d"]), true);

// On fait la somme des champs qui s'ajoutent
if (isset($data["avg_intra_logs"]) && isset($data["avg_work_logs"]))
{
    foreach ($data["avg_intra_logs"] as $k => $v)
	$data["avg_logs"][$k] = $v;
    foreach ($data["avg_work_logs"] as $k => $v)
    {
	if (!isset($data["avg_logs"][$k]))
	    $data["avg_logs"][$k] = $v;
	else
	    $data["avg_logs"][$k] += $v;
    }
    foreach ($data["avg_distant_logs"] as $k => $v)
    {
	if (!isset($data["avg_logs"][$k]))
	    $data["avg_logs"][$k] = $v;
	else
	    $data["avg_logs"][$k] += $v;
    }
}

if (isset($data["intra_logs"]) && isset($data["work_logs"]))
{
    foreach ($data["intra_logs"] as $k => $v)
	$data["logs"][$k] = $v;
    foreach ($data["work_logs"] as $k => $v)
    {
	if (!isset($data["logs"][$k]))
	    $data["logs"][$k] = $v;
	else
	    $data["logs"][$k] += $v;
    }
    foreach ($data["distant_logs"] as $k => $v)
    {
	if (!isset($data["logs"][$k]))
	    $data["logs"][$k] = $v;
	else
	    $data["logs"][$k] += $v;
    }
}

function profile_stats_register_value_for_scale($value)
{
    global $biggest;
    global $smallest;

    $value = (float)$value;
    if ($biggest < $value)
        $biggest = $value;
    if ($smallest > $value)
        $smallest = $value;
}

function profile_stats_stack_days_for_scale(array $data, array $labels)
{
    $days = [];
    foreach ($labels as $label)
    {
        if (!isset($data[$label]) || !is_array($data[$label]))
            continue ;
        foreach ($data[$label] as $day => $value)
        {
            if (!isset($days[$day]))
                $days[$day] = 0;
            $days[$day] += (float)$value;
        }
    }
    foreach ($days as $value)
        profile_stats_register_value_for_scale($value);
}

function profile_stats_negative_days_for_scale(array $data, array $labels)
{
    foreach ($labels as $label)
    {
        if (!isset($data[$label]) || !is_array($data[$label]))
            continue ;
        foreach ($data[$label] as $value)
            profile_stats_register_value_for_scale(-(float)abs($value));
    }
}

$biggest = 0;
$smallest = 0;
foreach ($data as $k => $idx)
{
    if (substr($k, 0, 8) == "halfday_")
	continue ;
    foreach ($idx as $kk => $vv)
    {
	if (substr($k, 0, 3) == "mis" && $landmark == "m")
	    $data[$k][$kk] = $vv = -$vv;
	profile_stats_register_value_for_scale($vv);
    }
}

// Les histogrammes d'activité sont dessinés en barres empilées.
// L'ancienne échelle ne prenait en compte que chaque série isolée, ou un
// sous-total incomplet, ce qui permettait aux barres cumulées (notamment avec
// lock_logs et ssh_idle_logs) de dépasser le cadre du graphe.
profile_stats_stack_days_for_scale($data, [
    "work_logs",
    "distant_logs",
    "ssh_idle_logs",
    "intra_logs",
    "lock_logs",
]);
profile_stats_stack_days_for_scale($data, [
    "avg_intra_logs",
    "avg_work_logs",
    "avg_distant_logs",
    "avg_ssh_idle_logs",
    "avg_lock_logs",
]);
profile_stats_stack_days_for_scale($data, [
    "presence",
    "late",
]);

// Les absences historiques sont dessinées sous la ligne zéro quand le graphe
// utilise un repère médian. Elles doivent donc aussi réserver de l'espace vers
// le bas dans ce mode.
if ($landmark == "m")
    profile_stats_negative_days_for_scale($data, ["absence_hist", "mispresence"]);

if ($landmark == "m")
{
    $limit = max($biggest, -$smallest) + 2;
    $bladder = $limit; // "biggest ladder", pas vessie.
    $sladder = -$limit; // smallest ladder
}
else
{
    $bladder = $biggest + 2;
    $sladder = 0;
}
$laddersize = $bladder - $sladder;

$img = imagecreatetruecolor(intval($w), intval($h));
imagesavealpha($img, true);
$lines = imagecolorallocatealpha($img, 0, 0, 0, 0);
$linesa = imagecolorallocatealpha($img, 0, 0, 0, 90);

$black = imagecolorallocatealpha($img, 0, 0, 0, 0);
$realwhite = imagecolorallocatealpha($img, 255, 255, 255, 0);

$white = imagecolorallocatealpha($img, 64, 64, 64, 0);
$whitea = imagecolorallocatealpha($img, 64, 64, 64, 90);

$pink = imagecolorallocatealpha($img, 250, 128, 128, 0);
$pinka = imagecolorallocatealpha($img, 250, 128, 128, 64);
$darkpink = imagecolorallocatealpha($img, 125, 64, 64, 0);
$darkpinka = imagecolorallocatealpha($img, 125, 64, 64, 90);

$lightgrey = imagecolorallocatealpha($img, 64+128, 64+128, 64+128, 0);
$lightgreya = imagecolorallocatealpha($img, 64+128, 64+128, 64+128, 60);
$grey = imagecolorallocatealpha($img, 64, 64, 64, 0);
$greya = imagecolorallocatealpha($img, 64, 64, 64, 60);
$darkgrey = imagecolorallocatealpha($img, 16, 16, 16, 0);
$darkgreya = imagecolorallocatealpha($img, 16, 16, 16, 60);

$lightgreen = imagecolorallocatealpha($img, 128, 255, 128, 0);
$lightgreena = imagecolorallocatealpha($img, 128, 255, 128, 90);
$green = imagecolorallocatealpha($img, 0, 255, 0, 0);
$greena = imagecolorallocatealpha($img, 0, 255, 0, 64);
$darkgreen = imagecolorallocatealpha($img, 0, 128, 0, 0);
$darkgreena = imagecolorallocatealpha($img, 0, 128, 0, 90);

$blue = imagecolorallocatealpha($img, 0, 0, 255, 0);
$bluea = imagecolorallocatealpha($img, 0, 0, 255, 64);
$darkblue = imagecolorallocatealpha($img, 0, 0, 128, 0);
$darkbluea = imagecolorallocatealpha($img, 0, 0, 128, 60);


$red = imagecolorallocatealpha($img, 255, 0, 0, 0);
$reda = imagecolorallocatealpha($img, 255, 0, 0, 90);

$orange = imagecolorallocatealpha($img, 255, 160, 0, 0);
$orangea = imagecolorallocatealpha($img, 255, 160, 0, 72);
$darkorange = imagecolorallocatealpha($img, 144, 80, 0, 0);

$halfgreen = imagecolorallocatealpha($img, 120, 255, 120, 105);
$halfred = imagecolorallocatealpha($img, 255, 90, 90, 105);
$halforange = imagecolorallocatealpha($img, 255, 170, 60, 100);
$halfgrey = imagecolorallocatealpha($img, 190, 190, 190, 114);

$yellow = imagecolorallocatealpha($img, 255, 255, 0, 0);
$yellowa = imagecolorallocatealpha($img, 255, 255, 0, 72);
$teal = imagecolorallocatealpha($img, 0, 255, 255, 0);
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $transparent);

// Lignes verticales marquant les journées
for ($i = 1; $i < $len; ++$i)
{
    infosphere_imageline($img, intval($i * $w / $len), 50, intval($i * $w / $len), $h - 50, $lines);

    if ($i % 2)
    {
	$coords = [
	    ($i + 0) * $w / $len,
	    50,
	    ($i + 0) * $w / $len,
	    $h - 50,
	    ($i + 1) * $w / $len,
	    $h - 50,
	    ($i + 1) * $w / $len,
	    50,
	];
	infosphere_imagefilledpolygon($img, $coords, $linesa);
    }
    
    $date = date("d/m", ($i + $startday) * 60 * 60 * 24);
    $siz = imagettfbbox(10, 0, $fnt, $date);
    infosphere_imagettftext(
	$img, 10, 0,
	intval($i * ($w / $len) - $siz[2] / 2),
	50 + ($len > 20 && $i % 2 ? -20 : -5),
	$lines,
	$fnt,
	$date
    );
    infosphere_imagettftext(
	$img, 10, 0,
	intval($i * ($w / $len) - $siz[2] / 2),
	$h + ($len > 20 && $i % 2 ? -15 : -30),
	$lines,
	$fnt,
	$date
    );
}

// Echelle
$hh = ($h - 100) / $laddersize;

$zero_y = $landmark == "m" ? intval($h - 50 + $sladder * $hh) : intval($h - 50);

// Fond indiquant la présence aux demi-journées, derrière les barres d'activité.
if (isset($data["halfday_am"]) || isset($data["halfday_pm"]))
{
    $halfday_colors = [
	1 => $halfgreen,
	  -1 => $halfred,
	     -2 => $halforange,
	0 => $halfgrey,
    ];
    for ($index = $endday - $startday - 1; $index >= 0; --$index)
    {
	if ($index <= 0)
	    continue ;
	$day = $index + $startday;
	$x1 = intval($w / $len / 2 + ($index + 0.05 - 1) * $w / $len);
	$x2 = intval($w / $len / 2 + ($index + 0.95 - 1) * $w / $len);
	$am = isset($data["halfday_am"][$day]) ? (int)$data["halfday_am"][$day] : 0;
	$pm = isset($data["halfday_pm"][$day]) ? (int)$data["halfday_pm"][$day] : 0;
	$am_color = isset($halfday_colors[$am]) ? $halfday_colors[$am] : $halfgrey;
	$pm_color = isset($halfday_colors[$pm]) ? $halfday_colors[$pm] : $halfgrey;
	infosphere_imagefilledrectangle($img, $x1, 50, $x2, $zero_y, $am_color);
	infosphere_imagefilledrectangle($img, $x1, $zero_y, $x2, $h - 50, $pm_color);
    }
}

// On affiche les valeurs. On commence par la fin.
for ($index = $endday - $startday - 1; $index >= 0; --$index)
{
    $coords = draw_area($data, "avg_intra_logs", $startday, $index, $laddersize, $transparent, $darkblue);
    $coords = draw_area($data, "avg_work_logs", $startday, $index, $laddersize, $transparent, $darkgreen, [$coords[8], $coords[9]]);
    $coords = draw_area($data, "avg_distant_logs", $startday, $index, $laddersize, $transparent, $darkpink, [$coords[8], $coords[9]]);
    $coords = draw_area($data, "avg_ssh_idle_logs", $startday, $index, $laddersize, $transparent, $darkorange, [$coords[8], $coords[9]]);
    draw_area($data, "avg_lock_logs", $startday, $index, $laddersize, $transparent, $grey, [$coords[8], $coords[9]]);
    
    $x = 0;
    $y = $h - 50;
    $hours = 0;
    foreach ([
	"work_logs" => [$green, $greena, true],
	"distant_logs" => [$pink, $pinka, true],
	"ssh_idle_logs" => [$orange, $orangea, false],
	"intra_logs" => [$blue, $bluea, true],
	"lock_logs" => [$grey, $greya, false]
    ] as $label => $color)
    {
	if (isset($data[$label][$index + $startday]) && $index > 0)
	{
	    $coords = [
		$w / $len / 2 + ($index + 0.2 - 1) * $w / $len, $y,
		$w / $len / 2 + ($index + 0.2 - 1) * $w / $len, $y - $data[$label][$index + $startday] * $hh,
		$w / $len / 2 + ($index + 0.8 - 1) * $w / $len, $y - $data[$label][$index + $startday] * $hh,
		$w / $len / 2 + ($index + 0.8 - 1) * $w / $len, $y,
	    ];
	    infosphere_imagefilledpolygon($img, $coords, $color[1]);
	    infosphere_imageline($img, $coords[0], $coords[1], $coords[2], $coords[3], $color[0]);
	    infosphere_imageline($img, $coords[2], $coords[3], $coords[4], $coords[5], $color[0]);
	    infosphere_imageline($img, $coords[4], $coords[5], $coords[6], $coords[7], $color[0]);
	    infosphere_imageline($img, $coords[6], $coords[7], $coords[0], $coords[1], $color[0]);
	    $y -= $data[$label][$index + $startday] * $hh;
	    if ($color[2])
		$hours += $data[$label][$index + $startday];
	}
    }
    if ($index > 0)
    {
	$hours = sprintf("%d:%d", (int)$hours, ($hours - (int)$hours) * 60);
	$siz = imagettfbbox(10, 0, $fnt, $hours);
	infosphere_imagettftext(
	    $img, 10, 0,
	    intval($w / $len / 2 + ($index + 0.5 - 1) * $w / $len - $siz[2] / 2),
	    $y - 5,
	    $lines, $fnt, $hours
	);
    }

    /*
    $coords = draw_area($data, "intra_logs", $startday, $index, $bladder, $bluea, $blue);
    $coords = draw_area($data, "work_logs", $startday, $index, $bladder, $greena, $green, [$coords[8], $coords[9]]);
    $coords = draw_area($data, "distant_logs", $startday, $index, $bladder, $pinka, $pink, [$coords[8], $coords[9]]);
    $coords = draw_area($data, "lock_logs", $startday, $index, $bladder, $lightgreya, $lightgrey, [$coords[8], $coords[9]]);
     */

    if (isset($data["presence_hist"]) || isset($data["absence_hist"]) || isset($data["presence"]) || isset($data["late"]) || isset($data["mispresence"]))
    {
        $day = $index + $startday;
        $presence = 0;
        $late_value = 0;
        $absence = 0;

        if (isset($data["presence_hist"]) || isset($data["absence_hist"]))
        {
            $presence = isset($data["presence_hist"][$day]) ? $data["presence_hist"][$day] : 0;
            $absence = isset($data["absence_hist"][$day]) ? $data["absence_hist"][$day] : 0;
        }
        else
        {
            $presence = isset($data["presence"][$day]) ? $data["presence"][$day] : 0;
            $late_value = isset($data["late"][$day]) ? $data["late"][$day] : 0;
            $absence = isset($data["mispresence"][$day]) ? abs($data["mispresence"][$day]) : 0;
        }

        if ($index > 0)
        {
            $bar_x1 = intval($w / $len / 2 + ($index + 0.18 - 1) * $w / $len);
            $bar_x2 = intval($w / $len / 2 + ($index + 0.82 - 1) * $w / $len);
            $up_y = $zero_y;

            if ($presence > 0)
            {
                $top_y = intval($up_y - $presence * $hh);
                infosphere_imagefilledrectangle($img, $bar_x1, $top_y, $bar_x2, $up_y, $greena);
                infosphere_imagerectangle($img, $bar_x1, $top_y, $bar_x2, $up_y, $green);
                $up_y = $top_y;
            }
            if ($late_value > 0)
            {
                $top_y = intval($up_y - $late_value * $hh);
                infosphere_imagefilledrectangle($img, $bar_x1, $top_y, $bar_x2, $up_y, $orangea);
                infosphere_imagerectangle($img, $bar_x1, $top_y, $bar_x2, $up_y, $orange);
                $up_y = $top_y;
            }
            if ($absence > 0)
            {
                $bottom_y = intval($zero_y + $absence * $hh);
                infosphere_imagefilledrectangle($img, $bar_x1, $zero_y, $bar_x2, $bottom_y, $reda);
                infosphere_imagerectangle($img, $bar_x1, $zero_y, $bar_x2, $bottom_y, $red);
            }
        }
    }

    draw_line($data, "delivery", $startday, $index, $bladder, $yellow);
    draw_line($data, "misdelivery", $startday, $index, $bladder, $yellow, $black, true);

    if (isset($data["medal_acquired_hist"])
        || isset($data["medal_missing_hist"])
        || isset($data["medal_lost_hist"])
        || isset($data["medal_negative_hist"]))
    {
	$day = $index + $startday;
	$zero = $landmark == "m" ? $h - 50 + $sladder * $hh : $h - 50;
	foreach ([
	    "medal_acquired_hist" => [0.10, 0.30, $greena, $green],
	    "medal_missing_hist" => [0.32, 0.52, $lightgreya, $lightgrey],
	    "medal_lost_hist" => [0.54, 0.74, $yellowa, $yellow],
	    "medal_negative_hist" => [0.76, 0.96, $reda, $red],
	] as $label => $bar)
	{
	    if (!isset($data[$label][$day]) || $index <= 0)
		continue ;
	    $value = $data[$label][$day];
	    if ($value == 0)
		continue ;
	    $coords = [
		$w / $len / 2 + ($index + $bar[0] - 1) * $w / $len, $zero,
		$w / $len / 2 + ($index + $bar[0] - 1) * $w / $len, $zero - $value * $hh,
		$w / $len / 2 + ($index + $bar[1] - 1) * $w / $len, $zero - $value * $hh,
		$w / $len / 2 + ($index + $bar[1] - 1) * $w / $len, $zero,
	    ];
	    infosphere_imagefilledpolygon($img, $coords, $bar[2]);
	    infosphere_imageline($img, $coords[0], $coords[1], $coords[2], $coords[3], $bar[3]);
	    infosphere_imageline($img, $coords[2], $coords[3], $coords[4], $coords[5], $bar[3]);
	    infosphere_imageline($img, $coords[4], $coords[5], $coords[6], $coords[7], $bar[3]);
	    infosphere_imageline($img, $coords[6], $coords[7], $coords[0], $coords[1], $bar[3]);
	}
    }
    else
    {
	draw_line($data, "medals", $startday, $index, $bladder, $teal);
	draw_line($data, "mismedals", $startday, $index,$bladder, $teal, $black, true);
    }
    
}

// Bloc de gauche indiquant l'échelle, en mode bottom
for ($i = $sladder; $i <= $bladder; ++$i)
{
    $y = $h - $hh * ($i - $sladder) - 50;

    if ($y < 50)
	continue ;
    
    // Gauche
    infosphere_imageline($img, intval($w / $len / 2 - 5), intval($y), intval($w / $len / 2 + 5), intval($y), $lines);
    // Droite
    infosphere_imageline($img, intval($w - $w / $len / 2 - 5), intval($y), intval($w - $w / $len / 2 + 5), intval($y), $lines);
    if ($i % 5 == 0)
	    infosphere_imageline($img, intval($w / $len / 2 - 5), intval($y), intval($w - $w / $len / 2 + 5), intval($y), $linesa);
    if ($i == 0)
    {
	$shift = 15;
	imagesetthickness($img, 2);
	infosphere_imageline($img, intval($w / $len / 2 - 5), intval($y), intval($w - $w / $len / 2 + 5), intval($y), $lines);
	imagesetthickness($img, 0);
    }
    else
	$shift = 10;
    if (($i % 2) && $i != 0)
	continue ;
    $siz = imagettfbbox($shift, 0, $fnt, $i);
    infosphere_imagettftext(
	$img, $shift, 0,
	intval(($w / $len) / 2 - $siz[2] - $shift),
	intval($y - $siz[5] / 2),
	$lines,
	$fnt,
	$i
    );
    infosphere_imagettftext(
	$img, $shift, 0,
	intval($w - ($w / $len) / 2 + $shift),
	intval($y - $siz[5] / 2),
	$lines,
	$fnt,
	$i
    );
}

// Lignes du bas et de gauche
infosphere_imageline($img, intval($w / $len / 2), 50, intval($w - $w / $len / 2), 50, $lines);
infosphere_imageline($img, intval($w / $len / 2), intval($h - 50), intval($w - $w / $len / 2), intval($h - 50), $lines);
infosphere_imageline($img, intval($w / $len / 2), 50, intval($w / $len / 2), $h - 50, $lines);
infosphere_imageline($img, intval($w - $w / $len / 2), 50, intval($w - $w / $len / 2), intval($h - 50), $lines);

$xp = $w / $len / 2;
$col = [
    "avg_intra_logs" => $darkblue,
    "avg_work_logs" => $darkgreen,
    "avg_distant_logs" => $darkpink,
    "avg_ssh_idle_logs" => $darkorange,
    "avg_lock_logs" => $grey,
    
    "intra_logs" => $blue,
    "work_logs" => $green,
    "distant_logs" => $pink,
    "ssh_idle_logs" => $orange,
    "lock_logs" => $lightgrey,
    
    "presence" => $darkblue,
    "presence_hist" => $green,
    "late" => $darkbluea,
    "absence_hist" => $red,
    "delivery" => $yellow,
    "medals" => $teal,
    "medal_acquired_hist" => $green,
    "medal_missing_hist" => $lightgrey,
    "medal_lost_hist" => $yellow,
    "medal_negative_hist" => $red,
];
$legend_labels = [
    "avg_ssh_idle_logs" => "avg ssh idle",
    "ssh_idle_logs" => "ssh idle",
    "presence_hist" => "presence",
    "absence_hist" => "absence",
    "medal_acquired_hist" => "medals acquired",
    "medal_missing_hist" => "medals missing",
    "medal_lost_hist" => "medals lost",
    "medal_negative_hist" => "negative medals",
];

foreach ($data as $k => $v)
{
    if (substr($k, 0, 3) == "mis")
	continue ;
    if (!isset($col[$k]))
	continue ;
    $legend = isset($legend_labels[$k]) ? $legend_labels[$k] : $k;
    $bbox = imagettfbbox(10, 0, $fnt, $legend);

    infosphere_imagefilledrectangle(
	$img, intval($xp + $bbox[6] - 5), 0, intval($xp + $bbox[2] + 5), 30, $col[$k]
    );
    if (substr($k, 0, 3) != "avg")
    {
	infosphere_imagettftext($img, 10, 0, intval($xp - 1), 15, $black, $fnt, $legend);
	infosphere_imagettftext($img, 10, 0, intval($xp), 15, $black, $fnt, $legend);
    }
    else
    {
	infosphere_imagettftext($img, 10, 0, intval($xp - 1), 15, $realwhite, $fnt, $legend);
	infosphere_imagettftext($img, 10, 0, intval($xp), 15, $realwhite, $fnt, $legend);
    }

    $xp += $bbox[2] + 20;
}

if (error_get_last() == NULL && $dbg == false)
{
    if (!isset($User))
	header("Content-type: image/png");
    imagepng($img, NULL, 0, PNG_NO_FILTER);
}
print_r(error_get_last());
