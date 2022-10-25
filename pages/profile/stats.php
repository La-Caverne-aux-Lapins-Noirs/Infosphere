<?php

function get(array $arr, $index, $def = NULL)
{
    if (!is_array($index))
	$index = [$index];
    foreach ($index as $idx)
    {
	if (!isset($arr[$idx]))
	    return ($def);
	$arr = $arr[$idx];
    }
    return ($arr);
}

function ppol($w)
{
    global $nopic;

    $nopic = true;
    echo "A-D<br />";
    echo "|&nbsp;&nbsp;&nbsp;&nbsp;|<br />";
    echo "B-C<br />";
    for ($i = 0; $i < 8; $i += 2)
	echo "X: ".$w[$i]." Y: ".$w[$i + 1]."<br />";
}

$nopic = false;

function forge(array $arr, $index, $i, $lab, $def = NULL, $lbase = NULL, $rbase = NULL, $coef = 1)
{
    global $h;
    global $w;
    global $len;

    $sign = 1;
    if ($lbase == NULL && $rbase == NULL)
	$top = $h - 50;
    else
    {
	$top = 0;
	$sign = -1;
    }

    $left = get($arr, [$index, $lab - 1], $def);
    $right = get($arr, [$index, $lab], $def);

    $lx = ($i - 1) * ($w / $len);
    $rx = ($i) * ($w / $len);
    
    return ([
	$lx, $top - $sign * ($lbase + $left * $coef), // Top Left
	$lx, $top - $sign * $lbase, // Bottom left
	$rx, $top - $sign * $rbase, // Bottom right
	$rx, $top - $sign * ($rbase + $right * $coef) // Top right
    ]);
}

$w = @$_GET["w"];
$h = @$_GET["h"];
if (!isset($_GET["s"]) || !isset($_GET["e"]))
    $s = ($e = (int)time()) - 7 * 60 * 60 * 24;
else
{
    $s = $_GET["s"]; // Start
    $e = $_GET["e"]; // End
}

$fnt = __DIR__."/../../res/futura.ttf";

if ($w < 600 || $h < 300)
    die();
if ($s > $e)
{
    $t = $s;
    $s = $e;
    $e = $t;
}
$s = (int)($s / 60 / 60 / 24);
$e = (int)($e / 60 / 60 / 24);
$len = $e - $s + 2;

if (!isset($_GET["d"]))
    $d = [
	"intra_logs" => [
	    $e - 1 => 3,
	    $e => 5
	],
	"work_logs" => [
	    $e - 1 => 5,
	    $e => 8
	],
	"presence" => [],
	"absence" => [],
	"delivery" => [],
	"misdelivery" => [],
	"medals" => [],
	"mismedals" => [],
    ];
else
    $d = json_decode(base64_decode($_GET["d"]), true);

$img = imagecreatetruecolor($w, $h);
imagesavealpha($img, true);
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 0); //127);
imagefill($img, 0, 0, $transparent);

$white = imagecolorallocatealpha($img, 255, 255, 255, 0);
$grey = imagecolorallocatealpha($img, 0, 0, 0, 0);
$grey = imagecolorallocatealpha($img, 255, 0, 0, 0);;

// Lignes verticales marquant les journées
for ($i = 1; $i < $len; ++$i)
{
    imageline($img, $i * $w / $len, 0, $i * $w / $len, $h - 50, $grey);
    $date = date("d/m", ($i + $s) * 60 * 60 * 24);
    $siz = imagettfbbox(10, 0, $fnt, $date);
    imagettftext(
	$img, 10, 0,
	$i * ($w / $len) - $siz[2] / 2,
	$h + ($len > 20 && $i % 2 ? -15 : -30),
	$grey,
	$fnt,
	$date
    );
}

// Echelle
$hh = ($h - 50) / 24;

$darkgreen = imagecolorallocatealpha($img, 0, 128, 0, 90);
$green = imagecolorallocatealpha($img, 64, 255, 255, 90);

// On affiche les valeurs.
for ($i = $e - $s + 1-1; $i >= 0; --$i)
{
    $intra = forge($d, "intra_logs", $i, $i + $s, 0, NULL, NULL, $hh); // Vert foncé
    imagefilledpolygon($img, $intra, 4, $darkgreen);
    ppol($intra);
    $work = forge($d, "work_logs", $i, $i + $s, 0, $intra[7], $intra[0], $hh); // Vert clair
    ppol($work);
    imagefilledpolygon($img, $work, 4, $green);
    break ;

    $presence = get($d, ["presence", $i], 0); // Bleu
    $absence = get($d, ["absence", $i], 0);
    $delivery = get($d, ["delivery", $i], 0);
    $misdelivery = get($d, ["misdelivery", $i], 0);
    $medals = get($d, ["medals", $i], 0);
    $mismedals = get($d, ["mismedals", $i], 0);

    
}

// Bloc de gauche indiquant l'échelle
for ($i = 0; $i <= 24; ++$i)
{
    $y = $hh * $i;
    imageline($img, $w / $len / 2 - 5, $y, $w / $len / 2 + 5, $y, $grey);
    if ($i % 2 || $i < 14)
	continue ;
    $siz = imagettfbbox(10, 0, $fnt, 24 - $i);
    imagettftext(
	$img, 10, 0,
	($w / $len) / 2 - $siz[2] - 10,
	$y - $siz[5] / 2,
	$grey,
	$fnt,
	24 - $i
    );
}
// Lignes du bas et de gauche
imageline($img, $w / $len / 2, $h - 50, $w, $h - 50, $grey);
imageline($img, $w / $len / 2, 0, $w / $len / 2, $h - 50, $grey);

if (error_get_last() == NULL && $nopic == false)
{
    header("Content-type: image/png");
    imagepng($img, NULL, 0, PNG_NO_FILTER);
}
