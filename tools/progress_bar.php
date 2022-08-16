<?php

function base64url_decode($data)
{
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

if (isset($_GET["w"]))
    $w = $_GET["w"];
else
    $w = 900;
if (isset($_GET["h"]))
    $h = $_GET["h"];
else
    $h = 50;

if (isset($_GET["data"]))
{
    $data = base64url_decode($_GET["data"]);
    $data = json_decode($data, true);
    $percents[0] = $data["Grade"]["A"];
    $percents[1] = $data["Grade"]["B"];
    $percents[2] = $data["Grade"]["D"];
    $current = $data["Completion"];
    $mandatory = $data["Mandatory"];
    $note = $data["Note"];
}
else
{
    $data = [];
    $percents[0] = 0.85;
    $percents[1] = 0.70;
    $percents[2] = 0.50;
    $current = 0;
    $mandatory = false;
}

$img = imagecreatetruecolor($w, $h);
$white = imagecolorallocatealpha($img, 255, 255, 255, 0);
$gray = imagecolorallocatealpha($img, 128, 128, 128, 0);
$black = imagecolorallocatealpha($img, 0, 0, 0, 0);
$red = imagecolorallocatealpha($img, 255, 0, 0, 0);
$blue = imagecolorallocatealpha($img, 0, 255, 255, 0);
$yellow = imagecolorallocatealpha($img, 0xFF, 0xFF, 0, 0);
$green = imagecolorallocatealpha($img, 0, 255, 0, 0);
$orange = imagecolorallocatealpha($img, 0, 255, 0xA5, 0);

// C'EST UNE MATIERE NOTE ET CA FAIT CHIER
if (count($note))
{
    $avg = 0;
    $nbr = 0;
    for ($i = 0; $i <= 20; ++$i)
    {
	$x = (($i + 1) / 22.0) * $w;
	imageline($img, $x, $h * 0.1, $x, $h * 0.9 - 15, $gray);
	imagettftext($img, 10, 0, $x - 4, $h * 0.9, $white, __DIR__."/../res/futura.ttf", $i);
	for ($j = 0; isset($note[$i]) && $j < $note[$i]; ++$j)
	{
	    $y = $j * ($h * 0.9 - 15 - $h * 0.1) / ($note[$i] + 1) + $h * 0.1;
	    imagefilledellipse($img, $x, $y, 10, 10, $white);
	    imageellipse($img, $x, $y, 10, 10, $black);
	    $avg += $i;
	    $nbr += 1;
	}
    }
    $avg = round($avg / $nbr);
    $x = (($avg + 1) / 22.0) * $w;
    $y = ($h * 0.9 - $h * 0.1) / 2.0;
    if ($avg >= 18)
    {
	$letter = "A";
	$color = $green;
    }
    else if ($avg >= 15)
    {
	$letter = "B";
	$color = $blue;
    }
    else if ($avg >= 12)
    {
	$letter = "C";
	$color = $yellow;
    }
    else if ($avg >= 10)
    {
	$letter = "D";
	$color = $orange;
    }
    else
    {
	$letter = "E";
	$color = $red;
    }

    imageellipse($img, $x - 0, $y - 0, $h * 0.66, $h * 0.66, $color);
    imageellipse($img, $x - 1, $y - 0, $h * 0.66, $h * 0.66, $color);
    imageellipse($img, $x - 0, $y - 1, $h * 0.66, $h * 0.66, $color);
    imageellipse($img, $x - 1, $y - 1, $h * 0.66, $h * 0.66, $color);
    imagettftext($img, 20, 0, $x - 7 + 0, $y + 10, $color, __DIR__."/../res/futura.ttf", $letter);
    imagettftext($img, 20, 0, $x - 7 + 1, $y + 10, $color, __DIR__."/../res/futura.ttf", $letter);
    imagettftext($img, 20, 0, $x - 7 - 1, $y + 10, $color, __DIR__."/../res/futura.ttf", $letter);
}
// C'EST UNE MATIERE MEDAILLE
else
{
    if ($mandatory == false)
    {
	$minpercent = 2;
	if ($current > $percents[2])
	    $color = $yellow;
	else
	    $color = $red;
    }
    else
    {
	$minpercent = 1;
	$color = $green;
    }

    // Les données
    for ($i = 0; $i < $current * $w; ++$i)
	imageline($img, $i, $h * 0.3, $i, $h * 0.7, $color);


    // Ligne allant de gauche a droite
    imageline($img, 0, $h / 2, $w, $h / 2, $white);

    for ($i = $minpercent; $i >= 0; --$i)
    {
	imageline($img,
		  $percents[$i] * $w, $h * 0.1,
		  $percents[$i] * $w, $h * 0.9,
		  $white
	);
	imagettftext($img, 10, 90,
		     $percents[$i] * $w, $h * 0.5,
		     $white, __DIR__."/../res/futura.ttf",
		     sprintf("% 3d", (int)($percents[$i] * 100))
	);
	imagettftext($img, 10, 90,
		     $percents[$i] * $w, $h * 0.5,
		     $white, __DIR__."/../res/futura.ttf",
		     sprintf("% 3d", (int)($percents[$i] * 100))
	);
    }

    if ($mandatory == false)
    {
	imagettftext($img, 10, 0,
		     (($percents[1] * $w - $percents[2] * $w) - 10) / 2.0 + $percents[2] * $w, $h * 0.95,
		     $white, __DIR__."/../res/futura.ttf", "D");
	imagettftext($img, 10, 0,
		     ($percents[2] * $w - 10) / 2.0, $h * 0.95,
		     $white, __DIR__."/../res/futura.ttf", "/");
    }
    else
    {
	imagettftext($img, 10, 0,
		     ($percents[1] * $w - 10) / 2.0, $h * 0.95,
		     $white, __DIR__."/../res/futura.ttf", "C");
	imagettftext($img, 10, 0,
		     (($w - $percents[0] * $w) - 10) / 2.0 + $percents[0] * $w, $h * 0.95,
		     $white, __DIR__."/../res/futura.ttf", "A");
	imagettftext($img, 10, 0,
		     (($percents[0] * $w - $percents[1] * $w) - 10) / 2.0 + $percents[1] * $w, $h * 0.95,
		     $white, __DIR__."/../res/futura.ttf", "B");
    }

    imagettftext(
	$img, 20, 0, ($current * $w - 20 * 3) / 2, $h * 0.5 + 10, $black, __DIR__."/../res/futura.ttf",
	sprintf("% 3d%%", (int)($current * 100)));
}

if (error_get_last() == NULL)
{
    header ("Content-type: image/png");
    imagepng($img, NULL, 0, PNG_NO_FILTER);
    imagedestroy($img);
}
