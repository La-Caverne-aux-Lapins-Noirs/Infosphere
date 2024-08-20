<?php
require_once ("profile_stats_func.php");

$fnt = __DIR__."/../res/futura.ttf";
$dbg = true;

while (error_get_last())
    error_clear_last();

$dbg = false;
$nopic = false;
$w = $_GET["w"];
$h = $_GET["h"];
if (!isset($_GET["s"]) || !isset($_GET["e"]))
    $startday = ($endday = (int)time()) - 7 * 60 * 60 * 24;
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

$biggest = 0;
$smallest = 0;
foreach ($data as $k => $idx)
{
    foreach ($idx as $kk => $vv)
    {
	if (substr($k, 0, 3) == "mis" && $landmark == "m")
	    $data[$k][$kk] = $vv = -$vv;
	if ($biggest < $vv)
	    $biggest = $vv;
	if ($smallest > $vv)
	    $smallest = $vv;
    }
}

$bladder = $biggest + 2; // "biggest ladder", pas vessie.
if ($landmark == "m")
    $sladder = $smallest - 2; // smallest ladder
else
    $sladder = 0;
$laddersize = $bladder - $sladder;

$img = imagecreatetruecolor($w, $h);
imagesavealpha($img, true);
$lines = imagecolorallocatealpha($img, 0, 0, 0, 0);
$linesa = imagecolorallocatealpha($img, 0, 0, 0, 90);

$black = imagecolorallocatealpha($img, 0, 0, 0, 0);
$realwhite = imagecolorallocatealpha($img, 255, 255, 255, 0);

$white = imagecolorallocatealpha($img, 64, 64, 64, 0);
$whitea = imagecolorallocatealpha($img, 64, 64, 64, 90);

$pink = imagecolorallocatealpha($img, 250, 128, 128, 0);
$pinka = imagecolorallocatealpha($img, 250, 128, 128, 90);
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
$greena = imagecolorallocatealpha($img, 0, 255, 0, 90);
$darkgreen = imagecolorallocatealpha($img, 0, 128, 0, 0);
$darkgreena = imagecolorallocatealpha($img, 0, 128, 0, 90);

$blue = imagecolorallocatealpha($img, 0, 0, 255, 0);
$bluea = imagecolorallocatealpha($img, 0, 0, 255, 90);
$darkblue = imagecolorallocatealpha($img, 0, 0, 128, 0);
$darkbluea = imagecolorallocatealpha($img, 0, 0, 128, 60);


$red = imagecolorallocatealpha($img, 255, 0, 0, 0);
$reda = imagecolorallocatealpha($img, 255, 0, 0, 90);

$yellow = imagecolorallocatealpha($img, 255, 255, 0, 0);
$teal = imagecolorallocatealpha($img, 0, 255, 255, 0);
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $transparent);

// Lignes verticales marquant les journées
for ($i = 1; $i < $len; ++$i)
{
    imageline($img, $i * $w / $len, 50, $i * $w / $len, $h - 50, $lines);

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
	imagefilledpolygon($img, $coords, 4, $linesa);
    }
    
    $date = date("d/m", ($i + $startday) * 60 * 60 * 24);
    $siz = imagettfbbox(10, 0, $fnt, $date);
    imagettftext(
	$img, 10, 0,
	$i * ($w / $len) - $siz[2] / 2,
	50 + ($len > 20 && $i % 2 ? -20 : -5),
	$lines,
	$fnt,
	$date
    );
    imagettftext(
	$img, 10, 0,
	$i * ($w / $len) - $siz[2] / 2,
	$h + ($len > 20 && $i % 2 ? -15 : -30),
	$lines,
	$fnt,
	$date
    );
}

// Echelle
$hh = ($h - 100) / $laddersize;

// On affiche les valeurs. On commence par la fin.
for ($index = $endday - $startday - 1; $index >= 0; --$index)
{
    $coords = draw_area($data, "avg_intra_logs", $startday, $index, $laddersize, $darkbluea, $darkblue);
    draw_area($data, "avg_work_logs", $startday, $index, $laddersize, $darkgreena, $darkgreen, [$coords[8], $coords[9]]);
    draw_area($data, "avg_distant_logs", $startday, $index, $laddersize, $darkpinka, $darkpink, [$coords[8], $coords[9]]);
    draw_area($data, "avg_lock_logs", $startday, $index, $laddersize, $greya, $grey, [$coords[8], $coords[9]]);
    
    $coords = draw_area($data, "intra_logs", $startday, $index, $bladder, $bluea, $blue);
    $coords = draw_area($data, "work_logs", $startday, $index, $bladder, $greena, $green, [$coords[8], $coords[9]]);
    $coords = draw_area($data, "distant_logs", $startday, $index, $bladder, $pinka, $pink, [$coords[8], $coords[9]]);
    $coords = draw_area($data, "lock_logs", $startday, $index, $bladder, $lightgreya, $lightgrey, [$coords[8], $coords[9]]);

    $coords = draw_area($data, "presence", $startday, $index, $bladder, $darkbluea, $darkblue);
    draw_area($data, "late", $startday, $index, $bladder, $darkbluea, $darkblue, [$coords[8], $coords[9]]);
    if ($landmark == "b")
	draw_line($data, "mispresence", $startday, $index, $bladder, $darkblue, $black, true);
    else
	draw_line($data, "mispresence", $startday, $index, $sladder, $darkblue, $black, true);	

    draw_line($data, "delivery", $startday, $index, $bladder, $yellow);
    draw_line($data, "misdelivery", $startday, $index, $bladder, $yellow, $black, true);

    draw_line($data, "medals", $startday, $index, $bladder, $teal);
    draw_line($data, "mismedals", $startday, $index,$bladder, $teal, $black, true);
}

// Bloc de gauche indiquant l'échelle, en mode bottom
for ($i = $sladder; $i <= $bladder; ++$i)
{
    $y = $h - $hh * ($i - $sladder) - 50;

    if ($y < 50)
	continue ;
    // Gauche
    imageline($img, $w / $len / 2 - 5, $y, $w / $len / 2 + 5, $y, $lines);
    // Droite
    imageline($img, $w - $w / $len / 2 - 5, $y, $w - $w / $len / 2 + 5, $y, $lines);
    if ($i % 5 == 0)
	imageline($img, $w / $len / 2 - 5, $y, $w - $w / $len / 2 + 5, $y, $linesa);
    if ($i == 0)
    {
	$shift = 15;
	imagesetthickness($img, 2);
	imageline($img, $w / $len / 2 - 5, $y, $w - $w / $len / 2 + 5, $y, $lines);
	imagesetthickness($img, 0);
    }
    else
	$shift = 10;
    if (($i % 2) && $i != 0)
	continue ;
    $siz = imagettfbbox($shift, 0, $fnt, $i);
    imagettftext(
	$img, $shift, 0,
	($w / $len) / 2 - $siz[2] - $shift,
	$y - $siz[5] / 2,
	$lines,
	$fnt,
	$i
    );
    imagettftext(
	$img, $shift, 0,
	$w - ($w / $len) / 2 + $shift,
	$y - $siz[5] / 2,
	$lines,
	$fnt,
	$i
    );
}

// Lignes du bas et de gauche
imageline($img, $w / $len / 2, 50, $w - $w / $len / 2, 50, $lines);
imageline($img, $w / $len / 2, $h - 50, $w - $w / $len / 2, $h - 50, $lines);
imageline($img, $w / $len / 2, 50, $w / $len / 2, $h - 50, $lines);
imageline($img, $w - $w / $len / 2, 50, $w - $w / $len / 2, $h - 50, $lines);

$xp = $w / $len / 2;
$col = [
    "avg_intra_logs" => $darkblue,
    "avg_work_logs" => $darkgreen,
    "avg_distant_logs" => $darkpink,
    "avg_lock_logs" => $grey,
    
    "intra_logs" => $blue,
    "work_logs" => $green,
    "distant_logs" => $pink,
    "lock_logs" => $lightgrey,
    
    "presence" => $darkblue,
    "late" => $darkbluea,
    "delivery" => $yellow,
    "medals" => $teal,
];
foreach ($data as $k => $v)
{
    if (substr($k, 0, 3) == "mis")
	continue ;
    if (!isset($col[$k]))
	continue ;
    $bbox = imagettfbbox(10, 0, $fnt, $k);

    imagefilledrectangle(
	$img, $xp + $bbox[6] - 5, 0, $xp + $bbox[2] + 5, 30, $col[$k]
    );
    if (substr($k, 0, 3) != "avg")
    {
	imagettftext($img, 10, 0, $xp - 1, 15, $black, $fnt, $k);
	imagettftext($img, 10, 0, $xp, 15, $black, $fnt, $k);
    }
    else
    {
	imagettftext($img, 10, 0, $xp - 1, 15, $realwhite, $fnt, $k);
	imagettftext($img, 10, 0, $xp, 15, $realwhite, $fnt, $k);
    }



    $xp += $bbox[2] + 20;
}

error_clear_last();
if (error_get_last() == NULL && $dbg == false)
{
    if (!isset($User))
	header("Content-type: image/png");
    imagepng($img, NULL, 0, PNG_NO_FILTER);
}

